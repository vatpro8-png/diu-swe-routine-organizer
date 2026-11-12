<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSlotTimeSeriesMetric extends Model
{
    protected $table = 'exam_slot_time_series_metrics';

    protected $fillable = [
        'academic_term_id',
        'exam_type',
        'exam_date',
        'slot_code',
        'concurrent_courses_count',
        'total_students',
        'regular_courses_count',
        'major_courses_count',
        'self_study_courses_count',
        'cross_listed_courses_count',
        'unique_batches_count',
        'max_course_headcount',
        'complexity_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'concurrent_courses_count' => 'integer',
            'total_students' => 'integer',
            'regular_courses_count' => 'integer',
            'major_courses_count' => 'integer',
            'self_study_courses_count' => 'integer',
            'cross_listed_courses_count' => 'integer',
            'unique_batches_count' => 'integer',
            'max_course_headcount' => 'integer',
            'complexity_score' => 'float',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }
}
