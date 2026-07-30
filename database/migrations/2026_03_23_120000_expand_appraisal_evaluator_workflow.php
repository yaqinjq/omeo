<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appraisals')) {
            Schema::table('appraisals', function (Blueprint $table): void {
                if (!Schema::hasColumn('appraisals', 'invited_by_user_id')) {
                    $table->unsignedBigInteger('invited_by_user_id')->nullable()->after('appraiser_id');
                }
                if (!Schema::hasColumn('appraisals', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('last_reminded_at');
                }
                if (!Schema::hasColumn('appraisals', 'feedback_strengths')) {
                    $table->text('feedback_strengths')->nullable()->after('invitation_note');
                }
                if (!Schema::hasColumn('appraisals', 'feedback_improvements')) {
                    $table->text('feedback_improvements')->nullable()->after('feedback_strengths');
                }
                if (!Schema::hasColumn('appraisals', 'feedback_notes')) {
                    $table->text('feedback_notes')->nullable()->after('feedback_improvements');
                }
            });
        }

        if (!Schema::hasTable('appraisal_component_scores')) {
            Schema::create('appraisal_component_scores', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_id');
                $table->string('component_key', 100);
                $table->string('component_label', 150);
                $table->string('source_type', 50)->nullable();
                $table->decimal('score_raw', 8, 2)->nullable();
                $table->decimal('score_normalized', 8, 2)->nullable();
                $table->decimal('weight', 8, 2)->default(0);
                $table->text('notes')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->unique(['appraisal_id', 'component_key'], 'appraisal_component_unique');
            });
        }

        if (!Schema::hasTable('appraisal_invitation_logs')) {
            Schema::create('appraisal_invitation_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->string('action', 50);
                $table->text('notes')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
