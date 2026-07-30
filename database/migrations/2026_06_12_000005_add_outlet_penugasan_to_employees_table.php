<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // outlet_id yang sudah ada = outlet asal (untuk penagihan BPJS)
            // outlet_penugasan_id = tempat kerja aktual (null = sama dengan outlet_id)
            $table->foreignId('outlet_penugasan_id')->nullable()->after('outlet_id')
                  ->constrained('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['outlet_penugasan_id']);
            $table->dropColumn('outlet_penugasan_id');
        });
    }
};
