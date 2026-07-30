<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('nik')->nullable(); // untuk blacklist
            $table->enum('status', ['applied','rejected','blocked','accepted'])->default('applied');
            $table->timestamp('auto_delete_at')->nullable(); // rejected → +7 hari
            $table->timestamps();
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('type'); // cv, ktp, kk, npwp, bpjs, sim, passport, foto, dll
            $table->string('file_path');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->unique(['candidate_id','type']);
        });

        Schema::create('appraisal_reviewer_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('scope', ['linear','cross'])->default('cross');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_reviewer_rules');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidates');
    }
};
