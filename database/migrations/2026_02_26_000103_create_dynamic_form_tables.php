<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('type', ['iq', 'disc', 'custom'])->default('custom');
                $table->integer('duration_minutes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('form_questions')) {
            Schema::create('form_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
                $table->integer('position')->default(0);
                $table->text('question_text');
                $table->enum('question_type', ['short_text', 'paragraph', 'radio', 'checkbox', 'dropdown', 'rating', 'linear_scale']);
                $table->boolean('is_required')->default(false);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('form_options')) {
            Schema::create('form_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('form_questions')->cascadeOnDelete();
                $table->integer('position')->default(0);
                $table->string('option_text');
                $table->string('value')->nullable();
                $table->integer('weight')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('form_assignments')) {
            Schema::create('form_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->enum('status', ['locked', 'opened', 'submitted', 'expired'])->default('locked');
                $table->dateTime('opened_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['form_id', 'candidate_id']);
            });
        }

        if (!Schema::hasTable('form_attempts')) {
            Schema::create('form_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_assignment_id')->constrained('form_assignments')->cascadeOnDelete();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->integer('time_spent_seconds')->nullable();
                $table->json('computed_result')->nullable();
                $table->timestamps();
                $table->unique('form_assignment_id');
            });
        }

        if (!Schema::hasTable('form_answers')) {
            Schema::create('form_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('form_attempt_id')->constrained('form_attempts')->cascadeOnDelete();
                $table->foreignId('question_id')->constrained('form_questions')->cascadeOnDelete();
                $table->text('answer_text')->nullable();
                $table->string('answer_value')->nullable();
                $table->json('answer_json')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_answers');
        Schema::dropIfExists('form_attempts');
        Schema::dropIfExists('form_assignments');
        Schema::dropIfExists('form_options');
        Schema::dropIfExists('form_questions');
        Schema::dropIfExists('forms');
    }
};

