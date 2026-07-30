<?php

namespace App\Console\Commands;

use App\Models\EmployeeOutletAssignment;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEmployeeOutletAssignmentsFromPayrollCommand extends Command
{
    protected $signature = 'employee-outlet-assignments:backfill {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Backfill employee_outlet_assignments (Sprint K) dari finance_bpjs_records (outlet + departemen dari CSV payroll yang sudah diimport)';

    private const NOTE_CLEAN = 'Auto-backfill dari data payroll CSV (finance_bpjs_records).';

    private const NOTE_MULTI = 'Auto-backfill dari payroll CSV — karyawan terdeteksi kerja di lebih dari 1 outlet pada periode ini. '
        . 'Outlet primary dipilih otomatis berdasarkan jumlah baris payroll terbanyak. Mohon direview & disesuaikan oleh HRD.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $rows = DB::table('finance_bpjs_records as fbr')
            ->join('employees as e', function ($join) {
                $join->whereRaw('CAST(fbr.no_komp AS UNSIGNED) = CAST(e.nokom AS UNSIGNED)');
            })
            ->whereNotNull('fbr.outlet_id')
            ->whereNull('fbr.deleted_at')
            ->select('e.id as employee_id', 'fbr.outlet_id', 'fbr.sub_dept', 'fbr.periode', 'fbr.gaji_pokok')
            ->get();

        $skippedNoOutletId = DB::table('finance_bpjs_records')->whereNull('outlet_id')->count();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada baris finance_bpjs_records dengan outlet_id terisi untuk dijadikan sumber backfill.');

            return 0;
        }

        $legalEntityByOutlet = Outlet::pluck('legal_entity_id', 'id');
        $byEmployee = $rows->groupBy('employee_id');

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Memproses {$byEmployee->count()} karyawan dari {$rows->count()} baris payroll...");

        $createdPrimary = 0;
        $createdSecondary = 0;
        $skippedExisting = 0;

        DB::beginTransaction();

        try {
        foreach ($byEmployee as $employeeId => $empRows) {
            $byPeriode = $empRows->groupBy('periode')->sortKeys();

            // Peringkat outlet per periode: outlet dengan baris payroll terbanyak (tie-break: total gaji_pokok) jadi primary
            $periodeSummary = $byPeriode->map(function ($periodeRows) {
                return $periodeRows->groupBy('outlet_id')->map(function ($group, $outletId) {
                    return (object) [
                        'outlet_id' => (int) $outletId,
                        'row_count' => $group->count(),
                        'gaji_sum'  => (float) $group->sum('gaji_pokok'),
                        'sub_dept'  => $group->pluck('sub_dept')->filter()->first(),
                    ];
                })->sort(function ($a, $b) {
                    return $b->row_count <=> $a->row_count
                        ?: $b->gaji_sum <=> $a->gaji_sum
                        ?: $a->outlet_id <=> $b->outlet_id;
                })->values();
            });

            // Rangkai span primary (gabungkan periode berurutan dengan outlet primary yang sama)
            $spans = [];
            $current = null;
            foreach ($periodeSummary as $periode => $ranked) {
                $primary = $ranked->first();
                if ($current && $current['outlet_id'] === $primary->outlet_id) {
                    $current['end_periode'] = $periode;
                } else {
                    if ($current) {
                        $spans[] = $current;
                    }
                    $current = [
                        'outlet_id'     => $primary->outlet_id,
                        'dept'          => $primary->sub_dept,
                        'start_periode' => $periode,
                        'end_periode'   => $periode,
                        'is_multi'      => $ranked->count() > 1,
                    ];
                }
            }
            if ($current) {
                $spans[] = $current;
            }

            foreach ($spans as $i => $span) {
                $outletId = $span['outlet_id'];
                $effectiveDate = $span['start_periode'] . '-01';
                $endDate = isset($spans[$i + 1]) ? $spans[$i + 1]['start_periode'] . '-01' : null;

                $exists = EmployeeOutletAssignment::where('employee_id', $employeeId)
                    ->where('outlet_id', $outletId)
                    ->where('effective_date', $effectiveDate)
                    ->exists();

                if ($exists) {
                    $skippedExisting++;
                    continue;
                }

                $this->line("  PRIMARY: employee_id={$employeeId} -> outlet_id={$outletId}, dept={$span['dept']}, effective_date={$effectiveDate}"
                    . ($endDate ? ", end_date={$endDate}" : ' (masih aktif)')
                    . ($span['is_multi'] ? ' [multi-outlet]' : ''));

                if (! $isDryRun) {
                    EmployeeOutletAssignment::create([
                        'employee_id'       => $employeeId,
                        'outlet_id'         => $outletId,
                        'payroll_outlet_id' => $outletId,
                        'legal_entity_id'   => $legalEntityByOutlet[$outletId] ?? null,
                        'department'        => $span['dept'],
                        'effective_date'    => $effectiveDate,
                        'end_date'          => $endDate,
                        'assignment_type'   => 'primary',
                        'notes'             => $span['is_multi'] ? self::NOTE_MULTI : self::NOTE_CLEAN,
                    ]);
                }

                $createdPrimary++;
            }

            // Assignment sekunder: outlet lain yang dipakai bersamaan di periode yang sama, dibatasi hanya di bulan itu
            foreach ($periodeSummary as $periode => $ranked) {
                foreach ($ranked->slice(1) as $secondary) {
                    $outletId = $secondary->outlet_id;
                    $effectiveDate = $periode . '-01';
                    $endDate = Carbon::parse($effectiveDate)->addMonth()->format('Y-m-d');

                    $exists = EmployeeOutletAssignment::where('employee_id', $employeeId)
                        ->where('outlet_id', $outletId)
                        ->where('assignment_type', 'secondary')
                        ->where('effective_date', $effectiveDate)
                        ->exists();

                    if ($exists) {
                        $skippedExisting++;
                        continue;
                    }

                    $this->line("  SECONDARY: employee_id={$employeeId} -> outlet_id={$outletId}, dept={$secondary->sub_dept}, {$effectiveDate} s/d {$endDate} [multi-outlet]");

                    if (! $isDryRun) {
                        EmployeeOutletAssignment::create([
                            'employee_id'       => $employeeId,
                            'outlet_id'         => $outletId,
                            'payroll_outlet_id' => $outletId,
                            'legal_entity_id'   => $legalEntityByOutlet[$outletId] ?? null,
                            'department'        => $secondary->sub_dept,
                            'effective_date'    => $effectiveDate,
                            'end_date'          => $endDate,
                            'assignment_type'   => 'secondary',
                            'notes'             => self::NOTE_MULTI,
                        ]);
                    }

                    $createdSecondary++;
                }
            }
        }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal: ' . $e->getMessage());

            return 1;
        }

        if ($isDryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        $this->newLine();
        $this->info("Primary dibuat: {$createdPrimary} | Secondary dibuat: {$createdSecondary} | Dilewati (sudah ada): {$skippedExisting}");

        if ($skippedNoOutletId > 0) {
            $this->warn("Catatan: {$skippedNoOutletId} baris finance_bpjs_records punya outlet_id kosong (outlet belum ada di Master Outlet) — karyawan terkait TIDAK ikut ter-backfill. Perlu ditambahkan dulu ke Master Outlet.");
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
