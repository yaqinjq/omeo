<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('master_shifts', 'outlet_id')) {
            return;
        }

        Schema::table('master_shifts', function (Blueprint $table) {
            $table->dropUnique('master_shifts_code_unique');
            $table->unsignedBigInteger('outlet_id')->nullable()->after('id');
            $table->index('outlet_id');
            $table->unique(['outlet_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_shifts', function (Blueprint $table) {
            $table->dropUnique(['outlet_id', 'code']);
            $table->dropIndex(['outlet_id']);
            $table->dropColumn('outlet_id');
            $table->unique('code');
        });
    }
};
