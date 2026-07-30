<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasIndex = collect(DB::select("SHOW INDEX FROM employees"))
            ->contains(fn ($row) => $row->Key_name === 'employees_email_private_index');

        if (! $hasIndex) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('email_private', 'employees_email_private_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_email_private_index');
        });
    }
};
