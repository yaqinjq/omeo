<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('walk_in_events')) {
            Schema::create('walk_in_events', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('status')->default('draft')->index();
                $table->date('event_date')->index();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('location')->nullable();
                $table->text('address')->nullable();
                $table->string('maps_url')->nullable();
                $table->longText('description')->nullable();
                $table->unsignedInteger('quota')->nullable();
                $table->text('participant_instruction')->nullable();
                $table->string('banner_path')->nullable();
                $table->timestamps();

                $table->index(['status', 'event_date']);
            });
        }

        if (! Schema::hasTable('walk_in_event_positions')) {
            Schema::create('walk_in_event_positions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('walk_in_event_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['walk_in_event_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('walk_in_registrations')) {
            Schema::create('walk_in_registrations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('walk_in_event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('walk_in_event_position_id')->nullable()->constrained()->nullOnDelete();
                $table->string('registration_code')->unique();
                $table->string('full_name');
                $table->string('whatsapp_number', 40);
                $table->string('email')->nullable();
                $table->string('domicile')->nullable();
                $table->string('referral_code')->nullable()->index();
                $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('registered')->index();
                $table->boolean('whatsapp_consent')->default(false);
                $table->boolean('attendance_commitment')->default(false);
                $table->dateTime('checked_in_at')->nullable();
                $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('screened_at')->nullable();
                $table->text('screening_note')->nullable();
                $table->timestamps();

                $table->unique(['walk_in_event_id', 'whatsapp_number'], 'walk_in_event_whatsapp_unique');
                $table->index(['walk_in_event_id', 'status']);
            });
        }

        if (! Schema::hasTable('walk_in_checkin_tokens')) {
            Schema::create('walk_in_checkin_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('walk_in_event_id')->constrained()->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->dateTime('valid_from')->nullable();
                $table->dateTime('expires_at')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['walk_in_event_id', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_in_checkin_tokens');
        Schema::dropIfExists('walk_in_registrations');
        Schema::dropIfExists('walk_in_event_positions');
        Schema::dropIfExists('walk_in_events');
    }
};
