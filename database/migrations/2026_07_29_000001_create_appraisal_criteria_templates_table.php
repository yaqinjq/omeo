<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appraisal_criteria_templates')) {
            return;
        }

        Schema::create('appraisal_criteria_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // Matches employees.lokasi_kerja enum ('outlet','office','production'). Null = not auto-matched.
            $table->string('lokasi_kerja', 20)->nullable();
            $table->boolean('is_default')->default(false)->comment('Dipakai sebagai fallback bila tidak ada template yang cocok');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed a default template. Existing indicators are backfilled into it by
        // the follow-up migration that adds appraisal_indicators.template_id.
        DB::table('appraisal_criteria_templates')->insert([
            'name'         => 'Default (Semua Kategori)',
            'description'  => 'Kriteria umum yang berlaku sebelum kriteria per kategori karyawan dibuat.',
            'lokasi_kerja' => null,
            'is_default'   => true,
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        // Compatibility-first migration: no destructive rollback.
    }
};
