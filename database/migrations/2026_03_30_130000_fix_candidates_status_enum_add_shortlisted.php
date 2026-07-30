<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function shouldSkip(): bool
    {
        return ! Schema::hasTable('candidates') || ! Schema::hasColumn('candidates', 'status');
    }

    public function up(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `candidates` MODIFY `status` ENUM('applied','shortlisted','accepted','rejected','blocked') NOT NULL DEFAULT 'applied'");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('candidates', function (Blueprint $table): void {
                $table->string('status')->default('applied')->change();
            });
        }
    }

    public function down(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `candidates` MODIFY `status` ENUM('applied','shortlisted','accepted','rejected','blocked') NOT NULL DEFAULT 'applied'");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('candidates', function (Blueprint $table): void {
                $table->string('status')->default('applied')->change();
            });
        }
    }
};
