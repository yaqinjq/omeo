<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appraisals')) {
            return;
        }

        Schema::table('appraisals', function (Blueprint $table): void {
            if (! Schema::hasColumn('appraisals', 'trigger_source')) {
                $table->string('trigger_source', 50)->nullable()->after('appraisal_period_id');
            }
            if (! Schema::hasColumn('appraisals', 'trigger_reason')) {
                $table->string('trigger_reason', 255)->nullable()->after('trigger_source');
            }
            if (! Schema::hasColumn('appraisals', 'probation_end_snapshot')) {
                $table->date('probation_end_snapshot')->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('appraisals', 'accelerated_by_user_id')) {
                $table->unsignedBigInteger('accelerated_by_user_id')->nullable()->after('invited_by_user_id');
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
