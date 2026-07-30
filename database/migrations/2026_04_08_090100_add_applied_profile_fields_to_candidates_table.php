<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('candidates')) {
            return;
        }

        Schema::table('candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidates', 'applied_position_id')) {
                $table->unsignedBigInteger('applied_position_id')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('candidates', 'applied_position_name')) {
                $table->string('applied_position_name', 150)->nullable()->after('applied_position_id');
            }
            if (! Schema::hasColumn('candidates', 'applied_department_id')) {
                $table->unsignedBigInteger('applied_department_id')->nullable()->after('applied_position_name');
            }
            if (! Schema::hasColumn('candidates', 'applied_department_name')) {
                $table->string('applied_department_name', 150)->nullable()->after('applied_department_id');
            }
            if (! Schema::hasColumn('candidates', 'applied_outlet_id')) {
                $table->unsignedBigInteger('applied_outlet_id')->nullable()->after('applied_department_name');
            }
            if (! Schema::hasColumn('candidates', 'applied_outlet_name')) {
                $table->string('applied_outlet_name', 150)->nullable()->after('applied_outlet_id');
            }
        });

        $this->addForeignIfPossible('candidates', 'applied_position_id', 'positions', 'candidates_applied_position_id_foreign');
        $this->addForeignIfPossible('candidates', 'applied_department_id', 'departments', 'candidates_applied_department_id_foreign');
        $this->addForeignIfPossible('candidates', 'applied_outlet_id', 'outlets', 'candidates_applied_outlet_id_foreign');
    }

    public function down(): void
    {
        if (! Schema::hasTable('candidates')) {
            return;
        }

        foreach ([
            'candidates_applied_position_id_foreign',
            'candidates_applied_department_id_foreign',
            'candidates_applied_outlet_id_foreign',
        ] as $foreignName) {
            if ($this->hasForeignKey('candidates', $foreignName)) {
                Schema::table('candidates', function (Blueprint $table) use ($foreignName): void {
                    $table->dropForeign($foreignName);
                });
            }
        }

        Schema::table('candidates', function (Blueprint $table): void {
            foreach ([
                'applied_position_id',
                'applied_position_name',
                'applied_department_id',
                'applied_department_name',
                'applied_outlet_id',
                'applied_outlet_name',
            ] as $column) {
                if (Schema::hasColumn('candidates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addForeignIfPossible(string $table, string $column, string $foreignTable, string $foreignName): void
    {
        if (! Schema::hasTable($foreignTable) || $this->hasForeignKey($table, $foreignName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable): void {
            $blueprint->foreign($column)->references('id')->on($foreignTable)->nullOnDelete();
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

        return $connection->table('information_schema.table_constraints')
            ->where('constraint_schema', $schema)
            ->where('table_name', $tableName)
            ->where('constraint_name', $foreignName)
            ->exists();
    }
};
