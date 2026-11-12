<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    public const SEMESTER_ORDER = [
        'Spring' => 1,
        'Summer' => 2,
        'Fall' => 3,
    ];

    protected $table = 'academic_terms';

    protected $fillable = [
        'term_code',
        'semester_name',
        'academic_year',
        'term_order',
        'sequence_index',
        'is_active',
        'start_date',
        'end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'academic_year' => 'integer',
            'term_order' => 'integer',
            'sequence_index' => 'integer',
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function examObservations(): HasMany
    {
        return $this->hasMany(ExamScheduleObservation::class, 'academic_term_id');
    }

    public function slotMetrics(): HasMany
    {
        return $this->hasMany(ExamSlotTimeSeriesMetric::class, 'academic_term_id');
    }

    public static function resolveOrCreate(string $semesterName, int $year, bool $activate = false): self
    {
        $cleanSemester = ucfirst(strtolower(trim($semesterName)));
        if (! isset(self::SEMESTER_ORDER[$cleanSemester])) {
            $cleanSemester = 'Fall';
        }

        $order = self::SEMESTER_ORDER[$cleanSemester];
        $sequence = ($year * 10) + $order;
        $termCode = "{$year}-{$order}-{$cleanSemester}";

        if ($activate) {
            self::query()->where('is_active', true)->update(['is_active' => false]);
        }

        $term = self::query()->firstOrCreate(
            ['term_code' => $termCode],
            [
                'semester_name' => $cleanSemester,
                'academic_year' => $year,
                'term_order' => $order,
                'sequence_index' => $sequence,
                'is_active' => $activate,
            ]
        );

        if ($activate && ! $term->is_active) {
            $term->update(['is_active' => true]);
        }

        return $term;
    }
}
