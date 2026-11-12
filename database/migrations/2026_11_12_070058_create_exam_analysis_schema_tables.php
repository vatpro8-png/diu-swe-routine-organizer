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
        if (! Schema::hasTable('academic_terms')) {
            Schema::create('academic_terms', function (Blueprint $table) {
                $table->id();
                $table->string('term_code', 32)->unique();
                $table->string('semester_name', 20); // Spring, Summer, Fall
                $table->unsignedSmallInteger('academic_year');
                $table->unsignedTinyInteger('term_order'); // 1=Spring, 2=Summer, 3=Fall
                $table->unsignedInteger('sequence_index')->index(); // e.g., 20263
                $table->boolean('is_active')->default(false)->index();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->unique(['semester_name', 'academic_year'], 'uq_academic_term_sem_year');
            });
        }

        if (! Schema::hasTable('exam_form_templates')) {
            Schema::create('exam_form_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('exam_type', 20)->index(); // Midterm, Final, Overlap
                $table->string('slot_code', 20); // Slot A, Slot B, Slot C
                $table->unsignedTinyInteger('slot_order')->default(1);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('time_label', 60);
                $table->unsignedSmallInteger('duration_minutes');
                $table->string('layout_pattern', 40)->default('date_3slot_batch_grid');
                $table->json('cell_schema')->nullable();
                $table->json('instructions_rules')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['exam_type', 'slot_code'], 'uq_form_template_type_slot');
            });
        }

        if (! Schema::hasTable('course_catalog')) {
            Schema::create('course_catalog', function (Blueprint $table) {
                $table->id();
                $table->string('course_code', 30)->unique();
                $table->string('display_code', 40)->nullable();
                $table->json('equivalent_codes')->nullable();
                $table->string('course_title', 200);
                $table->decimal('credit_hours', 3, 1)->nullable();
                $table->unsignedSmallInteger('course_level')->default(100)->index();
                $table->string('domain_prefix', 16)->index(); // SE, SWE, DS, CS, ST, RE, MAT, etc.
                $table->string('course_category', 40)->default('Core')->index();
                $table->boolean('is_lab_driven')->default(false);
                $table->decimal('complexity_weight', 5, 2)->default(1.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exam_schedule_observations')) {
            Schema::create('exam_schedule_observations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
                $table->foreignId('exam_form_template_id')->nullable()->constrained('exam_form_templates')->nullOnDelete();
                $table->foreignId('course_catalog_id')->nullable()->constrained('course_catalog')->nullOnDelete();
                $table->foreignId('exam_sheet_id')->nullable()->constrained('exam_sheets')->nullOnDelete();
                $table->string('exam_type', 20)->index(); // Midterm, Final, Overlap
                $table->date('exam_date')->nullable()->index();
                $table->string('day_of_week', 20)->nullable();
                $table->string('slot_code', 20)->default('Slot A');
                $table->time('slot_start_time')->nullable();
                $table->time('slot_end_time')->nullable();
                $table->unsignedSmallInteger('duration_minutes')->default(90);
                $table->string('course_code', 30)->index();
                $table->string('equivalent_course_code', 30)->nullable();
                $table->string('course_title', 200);
                $table->unsignedInteger('student_count')->nullable();
                $table->unsignedSmallInteger('batch')->nullable()->index();
                $table->string('cohort_scope', 30)->default('Regular')->index(); // Regular, Major, Non-Major, All, Self Study, Unassigned/NA, Overlap, Merged
                $table->string('major_track', 30)->nullable();
                $table->boolean('is_self_study')->default(false);
                $table->boolean('is_overlap')->default(false);
                $table->boolean('is_merged')->default(false);
                $table->string('teacher_initials', 20)->nullable();
                $table->unsignedSmallInteger('rooms_estimated')->nullable();
                $table->boolean('is_current')->default(true)->index();
                $table->timestamp('observed_at')->nullable();
                $table->timestamps();

                $table->index(['academic_term_id', 'exam_type', 'exam_date', 'slot_code'], 'idx_ts_term_type_date_slot');
                $table->index(['course_code', 'academic_term_id', 'exam_type'], 'idx_complexity_course_term');
                $table->index(['batch', 'exam_date'], 'idx_ts_batch_date');
            });
        }

        if (! Schema::hasTable('exam_slot_time_series_metrics')) {
            Schema::create('exam_slot_time_series_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
                $table->string('exam_type', 20)->index();
                $table->date('exam_date')->nullable()->index();
                $table->string('slot_code', 20);
                $table->unsignedInteger('concurrent_courses_count')->default(0);
                $table->unsignedInteger('total_students')->default(0);
                $table->unsignedInteger('regular_courses_count')->default(0);
                $table->unsignedInteger('major_courses_count')->default(0);
                $table->unsignedInteger('self_study_courses_count')->default(0);
                $table->unsignedInteger('cross_listed_courses_count')->default(0);
                $table->unsignedInteger('unique_batches_count')->default(0);
                $table->unsignedInteger('max_course_headcount')->default(0);
                $table->decimal('complexity_score', 8, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['academic_term_id', 'exam_type', 'exam_date', 'slot_code'], 'uq_ts_metrics_slot');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_slot_time_series_metrics');
        Schema::dropIfExists('exam_schedule_observations');
        Schema::dropIfExists('course_catalog');
        Schema::dropIfExists('exam_form_templates');
        Schema::dropIfExists('academic_terms');
    }
};
