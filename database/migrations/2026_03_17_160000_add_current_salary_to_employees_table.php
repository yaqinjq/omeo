<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'current_salary')) {
                    $table->decimal('current_salary', 15, 2)->nullable()->after('probation_end_date');
                }
            });
        }
    }

    public function down(): void
    {
        // additive-only rollback intentionally no-op for safety in production
    }
};
