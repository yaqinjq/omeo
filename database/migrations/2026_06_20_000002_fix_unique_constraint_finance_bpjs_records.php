<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropUnique('unique_nokom_periode');
            $table->unique(['no_komp', 'periode', 'outlet_name'], 'unique_nokomp_periode_outlet');
        });
    }

    public function down(): void
    {
        Schema::table('finance_bpjs_records', function (Blueprint $table) {
            $table->dropUnique('unique_nokomp_periode_outlet');
            $table->unique(['no_komp', 'periode'], 'unique_nokom_periode');
        });
    }
};
