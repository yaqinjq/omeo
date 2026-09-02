<?php

namespace App\Console\Commands;

use App\Models\Appraisal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateAppraisalEvaluatorsCommand extends Command
{
    protected $signature = 'appraisals:fix-duplicate-evaluators
                            {--force : Jalankan penghapusan sungguhan (tanpa ini, cuma laporan dry-run)}';

    protected $description = 'Audit & bersihkan baris appraisal duplikat (evaluator+karyawan+periode sama) akibat race condition saat generate batch';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        // appraiser_id IS NULL sengaja dikecualikan — GROUP BY tidak bisa
        // membedakan "banyak baris NULL" dari "beberapa evaluator berbeda yang
        // sama-sama gagal ter-match ke user" (mis. hasil migrasi historis MEO
        // yang username-nya tidak cocok akun manapun). Constraint unique di DB
        // juga tidak menganggap NULL sebagai duplikat satu sama lain, jadi
        // baris-baris ini memang bukan masalah yang perlu dibersihkan di sini.
        $dupeGroups = DB::table('appraisals')
            ->select('appraisal_period_id', 'employee_id', 'appraiser_id', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('appraiser_id')
            ->groupBy('appraisal_period_id', 'employee_id', 'appraiser_id')
            ->having('jumlah', '>', 1)
            ->get();

        if ($dupeGroups->isEmpty()) {
            $this->info('Tidak ada duplikat ditemukan.');
            return self::SUCCESS;
        }

        $this->info(($force ? 'MODE SUNGGUHAN' : 'MODE DRY-RUN (belum ada yang dihapus)') . " — {$dupeGroups->count()} grup duplikat ditemukan.\n");

        $safeCount = 0;
        $reviewCount = 0;
        $deletedRows = 0;

        foreach ($dupeGroups as $group) {
            $rows = Appraisal::query()
                ->where('appraisal_period_id', $group->appraisal_period_id)
                ->where('employee_id', $group->employee_id)
                ->where('appraiser_id', $group->appraiser_id)
                ->orderBy('id')
                ->get();

            $employeeName  = DB::table('employees')->where('id', $group->employee_id)->value('full_name') ?? "#{$group->employee_id}";
            $evaluatorName = DB::table('users')->where('id', $group->appraiser_id)->value('name') ?? "#{$group->appraiser_id}";
            $periodLabel   = DB::table('appraisal_periods')->where('id', $group->appraisal_period_id)->value('name') ?? "#{$group->appraisal_period_id}";

            $filled = $rows->filter(fn ($a) => in_array($a->status, ['submitted', 'approved'], true));

            $header = "[{$employeeName}] evaluator={$evaluatorName} periode={$periodLabel} — {$rows->count()} baris ({$filled->count()} submitted/approved)";

            if ($filled->count() > 1 && ! $this->isExactDuplicateContent($filled)) {
                // Butuh keputusan manusia — beberapa submission asli dengan isi berbeda.
                $reviewCount++;
                $this->warn("BUTUH REVIEW  {$header}");
                foreach ($rows as $a) {
                    $detailCount = DB::table('appraisal_details')->where('appraisal_id', $a->id)->count();
                    $this->line("    id={$a->id} status={$a->status} final_score={$a->final_score} proposed_status={$a->proposed_status} jumlah_kriteria_diisi={$detailCount} submitted_at={$a->submitted_at}");
                }
                continue;
            }

            // Aman: paling banyak 1 baris submitted/approved (biasa), ATAU
            // beberapa baris submitted/approved tapi ISINYA BYTE-IDENTICAL
            // (final_score, narasi, dan tiap skor kriteria persis sama —
            // pola khas bug JOIN fan-out saat migrasi historis MEO, bukan
            // evaluasi asli yang berbeda). Survivor = baris paling lama.
            $survivor = $filled->sortBy('id')->first() ?? $rows->sortBy('id')->first();
            $losers   = $rows->reject(fn ($a) => $a->id === $survivor->id);

            $safeCount++;
            $this->info("AMAN          {$header} -> simpan id={$survivor->id} ({$survivor->status}), hapus id=" . $losers->pluck('id')->implode(','));

            if ($force) {
                DB::transaction(function () use ($losers, &$deletedRows) {
                    foreach ($losers as $loser) {
                        DB::table('appraisal_details')->where('appraisal_id', $loser->id)->delete();
                        DB::table('appraisal_component_scores')->where('appraisal_id', $loser->id)->delete();
                        DB::table('appraisal_invitation_logs')->where('appraisal_id', $loser->id)->delete();
                        if (\Illuminate\Support\Facades\Schema::hasTable('appraisal_edit_requests')) {
                            DB::table('appraisal_edit_requests')->where('appraisal_id', $loser->id)->delete();
                        }
                        Appraisal::where('id', $loser->id)->delete();
                        $deletedRows++;
                    }
                });
            } else {
                $deletedRows += $losers->count();
            }
        }

        $this->newLine();
        $this->info("Ringkasan: {$safeCount} grup aman (" . ($force ? "{$deletedRows} baris dihapus" : "{$deletedRows} baris AKAN dihapus kalau --force") . "), {$reviewCount} grup butuh review manual (tidak disentuh).");

        if (! $force && $safeCount > 0) {
            $this->comment('Jalankan ulang dengan --force untuk benar-benar menghapus baris duplikat yang AMAN di atas.');
        }

        return self::SUCCESS;
    }

    /**
     * True kalau SEMUA baris submitted/approved di grup ini punya isi
     * byte-identical: final_score, ketiga kolom narasi, DAN setiap skor per
     * kriteria (bukan cuma rata-ratanya). Dipakai untuk membedakan bug JOIN
     * fan-out migrasi (aman digabung ke 1 baris) dari evaluasi asli berbeda
     * yang butuh keputusan manusia (mis. kasus Anugrah Budiamin di Henry).
     */
    private function isExactDuplicateContent($filledRows): bool
    {
        $signatures = $filledRows->map(function ($a) {
            $scores = DB::table('appraisal_details')
                ->where('appraisal_id', $a->id)
                ->orderBy('appraisal_indicator_id')
                ->pluck('score', 'appraisal_indicator_id');

            return json_encode([
                'final_score' => $a->final_score,
                'strengths'   => $a->feedback_strengths,
                'improvements'=> $a->feedback_improvements,
                'notes'       => $a->feedback_notes,
                'scores'      => $scores,
            ]);
        })->unique();

        return $signatures->count() === 1;
    }
}
