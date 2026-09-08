<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_chart_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('org_chart_nodes', 'display_position_id')) {
                $table->unsignedBigInteger('display_position_id')->nullable()->after('employee_id');
                $table->foreign('display_position_id')->references('id')->on('positions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_chart_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('org_chart_nodes', 'display_position_id')) {
                $table->dropForeign(['display_position_id']);
                $table->dropColumn('display_position_id');
            }
        });
    }
};
