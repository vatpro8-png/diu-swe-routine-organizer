<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\CourseCatalog;
use App\Models\ExamFormTemplate;
use App\Models\ExamRoutine;
use App\Models\ExamScheduleObservation;
use App\Models\ExamSheet;
use App\Models\ExamSlotTimeSeriesMetric;
use Carbon\Carbon;
use Database\Seeders\ExamFormTemplateSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamAnalyticsService
{
    private const DEFAULT_ROOM_CAPACITY = 35;

    /**
     * Immediately synchronize normalized analytical observations and slot time-series metrics
     * when a new exam routine sheet or manual exam slot is provided.
     *
     * @param  Collection<int, ExamRoutine>|null  $entries
     */
    public function syncFromExamType(string $examType, ?ExamSheet $sheet = null, ?Collection $entries = null): void
    {
        $normalizedType = ExamRoutine::normalizeExamType($examType);

        if (ExamFormTemplate::query()->count() === 0) {
            (new ExamFormTemplateSeeder)->run();
        }

        $entries ??= ExamRoutine::forExamType($normalizedType)->get();

        DB::transaction(function () use ($normalizedType, $sheet, $entries): void {
            if ($entries->isEmpty()) {
                ExamScheduleObservation::query()
                    ->where('exam_type', $normalizedType)
                    ->where('is_current', true)
                    ->delete();

                ExamSlotTimeSeriesMetric::query()
                    ->where('exam_type', $normalizedType)
                    ->delete();

                return;
            }

            /** @var ExamRoutine $first */
            $first = $entries->first();
            $semesterName = $first->semester ?: 'Fall';
            $year = (int) ($first->year ?: date('Y'));
            $term = AcademicTerm::resolveOrCreate($semesterName, $year, true);

            ExamScheduleObservation::query()
                ->where('academic_term_id', $term->id)
                ->where('exam_type', $normalizedType)
                ->delete();

            ExamSlotTimeSeriesMetric::query()
                ->where('academic_term_id', $term->id)
                ->where('exam_type', $normalizedType)
                ->delete();

            $templates = ExamFormTemplate::query()
                ->get()
                ->keyBy(static fn (ExamFormTemplate $tpl): string => $tpl->exam_type.'|'.$tpl->slot_code);

            $dateValues = [];
            $slotBuckets = [];

            foreach ($entries as $entry) {
                $effectiveType = $entry->is_overlap ? 'Overlap' : $normalizedType;
                $slotCode = $this->resolveSlotCode($entry->slot_code, $entry->time_slot, $effectiveType);
                /** @var ExamFormTemplate|null $template */
                $template = $templates->get($effectiveType.'|'.$slotCode)
                    ?? $templates->get($normalizedType.'|'.$slotCode);

                [$startTime, $endTime, $durationMinutes] = $this->resolveTimesAndDuration($entry->time_slot, $template);
                $isoDate = $this->parseDateToIso($entry->exam_date);
                if ($isoDate !== null) {
                    $dateValues[] = $isoDate;
                }

                $cohortScope = $this->resolveCohortScope($entry);
                $catalog = $this->upsertCourseCatalog($entry, $cohortScope);

                $roomsEstimated = $entry->student_count !== null && $entry->student_count > 0
                    ? (int) ceil($entry->student_count / self::DEFAULT_ROOM_CAPACITY)
                    : null;

                ExamScheduleObservation::create([
                    'academic_term_id' => $term->id,
                    'exam_form_template_id' => $template?->id,
                    'course_catalog_id' => $catalog->id,
                    'exam_sheet_id' => $sheet?->id ?? $entry->exam_sheet_id,
                    'exam_type' => $normalizedType,
                    'exam_date' => $isoDate,
                    'day_of_week' => $entry->day_of_week ?? ($isoDate ? Carbon::parse($isoDate)->format('l') : null),
                    'slot_code' => $slotCode,
                    'slot_start_time' => $startTime,
                    'slot_end_time' => $endTime,
                    'duration_minutes' => $durationMinutes,
                    'course_code' => $entry->course_code,
                    'equivalent_course_code' => $entry->equivalent_course_code,
                    'course_title' => $entry->course_name ?: $entry->course_code,
                    'student_count' => $entry->student_count,
                    'batch' => $entry->batch,
                    'cohort_scope' => $cohortScope,
                    'major_track' => $entry->major_track,
                    'is_self_study' => (bool) $entry->is_self_study,
                    'is_overlap' => (bool) $entry->is_overlap,
                    'is_merged' => $cohortScope === 'Merged',
                    'teacher_initials' => $entry->teacher_initials,
                    'rooms_estimated' => $roomsEstimated,
                    'is_current' => true,
                    'observed_at' => now(),
                ]);

                $bucketKey = ($isoDate ?? 'unscheduled').'|'.$slotCode;
                if (! isset($slotBuckets[$bucketKey])) {
                    $slotBuckets[$bucketKey] = [
                        'exam_date' => $isoDate,
                        'slot_code' => $slotCode,
                        'courses' => 0,
                        'students' => 0,
                        'regular' => 0,
                        'major' => 0,
                        'self_study' => 0,
                        'cross_listed' => 0,
                        'batches' => [],
                        'max_headcount' => 0,
                    ];
                }

                $slotBuckets[$bucketKey]['courses']++;
                $students = (int) ($entry->student_count ?? 0);
                $slotBuckets[$bucketKey]['students'] += $students;
                if ($students > $slotBuckets[$bucketKey]['max_headcount']) {
                    $slotBuckets[$bucketKey]['max_headcount'] = $students;
                }
                if ($cohortScope === 'Regular') {
                    $slotBuckets[$bucketKey]['regular']++;
                }
                if ($cohortScope === 'Major') {
                    $slotBuckets[$bucketKey]['major']++;
                }
                if ($cohortScope === 'Self Study' || $entry->is_self_study) {
                    $slotBuckets[$bucketKey]['self_study']++;
                }
                if (! empty($entry->equivalent_course_code)) {
                    $slotBuckets[$bucketKey]['cross_listed']++;
                }
                if ($entry->batch !== null) {
                    $slotBuckets[$bucketKey]['batches'][(int) $entry->batch] = true;
                }
            }

            if (! empty($dateValues)) {
                sort($dateValues);
                $term->update([
                    'start_date' => $dateValues[0],
                    'end_date' => $dateValues[count($dateValues) - 1],
                ]);
            }

            foreach ($slotBuckets as $bucket) {
                $uniqueBatches = count($bucket['batches']);
                $roomLoadFactor = $bucket['students'] / self::DEFAULT_ROOM_CAPACITY;
                $complexityScore = round(
                    ($bucket['courses'] * 1.50)
                    + ($uniqueBatches * 1.25)
                    + ($bucket['major'] * 1.35)
                    + ($bucket['self_study'] * 1.15)
                    + ($bucket['cross_listed'] * 1.20)
                    + ($roomLoadFactor * 0.65),
                    2
                );

                ExamSlotTimeSeriesMetric::create([
                    'academic_term_id' => $term->id,
                    'exam_type' => $normalizedType,
                    'exam_date' => $bucket['exam_date'],
                    'slot_code' => $bucket['slot_code'],
                    'concurrent_courses_count' => $bucket['courses'],
                    'total_students' => $bucket['students'],
                    'regular_courses_count' => $bucket['regular'],
                    'major_courses_count' => $bucket['major'],
                    'self_study_courses_count' => $bucket['self_study'],
                    'cross_listed_courses_count' => $bucket['cross_listed'],
                    'unique_batches_count' => $uniqueBatches,
                    'max_course_headcount' => $bucket['max_headcount'],
                    'complexity_score' => $complexityScore,
                ]);
            }
        });
    }

    /**
     * Build JSON-ready complexity analysis & time-series analytics payload.
     *
     * @return array<string, mixed>
     */
    public function buildAnalyticsSummary(?string $examType = null): array
    {
        if (ExamFormTemplate::query()->count() === 0) {
            (new ExamFormTemplateSeeder)->run();
        }

        $templates = ExamFormTemplate::query()
            ->orderBy('exam_type')
            ->orderBy('slot_order')
            ->get()
            ->groupBy('exam_type')
            ->map(static fn (Collection $group): array => $group->values()->all())
            ->all();

        $obsQuery = ExamScheduleObservation::query()->with(['academicTerm', 'courseCatalog']);
        $metricQuery = ExamSlotTimeSeriesMetric::query()->with('academicTerm');

        if ($examType !== null && trim($examType) !== '') {
            $normalized = ExamRoutine::normalizeExamType($examType);
            $obsQuery->where('exam_type', $normalized);
            $metricQuery->where('exam_type', $normalized);
        }

        $observations = $obsQuery->orderBy('exam_date')->orderBy('slot_code')->get();
        $metrics = $metricQuery->orderBy('exam_date')->orderBy('slot_code')->get();

        $scopeBreakdown = $observations
            ->groupBy('cohort_scope')
            ->map(static fn (Collection $rows, string $scope): array => [
                'cohort_scope' => $scope,
                'courses_count' => $rows->count(),
                'total_students' => (int) $rows->sum('student_count'),
            ])
            ->values()
            ->all();

        $courseComplexity = $observations
            ->sortByDesc(static fn (ExamScheduleObservation $o): float => (float) ($o->student_count ?? 0) + (($o->courseCatalog?->complexity_weight ?? 1.0) * 10))
            ->take(20)
            ->map(static fn (ExamScheduleObservation $o): array => [
                'course_code' => $o->course_code,
                'equivalent_course_code' => $o->equivalent_course_code,
                'course_title' => $o->course_title,
                'exam_type' => $o->exam_type,
                'exam_date' => $o->exam_date?->format('Y-m-d'),
                'slot_code' => $o->slot_code,
                'student_count' => $o->student_count,
                'rooms_estimated' => $o->rooms_estimated,
                'cohort_scope' => $o->cohort_scope,
                'course_level' => $o->courseCatalog?->course_level,
                'course_category' => $o->courseCatalog?->course_category,
                'complexity_weight' => $o->courseCatalog?->complexity_weight,
            ])
            ->values()
            ->all();

        $timeSeries = $metrics->map(static fn (ExamSlotTimeSeriesMetric $m): array => [
            'term_code' => $m->academicTerm?->term_code,
            'sequence_index' => $m->academicTerm?->sequence_index,
            'exam_type' => $m->exam_type,
            'exam_date' => $m->exam_date?->format('Y-m-d'),
            'slot_code' => $m->slot_code,
            'concurrent_courses_count' => $m->concurrent_courses_count,
            'total_students' => $m->total_students,
            'regular_courses_count' => $m->regular_courses_count,
            'major_courses_count' => $m->major_courses_count,
            'self_study_courses_count' => $m->self_study_courses_count,
            'cross_listed_courses_count' => $m->cross_listed_courses_count,
            'unique_batches_count' => $m->unique_batches_count,
            'max_course_headcount' => $m->max_course_headcount,
            'complexity_score' => $m->complexity_score,
        ])->values()->all();

        return [
            'memorized_forms' => $templates,
            'active_observations_count' => $observations->count(),
            'cohort_scope_breakdown' => $scopeBreakdown,
            'course_complexity' => $courseComplexity,
            'time_series' => $timeSeries,
        ];
    }

    protected function resolveSlotCode(?string $explicitSlotCode, ?string $timeSlot, string $examType): string
    {
        if ($explicitSlotCode !== null && trim($explicitSlotCode) !== '') {
            return trim($explicitSlotCode);
        }

        $lower = strtolower((string) $timeSlot);
        if (str_contains($lower, 'slot a') || str_starts_with($lower, '08:30') || str_starts_with($lower, '09:00') || str_starts_with($lower, '9:00')) {
            return 'Slot A';
        }
        if (str_contains($lower, 'slot b') || str_starts_with($lower, '11:30') || str_starts_with($lower, '12:00')) {
            return 'Slot B';
        }
        if (str_contains($lower, 'slot c') || str_starts_with($lower, '02:') || str_starts_with($lower, '2:') || str_starts_with($lower, '03:') || str_starts_with($lower, '3:')) {
            return 'Slot C';
        }

        return $examType === 'Overlap' ? 'Slot A' : 'Slot A';
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: int}
     */
    protected function resolveTimesAndDuration(?string $timeSlot, ?ExamFormTemplate $template): array
    {
        if ($timeSlot !== null && preg_match('/(\d{1,2}:\d{2}\s*[ap]m)\s*[-–]\s*(\d{1,2}:\d{2}\s*[ap]m)/i', $timeSlot, $m)) {
            $startTs = strtotime($m[1]);
            $endTs = strtotime($m[2]);
            if ($startTs !== false && $endTs !== false) {
                $diffMin = (int) round(($endTs - $startTs) / 60);
                if ($diffMin <= 0) {
                    $diffMin += 720;
                }

                return [date('H:i:s', $startTs), date('H:i:s', $endTs), max(30, $diffMin)];
            }
        }

        if ($template !== null) {
            return [$template->start_time, $template->end_time, $template->duration_minutes];
        }

        return [null, null, 90];
    }

    protected function parseDateToIso(?string $rawDate): ?string
    {
        $clean = trim((string) $rawDate);
        if ($clean === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $clean, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        try {
            return Carbon::parse($clean)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveCohortScope(ExamRoutine $entry): string
    {
        if (! empty($entry->cohort_scope)) {
            return $entry->cohort_scope;
        }
        if ($entry->is_overlap) {
            return 'Overlap';
        }
        if ($entry->is_self_study) {
            return 'Self Study';
        }
        if (! empty($entry->major_track)) {
            $upper = strtoupper($entry->major_track);
            if (str_contains($upper, 'NON')) {
                return 'Non-Major';
            }
            if ($upper === 'ALL') {
                return 'All';
            }

            return 'Major';
        }

        return $entry->batch !== null ? 'Regular' : 'Unassigned/NA';
    }

    protected function upsertCourseCatalog(ExamRoutine $entry, string $cohortScope): CourseCatalog
    {
        $prefix = CourseCatalog::resolveDomainPrefix($entry->course_code);
        $level = CourseCatalog::resolveCourseLevel($entry->course_code);
        $category = CourseCatalog::resolveCourseCategory($prefix, $cohortScope, $entry->major_track);
        $title = $entry->course_name ?: $entry->course_code;
        $isLabDriven = str_contains(strtolower($title), 'lab') || str_starts_with($entry->course_code, 'SWE');

        $weight = 1.00;
        if ($level >= 400) {
            $weight += 0.35;
        } elseif ($level >= 300) {
            $weight += 0.25;
        } elseif ($level >= 200) {
            $weight += 0.15;
        }
        if (! empty($entry->equivalent_course_code)) {
            $weight += 0.20;
        }
        if ($cohortScope === 'Major') {
            $weight += 0.15;
        }
        if ($isLabDriven) {
            $weight += 0.15;
        }

        /** @var CourseCatalog|null $existing */
        $existing = CourseCatalog::query()->where('course_code', $entry->course_code)->first();
        $equivalents = $existing?->equivalent_codes ?? [];
        if (! empty($entry->equivalent_course_code) && ! in_array($entry->equivalent_course_code, $equivalents, true)) {
            $equivalents[] = $entry->equivalent_course_code;
        }

        return CourseCatalog::query()->updateOrCreate(
            ['course_code' => $entry->course_code],
            [
                'display_code' => trim((string) preg_replace('/^([A-Z]+)(\d+)/', '$1 $2', $entry->course_code)),
                'equivalent_codes' => $equivalents,
                'course_title' => $title,
                'course_level' => $level,
                'domain_prefix' => $prefix,
                'course_category' => $category,
                'is_lab_driven' => $isLabDriven,
                'complexity_weight' => round($weight, 2),
            ]
        );
    }
}
