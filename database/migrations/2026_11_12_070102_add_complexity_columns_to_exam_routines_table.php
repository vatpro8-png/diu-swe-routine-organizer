<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('exam_routines')) {
            Schema::table('exam_routines', function (Blueprint $table) {
                if (! Schema::hasColumn('exam_routines', 'slot_code')) {
                    $table->string('slot_code', 20)->nullable()->after('day_of_week');
                }
                if (! Schema::hasColumn('exam_routines', 'equivalent_course_code')) {
                    $table->string('equivalent_course_code', 30)->nullable()->after('course_code');
                }
                if (! Schema::hasColumn('exam_routines', 'student_count')) {
                    $table->unsignedInteger('student_count')->nullable()->after('course_name');
                }
                if (! Schema::hasColumn('exam_routines', 'cohort_scope')) {
                    $table->string('cohort_scope', 30)->nullable()->after('major_track');
                }
                if (! Schema::hasColumn('exam_routines', 'is_self_study')) {
                    $table->boolean('is_self_study')->default(false)->after('cohort_scope');
                }
                if (! Schema::hasColumn('exam_routines', 'is_overlap')) {
                    $table->boolean('is_overlap')->default(false)->after('is_self_study');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('exam_routines')) {
            Schema::table('exam_routines', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn('exam_routines', 'slot_code') ? 'slot_code' : null,
                    Schema::hasColumn('exam_routines', 'equivalent_course_code') ? 'equivalent_course_code' : null,
                    Schema::hasColumn('exam_routines', 'student_count') ? 'student_count' : null,
                    Schema::hasColumn('exam_routines', 'cohort_scope') ? 'cohort_scope' : null,
                    Schema::hasColumn('exam_routines', 'is_self_study') ? 'is_self_study' : null,
                    Schema::hasColumn('exam_routines', 'is_overlap') ? 'is_overlap' : null,
                ]));

                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
