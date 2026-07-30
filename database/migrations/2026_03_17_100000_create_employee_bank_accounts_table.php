<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_bank_accounts')) {
            return;
        }

        Schema::create('employee_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('bank_code', 50)->nullable()->index();
            $table->string('bank_name', 150);
            $table->string('account_number', 100);
            $table->string('account_holder_name', 150);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'deleted_at'], 'employee_bank_accounts_employee_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_accounts');
    }
};
