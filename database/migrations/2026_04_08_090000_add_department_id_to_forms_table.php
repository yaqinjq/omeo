<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('forms')) {
            return;
        }

        Schema::table('forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('forms', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('type');
            }
        });

        if (Schema::hasTable('departments') && ! $this->hasForeignKey('forms', 'forms_department_id_foreign')) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('forms')) {
            return;
        }

        if ($this->hasForeignKey('forms', 'forms_department_id_foreign')) {
            Schema::table('forms', function (Blueprint $table): void {
                $table->dropForeign('forms_department_id_foreign');
            });
        }

        Schema::table('forms', function (Blueprint $table): void {
            if (Schema::hasColumn('forms', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });
    }

    private function hasForeignKey(string $table, string $foreignName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return false;
        }

        $schema = $connection->getDatabaseName();
        $prefix = $connection->getTablePrefix();
        $tableName = $prefix . $table;

        return Schema::getConnection()->table('information_schema.table_constraints')
            ->where('constraint_schema', $schema)
            ->where('table_name', $tableName)
            ->where('constraint_name', $foreignName)
            ->exists();
    }
};
