<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('forms') || ! Schema::hasColumn('forms', 'type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE forms MODIFY type ENUM('iq','disc','tiu','diferensial','fat','custom') NOT NULL DEFAULT 'custom'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('forms') || ! Schema::hasColumn('forms', 'type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE forms MODIFY type ENUM('iq','disc','custom') NOT NULL DEFAULT 'custom'");
        }
    }
};
