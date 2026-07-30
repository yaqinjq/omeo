<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bpjs_outlet_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('periode', 7); // 'YYYY-MM'
            $table->unsignedInteger('total_karyawan')->default(0);
            $table->unsignedInteger('karyawan_terdaftar_bpjs')->default(0);
            $table->unsignedInteger('karyawan_tidak_terdaftar')->default(0);
            $table->decimal('total_bpjs_tk', 15, 2)->default(0);
            $table->decimal('total_bpjs_jkes', 15, 2)->default(0);
            $table->decimal('total_bpjs_keseluruhan', 15, 2)->default(0);
            $table->string('status_bayar', 20)->default('belum_bayar');
            // belum_bayar | sudah_bayar | verified
            $table->date('tanggal_bayar')->nullable();
            $table->string('bukti_bayar_path', 512)->nullable();
            $table->string('nomor_referensi', 100)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'periode']);
            $table->index(['periode', 'status_bayar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bpjs_outlet_summaries');
    }
};
