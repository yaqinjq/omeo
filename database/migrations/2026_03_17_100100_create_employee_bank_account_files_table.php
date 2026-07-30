<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_bank_account_files')) {
            return;
        }

        Schema::create('employee_bank_account_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_bank_account_id')->index();
            $table->string('file_path', 255);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_bank_account_id', 'deleted_at'], 'employee_bank_account_files_account_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_account_files');
    }
};
