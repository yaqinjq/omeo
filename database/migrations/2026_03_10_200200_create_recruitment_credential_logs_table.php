<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recruitment_credential_logs')) {
            return;
        }

        Schema::create('recruitment_credential_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nik', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('last_applied_position', 150)->nullable();
            $table->string('status', 30);
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('purged_at');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['status', 'purged_at']);
            $table->index('nik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_credential_logs');
    }
};
