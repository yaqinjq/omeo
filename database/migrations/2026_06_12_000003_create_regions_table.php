<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->string('name', 100);
            $table->string('province', 100)->nullable();
            $table->string('bpjs_area_code', 20)->nullable();
            $table->decimal('umr_amount', 15, 2)->nullable();
            $table->smallInteger('umr_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('legal_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
