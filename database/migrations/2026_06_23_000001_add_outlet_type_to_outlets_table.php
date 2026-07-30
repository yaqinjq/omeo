<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Ubah ENUM lama ke VARCHAR(30) dan standarkan ke nilai baru
        DB::statement("ALTER TABLE outlets MODIFY COLUMN outlet_type VARCHAR(30) NOT NULL DEFAULT 'operational' COMMENT 'operational=outlet customer-facing | payroll=cost center internal | production=unit produksi internal'");

        DB::table('outlets')
            ->whereIn('outlet_type', ['branch', 'office', 'virtual'])
            ->update(['outlet_type' => 'operational']);

        DB::table('outlets')
            ->where('outlet_type', 'central_kitchen')
            ->update(['outlet_type' => 'production']);
    }

    public function down(): void
    {
        // Kembalikan ke varchar dengan default branch
        DB::statement("ALTER TABLE outlets MODIFY COLUMN outlet_type VARCHAR(30) NOT NULL DEFAULT 'branch'");

        DB::table('outlets')
            ->whereIn('outlet_type', ['operational', 'payroll'])
            ->update(['outlet_type' => 'branch']);

        DB::table('outlets')
            ->where('outlet_type', 'production')
            ->update(['outlet_type' => 'central_kitchen']);
    }
};
