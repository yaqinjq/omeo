<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_weight_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['global', 'per_type'])->default('global');
            $table->enum('appraisal_type', ['probation', 'annual', 'contract_renewal'])->nullable();
            $table->decimal('weight_criteria', 5, 2)->default(50.00)->comment('Kriteria Bintang / Indikator');
            $table->decimal('weight_kpi',      5, 2)->default(30.00)->comment('KPI Pencapaian Target');
            $table->decimal('weight_training',  5, 2)->default(20.00)->comment('Hasil Training');
            $table->decimal('weight_skill',     5, 2)->default(0.00)->comment('Kompetensi Keahlian');
            $table->decimal('weight_position',  5, 2)->default(0.00)->comment('Kompetensi Posisi/Jabatan');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'appraisal_type']);
        });

        // Seed default global config
        DB::table('appraisal_weight_configs')->insert([
            'scope'            => 'global',
            'appraisal_type'   => null,
            'weight_criteria'  => 50.00,
            'weight_kpi'       => 30.00,
            'weight_training'  => 20.00,
            'weight_skill'     => 0.00,
            'weight_position'  => 0.00,
            'updated_by'       => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_weight_configs');
    }
};
