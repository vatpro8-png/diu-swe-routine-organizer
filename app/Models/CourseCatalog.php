<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCatalog extends Model
{
    protected $table = 'course_catalog';

    protected $fillable = [
        'course_code',
        'display_code',
        'equivalent_codes',
        'course_title',
        'credit_hours',
        'course_level',
        'domain_prefix',
        'course_category',
        'is_lab_driven',
        'complexity_weight',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equivalent_codes' => 'array',
            'credit_hours' => 'float',
            'course_level' => 'integer',
            'is_lab_driven' => 'boolean',
            'complexity_weight' => 'float',
        ];
    }

    public function examObservations(): HasMany
    {
        return $this->hasMany(ExamScheduleObservation::class, 'course_catalog_id');
    }

    public static function resolveDomainPrefix(string $courseCode): string
    {
        if (preg_match('/^([A-Z]+)/', strtoupper(trim($courseCode)), $matches)) {
            return $matches[1];
        }

        return 'SE';
    }

    public static function resolveCourseLevel(string $courseCode): int
    {
        if (preg_match('/(\d{3})/', $courseCode, $matches)) {
            $num = (int) $matches[1];

            return (int) (floor($num / 100) * 100);
        }

        return 100;
    }

    public static function resolveCourseCategory(string $prefix, ?string $cohortScope = null, ?string $majorTrack = null): string
    {
        if ($cohortScope === 'Self Study' || $prefix === 'SWE') {
            return 'Self-Study / Legacy Curriculum';
        }
        if (in_array($prefix, ['DS', 'CS', 'ST', 'RE'], true) || ($majorTrack !== null && str_contains($majorTrack, 'MAJOR') && ! str_contains($majorTrack, 'NON'))) {
            return 'Specialization Major';
        }
        if ($cohortScope === 'Non-Major' || ($majorTrack !== null && str_contains($majorTrack, 'NON'))) {
            return 'Non-Major Elective';
        }
        if (in_array($prefix, ['MAT', 'MATH', 'PHY', 'STA'], true)) {
            return 'Mathematics & Basic Science';
        }
        if (in_array($prefix, ['ENG', 'GE', 'BNS', 'AOL', 'EMP'], true)) {
            return 'General Education & Skills';
        }

        return 'Core Software Engineering';
    }
}
