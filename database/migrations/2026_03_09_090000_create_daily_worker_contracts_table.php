<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_worker_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->restrictOnDelete();
            $table->string('contract_number', 150);
            $table->string('status', 30)->default('sent')->index();
            $table->longText('contract_html');
            $table->longText('signed_contract_html')->nullable();
            $table->string('stamp_file_path')->nullable();
            $table->string('stamp_number', 120)->nullable();
            $table->boolean('stamp_confirmed')->default(false);
            $table->string('candidate_signature_path')->nullable();
            $table->text('candidate_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamps();

            $table->index(['candidate_id', 'status']);
            $table->index(['contract_template_id', 'status']);
            $table->unique(['candidate_id', 'contract_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_worker_contracts');
    }
};
