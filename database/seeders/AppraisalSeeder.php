<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppraisalSeeder extends Seeder
{
    public function run(): void
    {
        // ── Appraisal Periods ──────────────────────────────────────────────────

        DB::table('appraisal_periods')->upsert([
            [
                'name'       => 'Probation Period 2026',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
                'is_active'  => true,
                'type'       => 'probation',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Annual Review 2026',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
                'is_active'  => true,
                'type'       => 'annual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Contract Renewal 2026',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
                'is_active'  => true,
                'type'       => 'contract_renewal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['name'], ['start_date', 'end_date', 'is_active', 'type', 'updated_at']);

        // ── Appraisal Indicators ───────────────────────────────────────────────
        // Truncate dulu agar tidak ada duplikat dari run sebelumnya
        if (Schema::hasTable('appraisal_indicators')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('appraisal_indicators')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Skala 1–5: 1=Buruk, 2=Cukup, 3=Bagus, 4=Sangat Bagus, 5=Luar Biasa
        // Total bobot = 100

        $defaultScaleLabels = json_encode([
            '1' => 'Buruk',
            '2' => 'Cukup',
            '3' => 'Bagus',
            '4' => 'Sangat Bagus',
            '5' => 'Luar Biasa',
        ]);

        $hasDescription = Schema::hasColumn('appraisal_indicators', 'description');
        $hasScaleLabels = Schema::hasColumn('appraisal_indicators', 'scale_labels');
        $hasMaxScore    = Schema::hasColumn('appraisal_indicators', 'max_score');

        $indicators = [
            [
                'category'    => 'Kompetensi Kerja',
                'question'    => 'Pengetahuan Pekerjaan',
                'description' => 'Pemahaman tentang kemampuan & keahlian yang dibutuhkan untuk menyelesaikan pekerjaannya.',
                'weight'      => 10,
            ],
            [
                'category'    => 'Kompetensi Kerja',
                'question'    => 'Kualitas Kerja',
                'description' => 'Ketepatan, perhatian terhadap detil pekerjaan & keefektifan untuk menyelesaikan tugas.',
                'weight'      => 10,
            ],
            [
                'category'    => 'Kompetensi Kerja',
                'question'    => 'Produktivitas',
                'description' => 'Keefektifan, konsistensi & ketepatan waktu dalam menyelesaikan tugas.',
                'weight'      => 10,
            ],
            [
                'category'    => 'Karakter',
                'question'    => 'Keandalan',
                'description' => 'Kemandirian & konsentrasi dalam bekerja, serta kesediaan untuk bertanggung jawab.',
                'weight'      => 10,
            ],
            [
                'category'    => 'Karakter',
                'question'    => 'Komunikasi',
                'description' => 'Kemampuan berkomunikasi baik tulisan maupun lisan untuk menunjang penyelesaian tugas.',
                'weight'      => 8,
            ],
            [
                'category'    => 'Karakter',
                'question'    => 'Hubungan Kerja',
                'description' => 'Kemampuan untuk mengatur hubungan kerjasama yang efektif dengan rekan kerja, atasan & masyarakat.',
                'weight'      => 8,
            ],
            [
                'category'    => 'Karakter',
                'question'    => 'Keselamatan',
                'description' => 'Kepatuhan terhadap peraturan yang mendukung tercapainya standar kerja.',
                'weight'      => 8,
            ],
            [
                'category'    => 'Sikap',
                'question'    => 'Kesopanan',
                'description' => 'Tata krama, kesopan-santunan, menghargai orang lain (rekan kerja, atasan & masyarakat).',
                'weight'      => 8,
            ],
            [
                'category'    => 'Sikap',
                'question'    => 'Motivasi',
                'description' => 'Antusiasme dalam bekerja, kemauan belajar dan mencari informasi baru.',
                'weight'      => 9,
            ],
            [
                'category'    => 'Sikap',
                'question'    => 'Leadership',
                'description' => 'Kemampuan untuk melatih, membina & memimpin.',
                'weight'      => 9,
            ],
            [
                'category'    => 'Kehadiran',
                'question'    => 'Kehadiran',
                'description' => 'Kerajinan & ketepatan waktu dalam bekerja.',
                'weight'      => 10,
            ],
        ];

        $updateCols = ['category', 'weight', 'updated_at'];
        if ($hasDescription) {
            $updateCols[] = 'description';
        }
        if ($hasMaxScore) {
            $updateCols[] = 'max_score';
        }
        if ($hasScaleLabels) {
            $updateCols[] = 'scale_labels';
        }

        foreach ($indicators as $indicator) {
            $row = [
                'category'    => $indicator['category'],
                'question'    => $indicator['question'],
                'weight'      => $indicator['weight'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ];

            if ($hasDescription) {
                $row['description'] = $indicator['description'];
            }
            if ($hasMaxScore) {
                $row['max_score'] = 5;
            }
            if ($hasScaleLabels) {
                $row['scale_labels'] = $defaultScaleLabels;
            }

            DB::table('appraisal_indicators')->upsert($row, ['question'], $updateCols);
        }

        $this->command->info('Appraisal seeder selesai:');
        $this->command->info('  Periods   : ' . DB::table('appraisal_periods')->count());
        $this->command->info('  Indicators: ' . DB::table('appraisal_indicators')->count());
    }
}
