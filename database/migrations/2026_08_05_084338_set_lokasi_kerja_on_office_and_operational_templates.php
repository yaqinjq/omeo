<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Root-cause fix ditemukan lewat appraisal:inventory-criteria-duplicates:
     * "KRITERIA PENILAIAN OFFICE" dan "KRITERIA OPERASIONAL OUTLET" tidak
     * pernah punya lokasi_kerja terisi sejak dibuat, jadi
     * AppraisalCriteriaTemplate::resolveFor() TIDAK PERNAH bisa memilih
     * keduanya — semua appraisal (lama & baru) selalu jatuh ke template
     * Default. Ini memasang lokasi_kerja yang benar supaya pemilihan
     * template otomatis sesuai kategori karyawan mulai berfungsi.
     */
    public function up(): void
    {
        if (! Schema::hasTable('appraisal_criteria_templates')) {
            return;
        }

        DB::table('appraisal_criteria_templates')
            ->where('name', 'KRITERIA PENILAIAN OFFICE')
            ->whereNull('lokasi_kerja')
            ->update(['lokasi_kerja' => 'office']);

        DB::table('appraisal_criteria_templates')
            ->where('name', 'KRITERIA OPERASIONAL OUTLET')
            ->whereNull('lokasi_kerja')
            ->update(['lokasi_kerja' => 'outlet']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('appraisal_criteria_templates')) {
            return;
        }

        DB::table('appraisal_criteria_templates')
            ->whereIn('name', ['KRITERIA PENILAIAN OFFICE', 'KRITERIA OPERASIONAL OUTLET'])
            ->update(['lokasi_kerja' => null]);
    }
};
