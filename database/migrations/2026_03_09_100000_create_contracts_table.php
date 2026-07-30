<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('contract_number', 150)->unique();
            $table->enum('status', ['draft','sent','viewed','awaiting_stamp','awaiting_signature','submitted','hr_review','approved','rejected','cancelled'])->default('draft')->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path_original')->nullable();
            $table->string('pdf_path_signed')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'status'], 'contracts_candidate_status_idx');
            $table->index(['contract_template_id', 'status'], 'contracts_template_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

