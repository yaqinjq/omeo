<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('candidates')) {
            return;
        }

        Schema::table('candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('candidates', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id', 'candidates_user_id_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('candidates')) {
            return;
        }

        Schema::table('candidates', function (Blueprint $table) {
            if (Schema::hasColumn('candidates', 'user_id')) {
                $table->dropIndex('candidates_user_id_index');
                $table->dropColumn('user_id');
            }
        });
    }
};

