<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('candidate_blacklists')) {
            Schema::create('candidate_blacklists', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
                $table->enum('identifier_type', ['nik', 'email', 'phone']);
                $table->string('identifier_value', 191);
                $table->boolean('is_active')->default(true);
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('blacklisted_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['identifier_type', 'identifier_value'], 'candidate_blacklists_identifier_unique');
                $table->index(['identifier_type', 'is_active'], 'candidate_blacklists_type_active_idx');
            });
        }

        if (!Schema::hasTable('candidate_retention_histories')) {
            Schema::create('candidate_retention_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('original_candidate_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('full_name', 255)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('nik', 50)->nullable();
                $table->string('status', 30);
                $table->dateTime('decision_at')->nullable();
                $table->dateTime('deleted_at_retention');
                $table->unsignedSmallInteger('retention_days');
                $table->string('delete_reason', 255)->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();

                $table->index('original_candidate_id', 'candidate_retention_histories_candidate_idx');
                $table->index('deleted_at_retention', 'candidate_retention_histories_deleted_idx');
                $table->index(['status', 'deleted_at_retention'], 'candidate_retention_histories_status_deleted_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_retention_histories');
        Schema::dropIfExists('candidate_blacklists');
    }
};
