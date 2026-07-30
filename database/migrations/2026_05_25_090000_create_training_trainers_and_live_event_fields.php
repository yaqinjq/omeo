<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_trainers')) {
            Schema::create('training_trainers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id')->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('status', 30)->default('pending')->index();
                $table->string('specialty')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('appointed_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'employee_id'], 'training_trainers_status_employee_idx');
            });
        }

        if (Schema::hasTable('training_events')) {
            Schema::table('training_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('training_events', 'registration_deadline_at')) {
                    $table->timestamp('registration_deadline_at')->nullable()->after('ends_at');
                }
                if (! Schema::hasColumn('training_events', 'check_in_opens_at')) {
                    $table->timestamp('check_in_opens_at')->nullable()->after('registration_deadline_at');
                }
                if (! Schema::hasColumn('training_events', 'check_in_closes_at')) {
                    $table->timestamp('check_in_closes_at')->nullable()->after('check_in_opens_at');
                }
                if (! Schema::hasColumn('training_events', 'max_participants')) {
                    $table->unsignedInteger('max_participants')->nullable()->after('check_in_closes_at');
                }
                if (! Schema::hasColumn('training_events', 'participant_instruction')) {
                    $table->text('participant_instruction')->nullable()->after('location_address');
                }
            });
        }

        if (Schema::hasTable('training_event_participants')) {
            Schema::table('training_event_participants', function (Blueprint $table): void {
                if (! Schema::hasColumn('training_event_participants', 'invited_at')) {
                    $table->timestamp('invited_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('training_event_participants', 'invited_by')) {
                    $table->unsignedBigInteger('invited_by')->nullable()->after('invited_at');
                }
                if (! Schema::hasColumn('training_event_participants', 'attendance_marked_at')) {
                    $table->timestamp('attendance_marked_at')->nullable()->after('checked_in_at');
                }
                if (! Schema::hasColumn('training_event_participants', 'attendance_marked_by')) {
                    $table->unsignedBigInteger('attendance_marked_by')->nullable()->after('attendance_marked_at');
                }
                if (! Schema::hasColumn('training_event_participants', 'attendance_note')) {
                    $table->text('attendance_note')->nullable()->after('check_in_address');
                }
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
