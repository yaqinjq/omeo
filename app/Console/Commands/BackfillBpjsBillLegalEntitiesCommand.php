<?php

namespace App\Console\Commands;

use App\Models\BpjsOfficialBill;
use App\Services\Finance\BpjsLegalEntityResolverService;
use Illuminate\Console\Command;

class BackfillBpjsBillLegalEntitiesCommand extends Command
{
    protected $signature = 'bpjs:backfill-legal-entities {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Resolve/auto-create legal_entity_id untuk bpjs_official_bills lama yang npp-nya sudah terparsing tapi belum ter-link ke Legal Entity';

    public function handle(BpjsLegalEntityResolverService $resolver): int
    {
        $isDryRun = $this->option('dry-run');

        $bills = BpjsOfficialBill::whereNull('legal_entity_id')
            ->whereNotNull('npp')
            ->where('npp', '!=', '')
            ->get();

        if ($bills->isEmpty()) {
            $this->info('Tidak ada bill yang perlu di-backfill.');

            return 0;
        }

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$bills->count()} bill...");

        $updated = 0;
        foreach ($bills as $bill) {
            $this->line("  Bill #{$bill->id} ({$bill->source_file_name}) — NPP {$bill->npp} / {$bill->nama_perusahaan}");

            if ($isDryRun) {
                continue;
            }

            $legalEntity = $resolver->resolveOrCreate($bill->npp, $bill->nama_perusahaan);
            if ($legalEntity) {
                $bill->update(['legal_entity_id' => $legalEntity->id]);
                $this->line("    -> legal_entity_id = {$legalEntity->id} ({$legalEntity->name})");
                $updated++;
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        } else {
            $this->info("Selesai. {$updated} bill di-update.");
        }

        return 0;
    }
}
