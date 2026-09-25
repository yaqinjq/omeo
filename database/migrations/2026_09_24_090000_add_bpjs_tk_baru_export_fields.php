<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan untuk fitur Export "Data TK Baru" (template resmi BPJS
 * Ketenagakerjaan) — field yang belum ada sumber datanya sama sekali di
 * OMEO. Sengaja diisi HRD dulu lewat form Data Karyawan (bukan self-service
 * karyawan) sebagai langkah awal yang bisa langsung dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'kode_pos')) {
                    $table->string('kode_pos', 10)->nullable()->after('lokasi_kerja');
                }
                if (! Schema::hasColumn('employees', 'jenis_identitas')) {
                    $table->string('jenis_identitas', 20)->nullable()->after('kode_pos');
                }
                if (! Schema::hasColumn('employees', 'masa_laku_identitas')) {
                    $table->string('masa_laku_identitas', 20)->nullable()->after('jenis_identitas');
                }
                if (! Schema::hasColumn('employees', 'surat_menyurat_ke')) {
                    $table->string('surat_menyurat_ke', 10)->nullable()->after('masa_laku_identitas');
                }
                if (! Schema::hasColumn('employees', 'status_pegawai_bpjs')) {
                    $table->string('status_pegawai_bpjs', 10)->nullable()->after('surat_menyurat_ke');
                }
                if (! Schema::hasColumn('employees', 'tanggal_akhir_kontrak')) {
                    $table->date('tanggal_akhir_kontrak')->nullable()->after('status_pegawai_bpjs');
                }
            });
        }

        if (Schema::hasTable('outlets')) {
            Schema::table('outlets', function (Blueprint $table) {
                if (! Schema::hasColumn('outlets', 'bpjs_lokasi_kode')) {
                    $table->string('bpjs_lokasi_kode', 10)->nullable()->after('region_id');
                }
                if (! Schema::hasColumn('outlets', 'bpjs_lokasi_nama')) {
                    $table->string('bpjs_lokasi_nama', 150)->nullable()->after('bpjs_lokasi_kode');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                foreach (['kode_pos', 'jenis_identitas', 'masa_laku_identitas', 'surat_menyurat_ke', 'status_pegawai_bpjs', 'tanggal_akhir_kontrak'] as $column) {
                    if (Schema::hasColumn('employees', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('outlets')) {
            Schema::table('outlets', function (Blueprint $table) {
                foreach (['bpjs_lokasi_kode', 'bpjs_lokasi_nama'] as $column) {
                    if (Schema::hasColumn('outlets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
