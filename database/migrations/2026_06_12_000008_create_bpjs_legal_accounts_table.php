<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_legal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->enum('bpjs_type', ['ketenagakerjaan', 'kesehatan']);
            $table->string('account_number', 50); // nomor kepesertaan BPJS
            $table->string('account_name', 200)->nullable(); // nama peserta terdaftar
            $table->string('bpjs_branch', 100)->nullable(); // kantor cabang BPJS
            $table->date('registered_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'bpjs_type'], 'unique_legal_bpjs_type');
            $table->index('legal_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_legal_accounts');
    }
};
