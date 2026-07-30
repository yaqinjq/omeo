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
            if (! Schema::hasColumn('appraisals', 'proposed_status')) {
                $table->enum('proposed_status', [
                    're_contract',
                    'discontinue',
                    'promoted',
                    'permanent',
                    'position_change',
                    'pending',
                ])->nullable()->default(null)->after('final_result');
            }
            if (! Schema::hasColumn('appraisals', 'proposed_position')) {
                $table->string('proposed_position')->nullable()->after('proposed_status');
            }
            if (! Schema::hasColumn('appraisals', 'proposed_contract_duration')) {
                $table->string('proposed_contract_duration')->nullable()->after('proposed_position');
            }
            if (! Schema::hasColumn('appraisals', 'feedback_achievements')) {
                $table->text('feedback_achievements')->nullable()->after('feedback_notes');
            }
            if (! Schema::hasColumn('appraisals', 'feedback_training_recommendations')) {
                $table->text('feedback_training_recommendations')->nullable()->after('feedback_achievements');
            }
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
