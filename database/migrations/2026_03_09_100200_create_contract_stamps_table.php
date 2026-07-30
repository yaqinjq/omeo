<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('stamp_type', 30);
            $table->string('stamp_number', 120)->nullable();
            $table->string('stamp_proof_path')->nullable();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['contract_id', 'confirmed_at'], 'contract_stamps_contract_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_stamps');
    }
};
