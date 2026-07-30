<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255)->nullable();
            $table->string('label', 200)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('finance_settings')->insert([
            'key'         => 'active_employee_min_hr_percent',
            'value'       => '0',
            'label'       => 'Ambang Batas Karyawan Aktif (% Hari Kerja)',
            'description' => 'Persentase minimum HR (hari kerja aktual) dibanding Attd (hari kerja standar) '
                            . 'untuk karyawan dianggap "aktif" di bulan tersebut. '
                            . 'Nilai 0 = HR > 0 sudah dianggap aktif (default saat ini). '
                            . 'Nilai 50 = HR harus minimal 50% dari Attd.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
    public function down(): void {
        Schema::dropIfExists('finance_settings');
    }
};
