<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamFormTemplate extends Model
{
    public const DEPARTMENT_INSTRUCTIONS = [
        'No student will be allowed to sit for examination without clearing their payment to the university or showing proper document.',
        'Each student is instructed to collect his/her Admit Card or Payment Document from Accounts Section and bring it to examination hall. No student will be allowed to sit for Examination without Admit Card.',
        'It is mandatory to attend the examination according to the sitting arrangement; if not, students will be strictly redirected to follow the sitting arrangement.',
        "Students are strongly advised to write their teachers' initials (for respective courses & sections) correctly to avoid inconvenient circumstances.",
        'ID card is mandatory to enter into exam hall.',
        'Hang your student ID card with DIU Ribbon with clear visibility.',
    ];

    protected $table = 'exam_form_templates';

    protected $fillable = [
        'code',
        'exam_type',
        'slot_code',
        'slot_order',
        'start_time',
        'end_time',
        'time_label',
        'duration_minutes',
        'layout_pattern',
        'cell_schema',
        'instructions_rules',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_order' => 'integer',
            'duration_minutes' => 'integer',
            'cell_schema' => 'array',
            'instructions_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function examObservations(): HasMany
    {
        return $this->hasMany(ExamScheduleObservation::class, 'exam_form_template_id');
    }
}
