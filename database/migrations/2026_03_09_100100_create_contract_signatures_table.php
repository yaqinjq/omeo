<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('signer_role', 30);
            $table->string('signer_name', 150);
            $table->string('signature_image_path');
            $table->timestamp('signed_at')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'signer_role'], 'contract_signatures_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};
