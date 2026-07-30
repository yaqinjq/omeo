<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('source_brand', 50);          // 'ah_pek' | 'tokio' | 'other'
            $table->string('source_file_name', 255);
            $table->string('source_file_type', 10);      // 'csv' | 'xlsx' | 'xls'
            $table->string('periode', 7);                // 'YYYY-MM'
            $table->string('sheet_name', 20)->nullable(); // for xlsx: 'MEI', 'APR', etc.
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->string('status', 20)->default('draft');
            // status: draft | processing | needs_review | completed | cancelled
            $table->string('file_path', 512)->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'periode']);
            $table->index('imported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_import_sessions');
    }
};
