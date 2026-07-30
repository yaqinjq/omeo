<?php

namespace App\Console\Commands;

use App\Models\Appraisal;
use App\Support\AppraisalGrading;
use Illuminate\Console\Command;

class BackfillAppraisalFinalResultCommand extends Command
{
    protected $signature = 'appraisal:backfill-final-result {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Hitung ulang final_result appraisal lama (approved/submitted) ke skema klasifikasi 6-tingkat baru (Outstanding/Exceed Expectation/dst) berdasarkan final_score yang sudah tersimpan';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $appraisals = Appraisal::query()
            ->whereIn('status', ['submitted', 'approved'])
            ->whereNotNull('final_score')
            ->where('final_score', '>', 0)
            ->get();

        if ($appraisals->isEmpty()) {
            $this->warn('Tidak ada appraisal dengan final_score untuk diproses.');

            return 0;
        }

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$appraisals->count()} appraisal...");

        $updated = 0;
        $unchanged = 0;

        foreach ($appraisals as $appraisal) {
            // final_score historically stored on two different scales depending on
            // whether the multi-component formula was active at submit time:
            // 1-5 (star average only) or 0-100 (weighted with KPI/training/etc).
            // Normalize back to the 1-5 scale AppraisalGrading expects.
            $score5 = (float) $appraisal->final_score > 5
                ? (float) $appraisal->final_score / 20
                : (float) $appraisal->final_score;

            $newResult = AppraisalGrading::classify($score5);

            if ($newResult === $appraisal->final_result) {
                $unchanged++;
                continue;
            }

            $this->line("#{$appraisal->id} {$appraisal->employee?->full_name}: '{$appraisal->final_result}' -> '{$newResult}' (score5={$score5})");

            if (! $isDryRun) {
                $appraisal->update(['final_result' => $newResult]);
            }

            $updated++;
        }

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Selesai. Diupdate: {$updated}, tidak berubah: {$unchanged}.");

        return 0;
    }
}
