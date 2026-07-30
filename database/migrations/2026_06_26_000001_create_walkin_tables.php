<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('walkin_events')) {
            Schema::create('walkin_events', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->date('event_date');
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->json('target_positions')->nullable();
                $table->enum('status', ['draft', 'published', 'ongoing', 'completed'])->default('draft');
                $table->dateTime('registration_open_until')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['status', 'event_date']);
            });
        }

        if (! Schema::hasTable('walkin_referral_links')) {
            Schema::create('walkin_referral_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained('walkin_events')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->string('referral_code', 12)->unique();
                $table->unsignedInteger('total_registrations')->default(0);
                $table->timestamps();

                $table->unique(['event_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('walkin_candidates')) {
            Schema::create('walkin_candidates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained('walkin_events');
                $table->string('referral_code', 12)->nullable()->index();
                $table->unsignedBigInteger('referred_by_user_id')->nullable();
                $table->string('candidate_name');
                $table->string('candidate_phone');
                $table->string('candidate_email')->nullable();
                $table->string('ig_account')->nullable();
                $table->string('applied_position')->nullable();
                $table->string('registration_number', 10)->nullable()->unique();
                $table->enum('registration_status', ['registered', 'checked_in', 'no_show', 'withdrawn'])->default('registered')->index();
                $table->dateTime('check_in_time')->nullable();
                $table->enum('interview_status', ['pending', 'passed', 'failed', 'skipped'])->default('pending');
                $table->unsignedBigInteger('interviewed_by')->nullable();
                $table->text('interview_notes')->nullable();
                $table->json('application_form_data')->nullable();
                $table->enum('test_type', ['differensial_disc', 'iq', 'none'])->nullable();
                $table->decimal('test_score', 5, 2)->nullable();
                $table->enum('test_status', ['pending', 'passed', 'failed'])->default('pending');
                $table->unsignedBigInteger('plotting_outlet_id')->nullable();
                $table->string('plotting_position')->nullable();
                $table->enum('final_status', ['pending', 'accepted', 'reserved', 'rejected'])->default('pending');
                $table->dateTime('announcement_sent_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'registration_status']);
                $table->index(['event_id', 'referred_by_user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('walkin_candidates');
        Schema::dropIfExists('walkin_referral_links');
        Schema::dropIfExists('walkin_events');
    }
};
