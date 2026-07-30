<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Menggunakan raw statement lebih aman untuk merubah ENUM ke VARCHAR
        // karena terkadang method ->change() dari Blueprint memiliki isu kompatibilitas 
        // dengan Doctrine DBAL jika ada tipe ENUM.
        DB::statement("ALTER TABLE `appraisals` MODIFY COLUMN `final_result` VARCHAR(100) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ke ENUM lama jika diperlukan (meskipun tidak disarankan karena data kualitatif akan ter-truncate)
        // DB::statement("ALTER TABLE `appraisals` MODIFY COLUMN `final_result` ENUM('Lulus','Perpanjang Probation','Tidak Lulus','Promosi','Tetap') NULL");
    }
};
