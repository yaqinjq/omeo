<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appraisal_edit_requests')) {
            return;
        }

        Schema::create('appraisal_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appraisal_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            // Set once the evaluator actually resubmits scores using this approval slot.
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('appraisal_id');
            $table->index(['appraisal_id', 'status']);
        });
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
