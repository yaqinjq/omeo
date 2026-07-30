<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('candidate_activity_logs')) {
            return;
        }

        Schema::create('candidate_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 80);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('source_page', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'created_at'], 'candidate_activity_candidate_created_idx');
            $table->index(['actor_user_id', 'created_at'], 'candidate_activity_actor_created_idx');
            $table->index('action_type', 'candidate_activity_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_activity_logs');
    }
};
