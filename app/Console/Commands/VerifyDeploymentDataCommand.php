<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyDeploymentDataCommand extends Command
{
    protected $signature = 'deploy:verify-data';

    protected $description = 'Cek status data untuk setiap langkah backfill/merge (departemen, BPJS legal entity, outlet, Sprint K) — read-only, aman dijalankan kapan saja';

    private const MISSING_OUTLET_NAMES = [
        'AH PEK KOPITIAM BINTARO',
        'AH PEK KOPITIAM BENHILL',
        'AH PEK KOPITIAM MARGONDA',
        'AH PEK KOPITIAM PAKUWON CITY MALL',
    ];

    private const DUPLICATE_DEPT_CODES = ['HRD', 'FA', 'PUR', 'DM'];
    private const NEST_DEPT_CODES = ['BAR', 'KOT', 'KPR', 'OPROD', 'SERV', 'MAIN'];

    public function handle(): int
    {
        $this->info('== 1. DEPARTEMEN (departments:merge-duplicates) ==');
        $mgr005 = DB::table('departments')->where('code', 'MGR005')->first();
        $dupStillExists = DB::table('departments')->whereIn('code', self::DUPLICATE_DEPT_CODES)->whereNull('deleted_at')->count();
        $nestDone = DB::table('departments')->whereIn('code', self::NEST_DEPT_CODES)
            ->when($mgr005, fn ($q) => $q->where('parent_id', $mgr005->id))
            ->count();
        $this->line("  Duplikat (HRD/FA/PUR/DM) yang MASIH aktif (belum di-merge): {$dupStillExists} / 4" . ($dupStillExists === 0 ? ' -> SUDAH BERES' : ' -> BELUM SELESAI, jalankan departments:merge-duplicates'));
        $this->line("  Unit operasional (BAR/KOT/dst) yang sudah nested ke MGR005: {$nestDone} / 6" . ($nestDone === 6 ? ' -> SUDAH BERES' : ' -> BELUM SELESAI'));

        $this->newLine();
        $this->info('== 2. BPJS LEGAL ENTITY (bpjs:backfill-legal-entities) ==');
        $billsTotal = DB::table('bpjs_official_bills')->count();
        $billsNoEntity = DB::table('bpjs_official_bills')->whereNull('legal_entity_id')->whereNotNull('npp')->where('npp', '!=', '')->count();
        $legalEntityCount = DB::table('legal_entities')->count();
        $this->line("  Total bpjs_official_bills: {$billsTotal}");
        $this->line("  Bill dengan NPP tapi legal_entity_id masih kosong: {$billsNoEntity}" . ($billsNoEntity === 0 ? ' -> SUDAH BERES' : ' -> BELUM SELESAI, jalankan bpjs:backfill-legal-entities'));
        $this->line("  Total legal_entities sekarang: {$legalEntityCount}");

        $this->newLine();
        $this->info('== 3. EMPLOYEE BPJS ASSIGNMENTS (bpjs:backfill-employee-assignments) ==');
        $ebaCount = DB::table('employee_bpjs_assignments')->count();
        $reconciledUnbackfilled = DB::table('bpjs_official_bill_rows as r')
            ->join('bpjs_official_bills as b', 'b.id', '=', 'r.bill_id')
            ->whereIn('r.match_status', ['auto', 'manual_confirmed'])
            ->whereNotNull('r.employee_id')
            ->whereNotNull('b.legal_entity_id')
            ->count();
        $this->line("  Total employee_bpjs_assignments: {$ebaCount}");
        $this->line("  Baris reconciled siap di-backfill (indikatif, termasuk yg sudah pernah): {$reconciledUnbackfilled}");
        $this->line('  (Kalau angka #2 di atas sudah "SUDAH BERES" dan angka ini > 0 tapi assignments masih sedikit, aman jalankan ulang bpjs:backfill-employee-assignments — command ini idempotent, tidak akan duplikat.)');

        $this->newLine();
        $this->info('== 4. OUTLET YANG BELUM TER-MAPPING (outlets:seed-missing-from-payroll) ==');
        $nullOutletId = DB::table('finance_bpjs_records')->whereNull('outlet_id')->count();
        $this->line("  finance_bpjs_records.outlet_id yang masih NULL: {$nullOutletId}" . ($nullOutletId === 0 ? ' -> SUDAH BERES' : ' -> BELUM SELESAI, jalankan outlets:seed-missing-from-payroll'));
        foreach (self::MISSING_OUTLET_NAMES as $name) {
            $exists = DB::table('outlets')->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
            $this->line('    - ' . $name . ': ' . ($exists ? 'ADA di Master Outlet' : 'BELUM ADA'));
        }

        $this->newLine();
        $this->info('== 5. SPRINT K — EMPLOYEE OUTLET ASSIGNMENTS (employee-outlet-assignments:backfill) ==');
        if (! Schema::hasTable('employee_outlet_assignments')) {
            $this->error('  Tabel employee_outlet_assignments BELUM ADA. Jalankan: php artisan migrate');
        } else {
            $eoaCount = DB::table('employee_outlet_assignments')->count();
            $sourceCombos = DB::table('finance_bpjs_records as fbr')
                ->join('employees as e', function ($join) {
                    $join->whereRaw('CAST(fbr.no_komp AS UNSIGNED) = CAST(e.nokom AS UNSIGNED)');
                })
                ->whereNotNull('fbr.outlet_id')
                ->whereNull('fbr.deleted_at')
                ->select('e.id', 'fbr.outlet_id')
                ->distinct()
                ->count();
            $this->line("  Total employee_outlet_assignments: {$eoaCount}");
            $this->line("  Kombinasi karyawan x outlet unik di sumber payroll: {$sourceCombos}");
            $this->line('  (Kalau #4 di atas belum "SUDAH BERES", jalankan outlets:seed-missing-from-payroll DULU baru ulangi employee-outlet-assignments:backfill supaya cakupannya lengkap — aman diulang, idempotent.)');
        }

        $this->newLine();
        $this->info('== RINGKASAN ==');
        $steps = [
            'Departemen' => $dupStillExists === 0 && $nestDone === 6,
            'BPJS Legal Entity' => $billsNoEntity === 0,
            'Outlet mapping' => $nullOutletId === 0,
            'Sprint K table ada' => Schema::hasTable('employee_outlet_assignments'),
        ];
        foreach ($steps as $label => $done) {
            $this->line('  ' . ($done ? '[SELESAI]' : '[BELUM]  ') . " {$label}");
        }

        return 0;
    }
}
