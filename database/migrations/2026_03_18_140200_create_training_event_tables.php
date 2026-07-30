<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_events')) {
            Schema::create('training_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_program_id')->nullable();
                $table->unsignedBigInteger('training_material_id')->nullable();
                $table->string('title');
                $table->enum('event_type', ['lms', 'meeting', 'practical'])->default('lms');
                $table->string('platform', 50)->nullable();
                $table->text('meeting_url')->nullable();
                $table->string('location_name')->nullable();
                $table->text('location_address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedBigInteger('mentor_user_id')->nullable();
                $table->boolean('requires_registration')->default(true);
                $table->boolean('requires_photo_validation')->default(true);
                $table->boolean('requires_geolocation')->default(true);
                $table->enum('status', ['draft', 'published', 'started', 'completed', 'cancelled'])->default('draft');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('training_event_participants')) {
            Schema::create('training_event_participants', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_event_id');
                $table->unsignedBigInteger('employee_id');
                $table->enum('status', ['invited', 'registered', 'checked_in', 'attended', 'absent', 'cancelled'])->default('invited');
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('checked_in_at')->nullable();
                $table->string('selfie_photo_path')->nullable();
                $table->string('environment_photo_path')->nullable();
                $table->decimal('check_in_latitude', 10, 7)->nullable();
                $table->decimal('check_in_longitude', 10, 7)->nullable();
                $table->string('check_in_address')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['training_event_id', 'employee_id'], 'training_event_participant_unique');
                $table->index(['employee_id', 'status'], 'training_event_participant_employee_status_idx');
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
