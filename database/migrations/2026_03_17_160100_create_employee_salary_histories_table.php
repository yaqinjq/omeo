<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('employee_salary_histories')) {
            Schema::create('employee_salary_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees');
                $table->decimal('amount', 15, 2);
                $table->date('effective_date')->nullable()->index();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source', 50)->nullable();
                $table->text('notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index(['employee_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // additive-only rollback intentionally no-op for safety in production
    }
};
