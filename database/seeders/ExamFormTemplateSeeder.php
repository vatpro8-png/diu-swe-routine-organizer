<?php

namespace Database\Seeders;

use App\Models\ExamFormTemplate;
use Illuminate\Database\Seeder;

class ExamFormTemplateSeeder extends Seeder
{
    /**
     * Memorize the official DIU SWE exam routine forms ONLY (slots, duration, grid structure, rules)
     * derived from department Midterm, Overlap, and Final forms without storing past schedule rows.
     */
    public function run(): void
    {
        $cellSchema = [
            'left_column' => ['course_codes', 'course_title', 'student_count'],
            'right_column' => ['batch', 'major_track', 'cohort_scope', 'teacher_initials', 'merge_note'],
            'supported_tracks' => ['DS Major', 'CS Major', 'ST Major', 'RE Major', 'Non-Major', 'ALL', 'Self Study', 'N/A', 'Overlap'],
        ];

        $templates = [
            // Midterm Examination Form (90 min slots — Fall 2026 standard)
            ['Midterm', 'Slot A', 1, '09:00:00', '10:30:00', '09:00 AM - 10:30 AM', 90],
            ['Midterm', 'Slot B', 2, '11:30:00', '13:00:00', '11:30 AM - 01:00 PM', 90],
            ['Midterm', 'Slot C', 3, '14:00:00', '15:30:00', '02:00 PM - 03:30 PM', 90],

            // Overlap / Clash Resolution Form (90 min slots at end of exam cycle)
            ['Overlap', 'Slot A', 1, '08:30:00', '10:00:00', '08:30 AM - 10:00 AM', 90],
            ['Overlap', 'Slot B', 2, '11:30:00', '13:00:00', '11:30 AM - 01:00 PM', 90],
            ['Overlap', 'Slot C', 3, '14:30:00', '16:00:00', '02:30 PM - 04:00 PM', 90],

            // Final Examination Form (120 min slots — Summer 2026 standard)
            ['Final', 'Slot A', 1, '09:00:00', '11:00:00', '09:00 AM - 11:00 AM', 120],
            ['Final', 'Slot B', 2, '12:00:00', '14:00:00', '12:00 PM - 02:00 PM', 120],
            ['Final', 'Slot C', 3, '15:00:00', '17:00:00', '03:00 PM - 05:00 PM', 120],
        ];

        foreach ($templates as [$examType, $slotCode, $order, $start, $end, $label, $duration]) {
            $slug = strtolower($examType.'_'.str_replace(' ', '_', $slotCode)."_{$duration}m");

            ExamFormTemplate::updateOrCreate(
                ['code' => $slug],
                [
                    'exam_type' => $examType,
                    'slot_code' => $slotCode,
                    'slot_order' => $order,
                    'start_time' => $start,
                    'end_time' => $end,
                    'time_label' => $label,
                    'duration_minutes' => $duration,
                    'layout_pattern' => 'date_3slot_batch_grid',
                    'cell_schema' => $cellSchema,
                    'instructions_rules' => ExamFormTemplate::DEPARTMENT_INSTRUCTIONS,
                    'is_active' => true,
                ]
            );
        }
    }
}
