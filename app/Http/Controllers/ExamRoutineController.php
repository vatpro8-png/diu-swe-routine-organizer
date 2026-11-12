<?php

namespace App\Http\Controllers;

use App\Models\AcademicRoutine;
use App\Models\ExamRoutine;
use App\Models\ExamSheet;
use App\Services\CourseIntegrationService;
use App\Services\ExamAnalyticsService;
use App\Services\ExamSheetParserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ExamRoutineController extends Controller
{
    /**
     * Upload and parse an exam sheet (.xlsx or .csv) for a distinct exam type (Midterm or Final).
     */
    public function uploadSheet(Request $request, ExamSheetParserService $parser, ExamAnalyticsService $analytics): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => ['required', Rule::in(ExamRoutine::EXAM_TYPES)],
            'exam_sheet' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            'sheet_name' => ['nullable', 'string', 'max:120'],
        ]);

        $examType = ExamRoutine::normalizeExamType($validated['exam_type']);
        $file = $request->file('exam_sheet');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $originalName = $file->getClientOriginalName();
        $typeSlug = strtolower($examType);

        $storedPath = $file->store("exam-sheets/{$typeSlug}", 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        try {
            $parsed = $parser->parseFile($fullPath, $extension, $examType, $validated['sheet_name'] ?? null);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($storedPath);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to parse exam sheet: '.$e->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType])
                ->withErrors(['exam_sheet' => 'Could not parse the uploaded exam sheet: '.$e->getMessage()]);
        }

        $oldPaths = ExamSheet::where('exam_type', $examType)->pluck('stored_path')->all();

        $sheet = DB::transaction(function () use ($examType, $originalName, $storedPath, $extension, $parsed): ExamSheet {
            ExamRoutine::where('exam_type', $examType)->delete();
            ExamSheet::where('exam_type', $examType)->delete();

            $sheet = ExamSheet::create([
                'exam_type' => $examType,
                'original_filename' => $originalName,
                'stored_path' => $storedPath,
                'file_extension' => $extension,
                'active_sheet_name' => $parsed['active_sheet'],
                'available_sheets' => array_map(
                    static fn (array $s): string => $s['name'],
                    $parsed['sheets']
                ),
                'headers' => $parsed['headers'],
                'raw_rows' => $parsed['raw_rows'],
                'row_count' => count($parsed['entries']),
                'uploaded_at' => now(),
            ]);

            $this->insertEntries($sheet, $examType, $parsed['entries']);

            return $sheet;
        });

        $analytics->syncFromExamType($examType, $sheet);

        foreach ($oldPaths as $oldPath) {
            if ($oldPath !== $storedPath && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }
        }

        $entryCount = count($parsed['entries']);
        $rawCount = count($parsed['raw_rows']);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "{$examType} exam sheet '{$originalName}' loaded ({$entryCount} exam entries, {$rawCount} sheet rows).",
                'payload' => [
                    'sheet' => $sheet,
                    'entries_count' => $entryCount,
                ],
            ]);
        }

        return redirect()
            ->route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType])
            ->with('status', "{$examType} exam sheet '{$originalName}' loaded ({$entryCount} exam slots parsed from '{$parsed['active_sheet']}').");
    }

    /**
     * Switch the active worksheet inside an uploaded multi-sheet .xlsx exam workbook.
     */
    public function selectSheet(Request $request, ExamSheetParserService $parser, ExamAnalyticsService $analytics): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => ['required', Rule::in(ExamRoutine::EXAM_TYPES)],
            'sheet_name' => ['required', 'string', 'max:120'],
        ]);

        $examType = ExamRoutine::normalizeExamType($validated['exam_type']);
        $sheet = ExamSheet::where('exam_type', $examType)->latest('id')->first();

        if (! $sheet || ! Storage::disk('local')->exists($sheet->stored_path)) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'No uploaded exam sheet found for this exam type.'], 404);
            }

            return redirect()
                ->route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType])
                ->withErrors(['exam_sheet' => "No {$examType} exam sheet file is available to switch worksheets."]);
        }

        $fullPath = Storage::disk('local')->path($sheet->stored_path);
        $parsed = $parser->parseFile($fullPath, $sheet->file_extension, $examType, $validated['sheet_name']);

        DB::transaction(function () use ($sheet, $examType, $parsed): void {
            ExamRoutine::where('exam_type', $examType)->delete();

            $sheet->update([
                'active_sheet_name' => $parsed['active_sheet'],
                'headers' => $parsed['headers'],
                'raw_rows' => $parsed['raw_rows'],
                'row_count' => count($parsed['entries']),
            ]);

            $this->insertEntries($sheet, $examType, $parsed['entries']);
        });

        $analytics->syncFromExamType($examType, $sheet);

        $entryCount = count($parsed['entries']);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Switched {$examType} worksheet to '{$parsed['active_sheet']}' ({$entryCount} exam slots).",
            ]);
        }

        return redirect()
            ->route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType])
            ->with('status', "Switched {$examType} exam sheet tab to '{$parsed['active_sheet']}' ({$entryCount} entries).");
    }

    /**
     * Store an individual exam routine entry manually.
     */
    public function storeSlot(Request $request, ExamAnalyticsService $analytics): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'semester' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'string', 'max:4'],
            'exam_type' => ['required', Rule::in(ExamRoutine::EXAM_TYPES)],
            'exam_date' => ['nullable', 'string', 'max:40'],
            'day_of_week' => ['nullable', 'string', 'max:20'],
            'slot_code' => ['nullable', 'string', 'max:20'],
            'time_slot' => ['required', 'string', 'max:60'],
            'batch' => ['nullable', 'integer', 'min:1', 'max:99'],
            'section' => ['nullable', 'string', 'max:20'],
            'major_track' => ['nullable', 'string', 'max:30'],
            'cohort_scope' => ['nullable', 'string', 'max:30'],
            'is_self_study' => ['nullable', 'boolean'],
            'is_overlap' => ['nullable', 'boolean'],
            'course_code' => ['required', 'string', 'max:30'],
            'equivalent_course_code' => ['nullable', 'string', 'max:30'],
            'course_name' => ['nullable', 'string', 'max:180'],
            'student_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'room_no' => ['nullable', 'string', 'max:40'],
            'building' => ['nullable', 'string', 'max:30'],
            'teacher_initials' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $examType = ExamRoutine::normalizeExamType($validated['exam_type']);
        $courseCode = strtoupper(str_replace(' ', '', trim($validated['course_code'])));
        $equivCode = ! empty($validated['equivalent_course_code'])
            ? strtoupper(str_replace(' ', '', trim($validated['equivalent_course_code'])))
            : null;
        $courseName = trim((string) ($validated['course_name'] ?? ''));
        if ($courseName === '') {
            $courseName = CourseIntegrationService::resolveCourseName($courseCode) ?: $courseCode;
        }

        $roomNo = trim((string) ($validated['room_no'] ?? ''));
        $building = trim((string) ($validated['building'] ?? ''));
        if ($building === '' && $roomNo !== '') {
            $building = AcademicRoutine::resolveBuilding($roomNo);
        }

        $dayOfWeek = trim((string) ($validated['day_of_week'] ?? ''));
        if ($dayOfWeek === '' && ! empty($validated['exam_date'])) {
            $ts = strtotime($validated['exam_date']);
            if ($ts !== false) {
                $dayOfWeek = date('l', $ts);
            }
        }

        $majorTrack = ! empty($validated['major_track']) ? strtoupper(trim($validated['major_track'])) : null;
        $isSelfStudy = (bool) ($validated['is_self_study'] ?? false);
        $isOverlap = (bool) ($validated['is_overlap'] ?? false);
        $cohortScope = trim((string) ($validated['cohort_scope'] ?? ''));
        if ($cohortScope === '') {
            $cohortScope = $isOverlap ? 'Overlap' : ($isSelfStudy ? 'Self Study' : ($majorTrack !== null ? 'Major' : (($validated['batch'] ?? null) !== null ? 'Regular' : 'Unassigned/NA')));
        }

        $entry = ExamRoutine::create([
            'semester' => ! empty($validated['semester']) ? ucfirst(strtolower(trim($validated['semester']))) : 'Fall',
            'year' => ! empty($validated['year']) ? trim($validated['year']) : '2026',
            'exam_type' => $examType,
            'exam_date' => $validated['exam_date'] ?? null,
            'day_of_week' => $dayOfWeek !== '' ? $dayOfWeek : null,
            'slot_code' => $validated['slot_code'] ?? null,
            'time_slot' => trim($validated['time_slot']),
            'batch' => $validated['batch'] ?? null,
            'section' => ! empty($validated['section']) ? strtoupper(trim($validated['section'])) : null,
            'major_track' => $majorTrack,
            'cohort_scope' => $cohortScope,
            'is_self_study' => $isSelfStudy,
            'is_overlap' => $isOverlap,
            'course_code' => $courseCode,
            'equivalent_course_code' => $equivCode,
            'course_name' => $courseName,
            'student_count' => $validated['student_count'] ?? null,
            'room_no' => $roomNo !== '' ? $roomNo : null,
            'building' => $building !== '' ? $building : null,
            'teacher_initials' => ! empty($validated['teacher_initials']) ? strtoupper(trim($validated['teacher_initials'])) : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $analytics->syncFromExamType($examType);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'payload' => $entry], 201);
        }

        return redirect()
            ->route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType])
            ->with('status', "Added {$courseCode} to {$examType} exam routine.");
    }

    /**
     * Clear the uploaded exam sheet and exam entries for a distinct exam type.
     */
    public function clear(Request $request, ExamAnalyticsService $analytics): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => ['required', Rule::in(ExamRoutine::EXAM_TYPES)],
        ]);

        $examType = ExamRoutine::normalizeExamType($validated['exam_type']);
        $paths = ExamSheet::where('exam_type', $examType)->pluck('stored_path')->all();

        DB::transaction(function () use ($examType): void {
            ExamRoutine::where('exam_type', $examType)->delete();
            ExamSheet::where('exam_type', $examType)->delete();
        });

        $analytics->syncFromExamType($examType);

        foreach ($paths as $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => "{$examType} exam routine cleared."]);
        }

        return redirect()
            ->route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType])
            ->with('status', "{$examType} exam routine and sheet cleared.");
    }

    /**
     * Download a sample CSV exam sheet template for Midterm or Final.
     */
    public function downloadTemplate(Request $request, ExamSheetParserService $parser): Response
    {
        $examType = ExamRoutine::normalizeExamType($request->input('exam_type', 'Midterm'));
        $csv = $parser->generateSampleCsv($examType);
        $filename = "DIU_SWE_{$examType}_Exam_Routine_Template.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export filtered exam routine rows for a distinct exam type as CSV.
     */
    public function exportCsv(Request $request): Response
    {
        $examType = ExamRoutine::normalizeExamType($request->input('exam_type', 'Midterm'));
        $batch = $request->filled('exam_batch') ? (int) $request->input('exam_batch') : null;
        $search = $request->filled('exam_search') ? trim((string) $request->input('exam_search')) : null;

        $rows = $this->filteredQuery($examType, $batch, $search)->get();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            "DIU SWE {$examType} Exam Routine (Fall 2026)",
        ], escape: '\\');
        fputcsv($stream, [], escape: '\\');
        fputcsv($stream, [
            'SL',
            'Exam Type',
            'Exam Date',
            'Day',
            'Time Slot',
            'Batch',
            'Section',
            'Track',
            'Course Code',
            'Course Title',
            'Room No',
            'Building',
            'Teacher Initials',
            'Notes',
        ], escape: '\\');

        foreach ($rows as $idx => $r) {
            fputcsv($stream, [
                $idx + 1,
                $r->exam_type,
                $r->exam_date ?? 'TBA',
                $r->day_of_week ?? 'TBA',
                $r->time_slot,
                $r->batch ?? 'All',
                $r->section ?? 'All',
                $r->major_track ?? 'Core',
                $r->course_code,
                $r->course_name ?? $r->course_code,
                $r->room_no ?? 'TBA',
                $r->building ?? 'TBA',
                $r->teacher_initials ?? 'TBA',
                $r->notes ?? '',
            ], escape: '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $batchSuffix = $batch ? "_Batch_{$batch}" : '';
        $filename = "DIU_SWE_{$examType}_Exam_Routine{$batchSuffix}.csv";

        return response($csv === false ? '' : $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export filtered exam routine entries as RFC-5545 iCalendar (.ics).
     */
    public function exportIcs(Request $request): Response
    {
        $examType = ExamRoutine::normalizeExamType($request->input('exam_type', 'Midterm'));
        $batch = $request->filled('exam_batch') ? (int) $request->input('exam_batch') : null;
        $search = $request->filled('exam_search') ? trim((string) $request->input('exam_search')) : null;

        $rows = $this->filteredQuery($examType, $batch, $search)->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Daffodil International University//DIU SWE Exam Routine//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:DIU SWE {$examType} Exam Routine",
            'X-WR-TIMEZONE:Asia/Dhaka',
        ];

        $fallbackBase = $examType === 'Final' ? '2026-12-12' : '2026-10-24';

        foreach ($rows as $idx => $r) {
            $parsedDate = $r->exam_date ? strtotime($r->exam_date) : false;
            $eventDate = $parsedDate !== false ? date('Ymd', $parsedDate) : date('Ymd', strtotime($fallbackBase." +{$idx} days"));

            [$startTime, $endTime] = $this->parseExamSlotTimes($r->time_slot);
            $dtStart = $eventDate.'T'.str_replace(':', '', $startTime);
            $dtEnd = $eventDate.'T'.str_replace(':', '', $endTime);

            $uid = "diu-swe-exam-{$examType}-{$r->id}-{$idx}@diu.edu.bd";
            $batchInfo = $r->batch ? "Batch {$r->batch}".($r->section ? "-{$r->section}" : '') : 'SWE';
            $summary = "[{$examType}] {$r->course_code}: ".($r->course_name ?: $r->course_code)." ({$batchInfo})";
            $location = $r->room_no ? "Room {$r->room_no}".($r->building ? ", {$r->building}" : '').', Daffodil Smart City' : 'Daffodil Smart City';
            $description = "Exam Type: {$examType}\\nCourse: {$r->course_code} - ".($r->course_name ?: $r->course_code)."\\nSlot: {$r->time_slot}\\nRoom: ".($r->room_no ?: 'TBA');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = 'DTSTAMP:'.gmdate('Ymd\THis\Z');
            $lines[] = "DTSTART;TZID=Asia/Dhaka:{$dtStart}";
            $lines[] = "DTEND;TZID=Asia/Dhaka:{$dtEnd}";
            $lines[] = "SUMMARY:{$summary}";
            $lines[] = "LOCATION:{$location}";
            $lines[] = "DESCRIPTION:{$description}";
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $ics = implode("\r\n", $lines)."\r\n";

        $filename = "DIU_SWE_{$examType}_Exam_Routine.ics";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * RESTful JSON endpoint for Exam Routine by distinct exam type.
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $examType = ExamRoutine::normalizeExamType($request->input('exam_type', 'Midterm'));
        $batch = $request->filled('batch') ? (int) $request->input('batch') : null;
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $entries = $this->filteredQuery($examType, $batch, $search)->get();
        $sheet = ExamSheet::where('exam_type', $examType)->latest('id')->first();

        return response()->json([
            'status' => 'success',
            'feature' => 'exam_routine',
            'meta' => [
                'exam_type' => $examType,
                'batch' => $batch,
                'total_entries' => $entries->count(),
                'sheet' => $sheet ? [
                    'original_filename' => $sheet->original_filename,
                    'active_sheet_name' => $sheet->active_sheet_name,
                    'available_sheets' => $sheet->available_sheets,
                    'headers' => $sheet->headers,
                ] : null,
            ],
            'payload' => $entries,
        ]);
    }

    /**
     * Course complexity & time-series analytics REST endpoint.
     */
    public function apiAnalytics(Request $request, ExamAnalyticsService $analytics): JsonResponse
    {
        $examType = $request->filled('exam_type') ? (string) $request->input('exam_type') : null;

        return response()->json([
            'status' => 'success',
            'feature' => 'exam_complexity_and_time_series_analytics',
            'payload' => $analytics->buildAnalyticsSummary($examType),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    protected function insertEntries(ExamSheet $sheet, string $examType, array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        $now = now();
        $payload = [];
        foreach ($entries as $entry) {
            $payload[] = [
                'exam_sheet_id' => $sheet->id,
                'semester' => $entry['semester'] ?? 'Fall',
                'year' => $entry['year'] ?? '2026',
                'exam_type' => $examType,
                'exam_date' => $entry['exam_date'] ?? null,
                'day_of_week' => $entry['day_of_week'] ?? null,
                'slot_code' => $entry['slot_code'] ?? null,
                'time_slot' => $entry['time_slot'] ?? '09:00 AM - 11:00 AM',
                'batch' => $entry['batch'] ?? null,
                'section' => $entry['section'] ?? null,
                'major_track' => $entry['major_track'] ?? null,
                'cohort_scope' => $entry['cohort_scope'] ?? null,
                'is_self_study' => (bool) ($entry['is_self_study'] ?? false),
                'is_overlap' => (bool) ($entry['is_overlap'] ?? false),
                'course_code' => $entry['course_code'] ?? 'EXAM',
                'equivalent_course_code' => $entry['equivalent_course_code'] ?? null,
                'course_name' => $entry['course_name'] ?? null,
                'student_count' => $entry['student_count'] ?? null,
                'room_no' => $entry['room_no'] ?? null,
                'building' => $entry['building'] ?? null,
                'teacher_initials' => $entry['teacher_initials'] ?? null,
                'notes' => $entry['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($payload, 200) as $chunk) {
            ExamRoutine::insert($chunk);
        }
    }

    protected function filteredQuery(string $examType, ?int $batch, ?string $search): Builder
    {
        $query = ExamRoutine::query()->forExamType($examType);

        if ($batch !== null && $batch > 0) {
            $query->where('batch', $batch);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('course_code', 'LIKE', $like)
                    ->orWhere('course_name', 'LIKE', $like)
                    ->orWhere('room_no', 'LIKE', $like)
                    ->orWhere('section', 'LIKE', $like)
                    ->orWhere('teacher_initials', 'LIKE', $like)
                    ->orWhere('exam_date', 'LIKE', $like);
            });
        }

        return $query->orderBy('exam_date')->orderBy('time_slot')->orderBy('batch')->orderBy('course_code');
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseExamSlotTimes(string $slot): array
    {
        $parts = explode('-', $slot);
        if (count($parts) === 2) {
            $start = strtotime(trim($parts[0]));
            $end = strtotime(trim($parts[1]));
            if ($start !== false && $end !== false) {
                return [date('H:i:s', $start), date('H:i:s', $end)];
            }
        }

        return ['09:00:00', '11:00:00'];
    }
}
