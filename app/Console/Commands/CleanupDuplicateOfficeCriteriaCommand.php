<?php

namespace App\Console\Commands;

use App\Models\Appraisal;
use App\Models\AppraisalWeightConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupDuplicateOfficeCriteriaCommand extends Command
{
    protected $signature = 'appraisals:cleanup-duplicate-office-criteria
                            {--force : Jalankan penghapusan sungguhan (tanpa ini, cuma laporan dry-run)}';

    protected $description = 'Hapus 11 kriteria bahasa Indonesia duplikat di Template #3 (KRITERIA PENILAIAN OFFICE) dan hitung ulang final_score appraisal yang terdampak';

    /**
     * 11 indikator bahasa Indonesia yang duplikat dengan 10 indikator bahasa
     * Inggris "Historis MEO" (46-55) yang sudah ada & dipakai semua evaluator
     * lain di Template #3. Ditemukan lewat investigasi manual bersama user.
     */
    private const DUPLICATE_INDICATOR_IDS = [56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $affected = DB::table('appraisal_details')
            ->whereIn('appraisal_indicator_id', self::DUPLICATE_INDICATOR_IDS)
            ->get()
            ->groupBy('appraisal_id');

        if ($affected->isEmpty()) {
            $this->info('Tidak ada appraisal yang terdampak. Menghapus 11 indikator duplikat langsung (kalau ada).');
        }

        $this->info(($force ? 'MODE SUNGGUHAN' : 'MODE DRY-RUN') . " — {$affected->count()} appraisal terdampak.\n");

        foreach ($affected as $appraisalId => $rows) {
            $appraisal = Appraisal::find($appraisalId);
            if (! $appraisal) {
                $this->warn("Appraisal #{$appraisalId} tidak ditemukan, dilewati.");
                continue;
            }

            $employeeName  = DB::table('employees')->where('id', $appraisal->employee_id)->value('full_name') ?? "#{$appraisal->employee_id}";
            $evaluatorName = DB::table('users')->where('id', $appraisal->appraiser_id)->value('name') ?? "#{$appraisal->appraiser_id}";

            $this->line("Appraisal #{$appraisalId} — {$employeeName} (evaluator: {$evaluatorName})");
            $this->line("  final_score saat ini: {$appraisal->final_score}");
            $this->line('  Menghapus ' . $rows->count() . ' baris kriteria Indonesia duplikat.');

            if ($force) {
                DB::transaction(function () use ($appraisal) {
                    DB::table('appraisal_details')
                        ->where('appraisal_id', $appraisal->id)
                        ->whereIn('appraisal_indicator_id', self::DUPLICATE_INDICATOR_IDS)
                        ->delete();

                    $newFinalScore = $this->recomputeFinalScore($appraisal);

                    $this->line("  final_score baru: {$newFinalScore}");
                });
            } else {
                $previewScore = $this->recomputeFinalScore($appraisal, dryRun: true);
                $this->line("  final_score SETELAH dibersihkan (preview): {$previewScore}");
            }

            $this->newLine();
        }

        // Hapus definisi 11 indikator duplikat itu sendiri — aman dilakukan
        // begitu tidak ada lagi appraisal_details yang mereferensikannya.
        $stillUsed = DB::table('appraisal_details')
            ->whereIn('appraisal_indicator_id', self::DUPLICATE_INDICATOR_IDS)
            ->count();

        if ($stillUsed > 0 && $force) {
            $this->error("Masih ada {$stillUsed} baris appraisal_details yang mereferensikan indikator duplikat — indikator TIDAK dihapus untuk keamanan.");
            return self::FAILURE;
        }

        if ($force) {
            $deleted = DB::table('appraisal_indicators')->whereIn('id', self::DUPLICATE_INDICATOR_IDS)->delete();
            $this->info("Menghapus {$deleted} definisi indikator duplikat dari Template #3.");
        } else {
            $this->info('[DRY RUN] Akan menghapus ' . count(self::DUPLICATE_INDICATOR_IDS) . ' definisi indikator duplikat dari Template #3 setelah semua appraisal di atas dibersihkan.');
            $this->comment('Jalankan ulang dengan --force untuk eksekusi sungguhan.');
        }

        return self::SUCCESS;
    }

    /**
     * Hitung ulang final_score persis meniru rumus di AppraisalController::submit()
     * (kriteria bintang × 20, digabung KPI/Training/Skill/Position sesuai bobot),
     * tapi HANYA berdasarkan appraisal_details yang tersisa setelah pembersihan.
     */
    private function recomputeFinalScore(Appraisal $appraisal, bool $dryRun = false): ?float
    {
        $detailsQuery = DB::table('appraisal_details')->where('appraisal_id', $appraisal->id);
        if ($dryRun) {
            $detailsQuery->whereNotIn('appraisal_indicator_id', self::DUPLICATE_INDICATOR_IDS);
        }
        $details = $detailsQuery->get();

        $indicators = DB::table('appraisal_indicators')
            ->whereIn('id', $details->pluck('appraisal_indicator_id'))
            ->get()
            ->keyBy('id');

        $totalWeight     = 0;
        $weightedStarSum = 0;
        foreach ($details as $d) {
            if ($d->score === null) {
                continue;
            }
            $indicator = $indicators->get($d->appraisal_indicator_id);
            $weight    = $indicator ? (int) $indicator->weight : 1;
            $totalWeight     += $weight;
            $weightedStarSum += ((int) $d->score) * $weight;
        }
        $indicatorAvgScore = $totalWeight > 0 ? round($weightedStarSum / $totalWeight, 4) : null;
        $criteriaScore     = $indicatorAvgScore !== null ? round($indicatorAvgScore * 20, 4) : 0.0;

        if (! Schema::hasTable('appraisal_component_scores')) {
            $finalScore = $criteriaScore;
        } else {
            $appraisal->loadMissing('period');
            $wCfg = AppraisalWeightConfig::loadFor($appraisal->period?->type);
            $w    = $wCfg->toWeightsArray();

            $componentRows = DB::table('appraisal_component_scores')
                ->where('appraisal_id', $appraisal->id)
                ->whereIn('component_key', ['kpi', 'training', 'competency_skill', 'competency_position'])
                ->get()
                ->keyBy('component_key');

            $kpiScoreVal = (($appraisal->enable_kpi_component ?? true) && isset($componentRows['kpi']))
                ? (float) ($componentRows['kpi']->score_normalized ?? 0) : null;
            $trainScore = isset($componentRows['training'])
                ? (float) ($componentRows['training']->score_normalized ?? 0) : null;
            $skillScore = ($appraisal->enable_skill_component === true && isset($componentRows['competency_skill']))
                ? (float) ($componentRows['competency_skill']->score_normalized ?? 0) : null;
            $posScore = ($appraisal->enable_position_component === true && isset($componentRows['competency_position']))
                ? (float) ($componentRows['competency_position']->score_normalized ?? 0) : null;

            $weightedSum = $criteriaScore * $w['criteria'];
            $totalW      = $w['criteria'];

            if ($kpiScoreVal !== null && $w['kpi'] > 0) {
                $weightedSum += $kpiScoreVal * $w['kpi'];
                $totalW      += $w['kpi'];
            }
            if ($trainScore !== null && $w['training'] > 0) {
                $weightedSum += $trainScore * $w['training'];
                $totalW      += $w['training'];
            }
            if ($skillScore !== null && $w['skill'] > 0) {
                $weightedSum += $skillScore * $w['skill'];
                $totalW      += $w['skill'];
            }
            if ($posScore !== null && $w['position'] > 0) {
                $weightedSum += $posScore * $w['position'];
                $totalW      += $w['position'];
            }

            $finalScore = $totalW > 0 ? round($weightedSum / $totalW, 2) : 0.0;
        }

        if (! $dryRun) {
            $appraisal->update([
                'final_score'  => $finalScore,
                'final_result' => $finalScore > 0 ? $this->scoreToResult($finalScore / 20) : $appraisal->final_result,
            ]);
        }

        return $finalScore;
    }

    private function scoreToResult(float $avgOn5Scale): string
    {
        return \App\Support\AppraisalGrading::classify($avgOn5Scale);
    }
}
