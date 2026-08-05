<?php

namespace App\Console\Commands;

use App\Models\AppraisalIndicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeAppraisalIndicatorCommand extends Command
{
    protected $signature = 'appraisal:merge-indicator
        {loser : ID kriteria yang akan dihapus}
        {survivor : ID kriteria yang dipertahankan, semua histori penilaian loser dipindah ke sini}
        {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Gabungkan dua kriteria appraisal (appraisal_indicators) yang duplikat: pindahkan semua appraisal_details dari loser ke survivor, lalu hapus loser. Dipakai manual per pasangan setelah dikonfirmasi lewat appraisal:inventory-criteria-duplicates — TIDAK menebak sendiri pasangan mana yang digabung.';

    public function handle(): int
    {
        $isDryRun  = $this->option('dry-run');
        $loserId   = (int) $this->argument('loser');
        $survivorId = (int) $this->argument('survivor');

        if ($loserId === $survivorId) {
            $this->error('ID loser dan survivor tidak boleh sama.');
            return 1;
        }

        $loser    = AppraisalIndicator::find($loserId);
        $survivor = AppraisalIndicator::find($survivorId);

        if (! $loser || ! $survivor) {
            $this->error('ID loser atau survivor tidak ditemukan di appraisal_indicators.');
            return 1;
        }

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Menggabungkan kriteria #{$loserId} \"{$loser->question}\" (template {$loser->template_id}) → #{$survivorId} \"{$survivor->question}\" (template {$survivor->template_id})");

        // Kalau seorang karyawan sudah punya baris appraisal_details untuk
        // KEDUA indicator ini di appraisal yang sama (skor loser dan skor
        // survivor sudah ada bersamaan), pindah-paksa loser -> survivor akan
        // bentrok / menimpa salah satu skor secara diam-diam. Deteksi dulu,
        // jangan menebak mana yang benar.
        $collisions = DB::table('appraisal_details as loser_d')
            ->join('appraisal_details as survivor_d', function ($join) use ($loserId, $survivorId) {
                $join->on('loser_d.appraisal_id', '=', 'survivor_d.appraisal_id')
                     ->where('survivor_d.appraisal_indicator_id', '=', $survivorId);
            })
            ->where('loser_d.appraisal_indicator_id', $loserId)
            ->select('loser_d.appraisal_id', 'loser_d.score as loser_score', 'survivor_d.score as survivor_score')
            ->get();

        if ($collisions->isNotEmpty()) {
            $this->error("Ditemukan {$collisions->count()} appraisal yang SUDAH punya skor di kedua kriteria ini sekaligus — tidak bisa digabung otomatis, akan menimpa salah satu skor secara diam-diam:");
            foreach ($collisions as $c) {
                $this->line("  appraisal_id={$c->appraisal_id} | skor kriteria loser={$c->loser_score} | skor kriteria survivor={$c->survivor_score}");
            }
            $this->warn('Selesaikan bentrokan ini manual dulu (putuskan skor mana yang benar per appraisal di atas) sebelum menjalankan command ini lagi.');
            return 1;
        }

        $affectedCount = DB::table('appraisal_details')->where('appraisal_indicator_id', $loserId)->count();
        $this->line("  {$affectedCount} baris appraisal_details akan dipindah dari kriteria #{$loserId} ke #{$survivorId}.");

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
            return 0;
        }

        DB::transaction(function () use ($loserId, $survivorId, $loser) {
            DB::table('appraisal_details')
                ->where('appraisal_indicator_id', $loserId)
                ->update(['appraisal_indicator_id' => $survivorId]);

            $loser->delete();
        });

        $this->info("Selesai. Kriteria #{$loserId} dihapus, {$affectedCount} baris penilaian dipindah ke #{$survivorId}.");

        return 0;
    }
}
