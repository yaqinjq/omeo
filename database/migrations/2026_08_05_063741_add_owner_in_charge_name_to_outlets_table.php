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
        if (Schema::hasColumn('outlets', 'owner_in_charge_name')) {
            return;
        }

        Schema::table('outlets', function (Blueprint $table) {
            $table->string('owner_in_charge_name', 150)->nullable()->after('outlet_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('owner_in_charge_name');
        });
    }
};
