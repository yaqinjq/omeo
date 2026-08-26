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

        $dupeGroups = DB::table('appraisals')
            ->select('appraisal_period_id', 'employee_id', 'appraiser_id', DB::raw('COUNT(*) as jumlah'))
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

            if ($filled->count() > 1) {
                // Butuh keputusan manusia — beberapa submission asli dengan isi berbeda.
                $reviewCount++;
                $this->warn("BUTUH REVIEW  {$header}");
                foreach ($rows as $a) {
                    $detailCount = DB::table('appraisal_details')->where('appraisal_id', $a->id)->count();
                    $this->line("    id={$a->id} status={$a->status} final_score={$a->final_score} proposed_status={$a->proposed_status} jumlah_kriteria_diisi={$detailCount} submitted_at={$a->submitted_at}");
                }
                continue;
            }

            // Aman: paling banyak 1 baris submitted/approved. Survivor = baris
            // itu kalau ada, kalau semua masih draft pakai yang paling lama (id terkecil).
            $survivor = $filled->first() ?? $rows->first();
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
}
