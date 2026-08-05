<?php

namespace App\Console\Commands;

use App\Services\Finance\BpjsEmployeeAssignmentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEmployeeBpjsAssignmentsCommand extends Command
{
    protected $signature = 'bpjs:backfill-employee-assignments {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Backfill employee_bpjs_assignments dari bpjs_official_bill_rows yang sudah ter-reconcile (match_status auto/manual_confirmed) dan bill-nya sudah punya legal_entity_id. Sejak fitur auto-sync ditambahkan, command ini hanya perlu dipakai untuk data lama / audit ulang — bill baru sudah otomatis tersinkron begitu review dikonfirmasi.';

    public function __construct(private BpjsEmployeeAssignmentSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $rows = DB::table('bpjs_official_bill_rows as r')
            ->join('bpjs_official_bills as b', 'b.id', '=', 'r.bill_id')
            ->whereIn('r.match_status', ['auto', 'manual_confirmed'])
            ->whereNotNull('r.employee_id')
            ->whereNotNull('b.legal_entity_id')
            ->select('r.employee_id', 'b.legal_entity_id', 'b.periode')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada baris ter-reconcile dengan legal_entity_id terisi. Jalankan bpjs:backfill-legal-entities dulu kalau belum.');

            return 0;
        }

        // Group per employee + legal_entity, ambil periode paling awal sebagai effective_date
        $grouped = $rows->groupBy(fn ($r) => $r->employee_id . '|' . $r->legal_entity_id);

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$grouped->count()} kombinasi karyawan x PT dari {$rows->count()} baris ter-reconcile...");

        $created = 0;
        $skipped = 0;

        foreach ($grouped as $group) {
            $first = $group->first();
            $employeeId = $first->employee_id;
            $legalEntityId = $first->legal_entity_id;
            $earliestPeriode = $group->min('periode');
            $effectiveDate = $earliestPeriode . '-01';

            if ($isDryRun) {
                // Dry-run tidak boleh menulis apa pun — cek exists manual di sini
                // saja, jangan panggil ensureAssignment() (yang selalu menyimpan).
                $exists = \App\Models\EmployeeBpjsAssignment::where('employee_id', $employeeId)
                    ->where('legal_entity_id', $legalEntityId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $this->line("  CREATE: employee_id={$employeeId} -> legal_entity_id={$legalEntityId}, effective_date={$effectiveDate} ({$group->count()} baris tagihan)");
                $created++;
                continue;
            }

            $wasCreated = $this->syncService->ensureAssignment($employeeId, $legalEntityId, $effectiveDate);

            if (! $wasCreated) {
                $skipped++;
                continue;
            }

            $this->line("  CREATE: employee_id={$employeeId} -> legal_entity_id={$legalEntityId}, effective_date={$effectiveDate} ({$group->count()} baris tagihan)");
            $created++;
        }

        $this->newLine();
        $this->info("Dibuat: {$created} | Dilewati (sudah ada): {$skipped}");

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
