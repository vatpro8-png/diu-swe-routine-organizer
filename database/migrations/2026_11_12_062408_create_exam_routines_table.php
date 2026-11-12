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
        if (! Schema::hasTable('exam_routines')) {
            Schema::create('exam_routines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_sheet_id')->nullable()->constrained('exam_sheets')->nullOnDelete();
                $table->string('semester', 20)->default('Fall');
                $table->string('year', 4)->default('2026');
                $table->string('exam_type', 20)->index();
                $table->string('exam_date', 40)->nullable();
                $table->string('day_of_week', 20)->nullable();
                $table->string('time_slot', 60)->default('09:00 AM - 11:00 AM');
                $table->integer('batch')->nullable()->index();
                $table->string('section', 20)->nullable();
                $table->string('major_track', 20)->nullable();
                $table->string('course_code', 30)->index();
                $table->string('course_name', 180)->nullable();
                $table->string('room_no', 40)->nullable();
                $table->string('building', 30)->nullable();
                $table->string('teacher_initials', 20)->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index(['exam_type', 'batch', 'course_code'], 'idx_exam_type_batch_course');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_routines');
    }
};
