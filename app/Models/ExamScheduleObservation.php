<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamScheduleObservation extends Model
{
    protected $table = 'exam_schedule_observations';

    protected $fillable = [
        'academic_term_id',
        'exam_form_template_id',
        'course_catalog_id',
        'exam_sheet_id',
        'exam_type',
        'exam_date',
        'day_of_week',
        'slot_code',
        'slot_start_time',
        'slot_end_time',
        'duration_minutes',
        'course_code',
        'equivalent_course_code',
        'course_title',
        'student_count',
        'batch',
        'cohort_scope',
        'major_track',
        'is_self_study',
        'is_overlap',
        'is_merged',
        'teacher_initials',
        'rooms_estimated',
        'is_current',
        'observed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'duration_minutes' => 'integer',
            'student_count' => 'integer',
            'batch' => 'integer',
            'is_self_study' => 'boolean',
            'is_overlap' => 'boolean',
            'is_merged' => 'boolean',
            'rooms_estimated' => 'integer',
            'is_current' => 'boolean',
            'observed_at' => 'datetime',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(ExamFormTemplate::class, 'exam_form_template_id');
    }

    public function courseCatalog(): BelongsTo
    {
        return $this->belongsTo(CourseCatalog::class, 'course_catalog_id');
    }

    public function examSheet(): BelongsTo
    {
        return $this->belongsTo(ExamSheet::class, 'exam_sheet_id');
    }
}
