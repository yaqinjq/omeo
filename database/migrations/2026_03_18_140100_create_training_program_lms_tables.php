<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_programs')) {
            Schema::create('training_programs', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('audience_scope', ['general', 'department', 'position'])->default('general');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('mentor_user_id')->nullable();
                $table->boolean('is_sequential')->default(true);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('training_program_material')) {
            Schema::create('training_program_material', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_program_id');
                $table->unsignedBigInteger('training_material_id');
                $table->unsignedInteger('sequence_order')->default(1);
                $table->boolean('is_required')->default(true);
                $table->boolean('unlock_after_previous_completed')->default(true);
                $table->unsignedInteger('estimated_minutes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['training_program_id', 'training_material_id'], 'training_program_material_unique');
                $table->index(['training_program_id', 'sequence_order'], 'training_program_material_order_idx');
            });
        }

        if (! Schema::hasTable('training_program_enrollments')) {
            Schema::create('training_program_enrollments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_program_id');
                $table->unsignedBigInteger('employee_id');
                $table->enum('status', ['assigned', 'in_progress', 'completed', 'failed', 'cancelled'])->default('assigned');
                $table->decimal('progress_percent', 5, 2)->unsigned()->default(0);
                $table->unsignedBigInteger('last_training_material_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['training_program_id', 'employee_id'], 'training_program_enrollment_unique');
                $table->index(['employee_id', 'status'], 'training_program_enrollment_employee_status_idx');
            });
        }

        if (! Schema::hasTable('training_material_progress')) {
            Schema::create('training_material_progress', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_program_enrollment_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('training_program_id');
                $table->unsignedBigInteger('training_material_id');
                $table->enum('status', ['locked', 'assigned', 'in_progress', 'completed', 'failed'])->default('assigned');
                $table->decimal('progress_percent', 5, 2)->unsigned()->default(0);
                $table->decimal('pretest_score', 8, 2)->unsigned()->nullable();
                $table->decimal('posttest_score', 8, 2)->unsigned()->nullable();
                $table->unsignedBigInteger('pretest_attempt_id')->nullable();
                $table->unsignedBigInteger('posttest_attempt_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['training_program_enrollment_id', 'training_material_id'], 'training_material_progress_unique');
                $table->index(['employee_id', 'status'], 'training_material_progress_employee_status_idx');
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};

