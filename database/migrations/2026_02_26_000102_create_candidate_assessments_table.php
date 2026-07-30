<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('candidate_assessments')) {
            return;
        }

        Schema::create('candidate_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->integer('iq_score')->nullable();
            $table->json('disc_result')->nullable();
            $table->integer('interview_score')->nullable();
            $table->text('interview_notes')->nullable();
            $table->enum('status', ['in_process', 'passed', 'reserve', 'rejected', 'blocked'])->default('in_process');
            $table->timestamps();
            $table->unique('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_assessments');
    }
};

