<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_form_attempts')) {
            Schema::create('training_form_attempts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('training_program_id');
                $table->unsignedBigInteger('training_material_id');
                $table->unsignedBigInteger('form_id');
                $table->enum('purpose', ['pretest', 'posttest']);
                $table->enum('status', ['draft', 'started', 'submitted', 'expired'])->default('draft');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedInteger('time_spent_seconds')->nullable();
                $table->json('computed_result')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'training_program_id'], 'training_form_attempt_employee_program_idx');
                $table->index(['training_material_id', 'purpose'], 'training_form_attempt_material_purpose_idx');
            });
        }

        if (! Schema::hasTable('training_form_answers')) {
            Schema::create('training_form_answers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_form_attempt_id');
                $table->unsignedBigInteger('question_id');
                $table->text('answer_text')->nullable();
                $table->string('answer_value')->nullable();
                $table->json('answer_json')->nullable();
                $table->string('answer_file_path')->nullable();
                $table->timestamps();

                $table->index(['training_form_attempt_id', 'question_id'], 'training_form_answers_attempt_question_idx');
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
