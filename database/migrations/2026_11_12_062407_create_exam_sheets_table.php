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
        if (! Schema::hasTable('exam_sheets')) {
            Schema::create('exam_sheets', function (Blueprint $table) {
                $table->id();
                $table->string('exam_type', 20)->index();
                $table->string('original_filename', 255);
                $table->string('stored_path', 255);
                $table->string('file_extension', 16)->default('xlsx');
                $table->string('active_sheet_name', 120)->nullable();
                $table->json('available_sheets')->nullable();
                $table->json('headers')->nullable();
                $table->longText('raw_rows')->nullable();
                $table->unsignedInteger('row_count')->default(0);
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sheets');
    }
};
