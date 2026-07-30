<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appraisals')) {
            return;
        }

        Schema::table('appraisals', function (Blueprint $table): void {
            if (!Schema::hasColumn('appraisals', 'due_date')) {
                $table->date('due_date')->nullable()->after('date_appraised');
            }

            if (!Schema::hasColumn('appraisals', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->after('due_date');
            }

            if (!Schema::hasColumn('appraisals', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable()->after('invited_at');
            }

            if (!Schema::hasColumn('appraisals', 'is_feedback_private')) {
                $table->boolean('is_feedback_private')->default(true)->after('last_reminded_at');
            }

            if (!Schema::hasColumn('appraisals', 'invitation_note')) {
                $table->text('invitation_note')->nullable()->after('is_feedback_private');
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
