<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRoutine extends Model
{
    public const EXAM_TYPES = ['Midterm', 'Final'];

    public const DEFAULT_SLOTS = [
        'Midterm' => [
            '09:00 AM - 10:30 AM',
            '11:30 AM - 01:00 PM',
            '02:00 PM - 03:30 PM',
        ],
        'Final' => [
            '09:00 AM - 11:00 AM',
            '12:00 PM - 02:00 PM',
            '03:00 PM - 05:00 PM',
        ],
        'Overlap' => [
            '08:30 AM - 10:00 AM',
            '11:30 AM - 01:00 PM',
            '02:30 PM - 04:00 PM',
        ],
    ];

    protected $table = 'exam_routines';

    protected $fillable = [
        'exam_sheet_id',
        'semester',
        'year',
        'exam_type',
        'exam_date',
        'day_of_week',
        'slot_code',
        'time_slot',
        'batch',
        'section',
        'major_track',
        'cohort_scope',
        'is_self_study',
        'is_overlap',
        'course_code',
        'equivalent_course_code',
        'course_name',
        'student_count',
        'room_no',
        'building',
        'teacher_initials',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'batch' => 'integer',
            'student_count' => 'integer',
            'is_self_study' => 'boolean',
            'is_overlap' => 'boolean',
        ];
    }

    public function examSheet(): BelongsTo
    {
        return $this->belongsTo(ExamSheet::class, 'exam_sheet_id');
    }

    public function scopeForExamType(Builder $query, string $examType): Builder
    {
        return $query->where('exam_type', self::normalizeExamType($examType));
    }

    public static function normalizeExamType(?string $examType): string
    {
        $clean = strtolower(trim((string) $examType));

        return $clean === 'final' ? 'Final' : 'Midterm';
    }
}
