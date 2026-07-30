<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->foreignId('legal_entity_id')->nullable()->after('external_id')
                  ->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('legal_entity_id')
                  ->constrained('regions')->nullOnDelete();
            $table->enum('outlet_type', ['branch', 'central_kitchen', 'office', 'virtual'])
                  ->default('branch')->after('region_id');
            $table->boolean('is_active')->default(true)->after('outlet_type');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn(['legal_entity_id', 'region_id', 'outlet_type', 'is_active']);
        });
    }
};
