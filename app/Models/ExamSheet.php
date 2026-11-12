<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSheet extends Model
{
    public const EXAM_TYPES = ['Midterm', 'Final'];

    protected $table = 'exam_sheets';

    protected $fillable = [
        'exam_type',
        'original_filename',
        'stored_path',
        'file_extension',
        'active_sheet_name',
        'available_sheets',
        'headers',
        'raw_rows',
        'row_count',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_sheets' => 'array',
            'headers' => 'array',
            'raw_rows' => 'array',
            'row_count' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function examRoutines(): HasMany
    {
        return $this->hasMany(ExamRoutine::class, 'exam_sheet_id');
    }
}
