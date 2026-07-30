<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_group_id')->nullable()->constrained('company_groups')->nullOnDelete();
            $table->string('name', 200);
            $table->string('short_name', 50)->nullable();
            $table->enum('entity_type', ['PT', 'CV', 'UD', 'Firma', 'Yayasan', 'Lainnya'])->default('PT');
            $table->string('npwp', 30)->nullable()->unique();
            $table->string('nib', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('director_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('company_group_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entities');
    }
};
