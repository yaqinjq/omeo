<?php

namespace App\Services\Finance;

use App\Models\BpjsOfficialBill;
use App\Models\BpjsOfficialBillRow;
use App\Models\BpjsReconciliationResult;
use App\Models\Employee;
use App\Models\FinanceBpjsRecord;

class BpjsReconciliationService
{
    private const MINOR_THRESHOLD       = 10000;   // < Rp10.000 = minor
    private const SIGNIFICANT_THRESHOLD = 50000;   // >= Rp50.000 = significant

    /**
     * Run reconciliation for a bill.
     *
     * OMEO lookup strategy:
     *   finance_bpjs_records.employee_id is never populated by the importer,
     *   so we join via:  ltrim(employees.employee_number, '0') = finance_bpjs_records.no_komp
     */
    public function reconcile(BpjsOfficialBill $bill): array
    {
        $periode = $bill->periode;

        // ── Load OMEO records keyed by no_komp ───────────────────────────────
        // Sum across outlets: the same employee may appear in multiple outlet rows.
        // Filter out null/empty no_komp to avoid false grouping under the '' key.
        $omeoByNoKomp = FinanceBpjsRecord::where('periode', $periode)
            ->whereNotNull('no_komp')
            ->where('no_komp', '!=', '')
            ->get()
            ->groupBy('no_komp')
            ->map(function ($group) {
                $rec = $group->first();
                $rec->setAttribute('gaji_pokok',         $group->sum('gaji_pokok'));
                $rec->setAttribute('bpjs_tk_employee',   $group->sum('bpjs_tk_employee'));
                $rec->setAttribute('bpjs_tk_employer',   $group->sum('bpjs_tk_employer'));
                $rec->setAttribute('bpjs_jkes_employee', $group->sum('bpjs_jkes_employee'));
                $rec->setAttribute('bpjs_jkes_employer', $group->sum('bpjs_jkes_employer'));
                return $rec;
            });

        if ($omeoByNoKomp->isEmpty()) {
            throw new \RuntimeException(
                "Tidak ada data OMEO untuk periode {$periode}. " .
                "Import data payroll terlebih dahulu via Finance → Payroll Import."
            );
        }

        // ── Build employee_id → no_komp map ──────────────────────────────────
        // employees.employee_number may have leading zeros; no_komp is the stripped version.
        $allMatchedEmpIds = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->unique();

        $empNoKompMap = Employee::whereIn('id', $allMatchedEmpIds)
            ->whereNotNull('employee_number')
            ->get(['id', 'employee_number'])
            ->keyBy('id')
            ->map(fn ($e) => ltrim($e->employee_number, '0'));

        // ── Load matched bill rows ────────────────────────────────────────────
        $billRows = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->whereIn('match_status', ['auto', 'manual_confirmed'])
            ->get();

        $stats          = ['ok' => 0, 'selisih_minor' => 0, 'selisih_signifikan' => 0, 'perlu_review' => 0];
        $matchedNoKomps = [];

        // ── 1. Process matched bill rows ──────────────────────────────────────
        foreach ($billRows as $row) {
            $noKomp  = $row->employee_id ? $empNoKompMap->get($row->employee_id) : null;
            $omeoRec = $noKomp ? $omeoByNoKomp->get($noKomp) : null;

            $result = $this->buildResult($bill, $row, $omeoRec, $periode);
            BpjsReconciliationResult::updateOrCreate(
                ['bill_row_id' => $row->id],
                $result
            );
            $stats[$result['status']]++;

            if ($noKomp) {
                $matchedNoKomps[] = $noKomp;
            }
        }

        // ── 2. OMEO records NOT in tagihan ────────────────────────────────────
        // Employees in OMEO payroll data but absent from the official BPJS bill.
        $omeoOnly = $omeoByNoKomp->reject(fn ($_, $noKomp) => in_array($noKomp, $matchedNoKomps, true));

        foreach ($omeoOnly as $omeoRec) {
            // Skip records without an employee link — can't create a unique synthetic row
            if (! $omeoRec->employee_id) {
                continue;
            }

            // Check whether a synthetic row for this employee already exists to avoid duplicates
            $syntheticRow = BpjsOfficialBillRow::where('bill_id', $bill->id)
                ->where('employee_id', $omeoRec->employee_id)
                ->first();

            if (! $syntheticRow) {
                $syntheticRow = BpjsOfficialBillRow::create([
                    'bill_id'      => $bill->id,
                    'employee_id'  => $omeoRec->employee_id,
                    'match_status' => 'auto',
                    'nik'          => $omeoRec->nik,
                    'nama'         => $omeoRec->nama,
                    'total_iuran'  => 0,
                ]);
            }

            $result = $this->buildResultOmeoOnly($bill, $syntheticRow, $omeoRec, $periode);
            BpjsReconciliationResult::updateOrCreate(
                ['bill_row_id' => $syntheticRow->id],
                $result
            );
            $stats[$result['status']]++;
        }

        $bill->update(['status' => 'reconciled']);

        return $stats;
    }

    private function buildResult(
        BpjsOfficialBill    $bill,
        BpjsOfficialBillRow $row,
        ?FinanceBpjsRecord  $omeoRec,
        string              $periode
    ): array {
        $official = [
            'official_jkk'          => (float) $row->iuran_jkk,
            'official_jkm'          => (float) $row->iuran_jkm,
            'official_jht_employee' => (float) $row->iuran_jht_employee,
            'official_jht_employer' => (float) $row->iuran_jht_employer,
            'official_jp_employee'  => (float) $row->iuran_jp_employee,
            'official_jp_employer'  => (float) $row->iuran_jp_employer,
            'official_total'        => (float) $row->total_iuran,
        ];

        if (!$omeoRec) {
            return array_merge($official, [
                'bill_id'               => $bill->id,
                'bill_row_id'           => $row->id,
                'employee_id'           => $row->employee_id,
                'periode'               => $periode,
                'omeo_gaji_pokok'       => 0,
                'omeo_bpjs_tk_employee' => 0,
                'omeo_bpjs_tk_employer' => 0,
                'omeo_bpjs_jkes_employee' => 0,
                'omeo_bpjs_jkes_employer' => 0,
                'omeo_total'            => 0,
                'selisih_total'         => (float) $row->total_iuran,
                'penyebab'              => 'tidak_terdaftar_omeo',
                'status'                => 'perlu_review',
                'notes'                 => 'Karyawan tidak ditemukan di data OMEO periode ini.',
            ]);
        }

        $omeoTk   = (float) $omeoRec->bpjs_tk_employee  + (float) $omeoRec->bpjs_tk_employer;
        $omeoJkes = (float) $omeoRec->bpjs_jkes_employee + (float) $omeoRec->bpjs_jkes_employer;
        $omeoTotal  = $omeoTk + $omeoJkes;
        $selisih    = (float) $row->total_iuran - $omeoTotal;
        $selisihAbs = abs($selisih);

        [$status, $penyebab] = $this->classifySelisih($selisihAbs, $omeoRec);

        return array_merge($official, [
            'bill_id'                 => $bill->id,
            'bill_row_id'             => $row->id,
            'employee_id'             => $row->employee_id,
            'periode'                 => $periode,
            'omeo_gaji_pokok'         => (float) $omeoRec->gaji_pokok,
            'omeo_bpjs_tk_employee'   => (float) $omeoRec->bpjs_tk_employee,
            'omeo_bpjs_tk_employer'   => (float) $omeoRec->bpjs_tk_employer,
            'omeo_bpjs_jkes_employee' => (float) $omeoRec->bpjs_jkes_employee,
            'omeo_bpjs_jkes_employer' => (float) $omeoRec->bpjs_jkes_employer,
            'omeo_total'              => $omeoTotal,
            'selisih_total'           => $selisih,
            'penyebab'                => $penyebab,
            'status'                  => $status,
            'notes'                   => null,
        ]);
    }

    private function buildResultOmeoOnly(
        BpjsOfficialBill    $bill,
        BpjsOfficialBillRow $row,
        FinanceBpjsRecord   $omeoRec,
        string              $periode
    ): array {
        $omeoTk   = (float) $omeoRec->bpjs_tk_employee  + (float) $omeoRec->bpjs_tk_employer;
        $omeoJkes = (float) $omeoRec->bpjs_jkes_employee + (float) $omeoRec->bpjs_jkes_employer;
        $omeoTotal = $omeoTk + $omeoJkes;

        return [
            'bill_id'               => $bill->id,
            'bill_row_id'           => $row->id,
            'employee_id'           => $omeoRec->employee_id,
            'periode'               => $periode,
            'official_jkk'          => 0, 'official_jkm' => 0,
            'official_jht_employee' => 0, 'official_jht_employer' => 0,
            'official_jp_employee'  => 0, 'official_jp_employer'  => 0,
            'official_total'        => 0,
            'omeo_gaji_pokok'         => (float) $omeoRec->gaji_pokok,
            'omeo_bpjs_tk_employee'   => (float) $omeoRec->bpjs_tk_employee,
            'omeo_bpjs_tk_employer'   => (float) $omeoRec->bpjs_tk_employer,
            'omeo_bpjs_jkes_employee' => (float) $omeoRec->bpjs_jkes_employee,
            'omeo_bpjs_jkes_employer' => (float) $omeoRec->bpjs_jkes_employer,
            'omeo_total'              => $omeoTotal,
            'selisih_total'           => 0 - $omeoTotal,
            'penyebab'                => 'tidak_di_tagihan',
            'status'                  => 'perlu_review',
            'notes'                   => 'Ada di OMEO tapi tidak ada di tagihan BPJS resmi.',
        ];
    }

    private function classifySelisih(float $selisihAbs, FinanceBpjsRecord $omeoRec): array
    {
        if ($selisihAbs === 0.0) {
            return ['ok', 'ok'];
        }

        if ($selisihAbs < self::MINOR_THRESHOLD) {
            return ['selisih_minor', 'lainnya'];
        }

        if ((float) $omeoRec->gaji_pokok === 0.0) {
            return ['selisih_signifikan', 'karyawan_baru'];
        }

        if ($selisihAbs >= self::SIGNIFICANT_THRESHOLD) {
            return ['selisih_signifikan', 'beda_gaji'];
        }

        return ['perlu_review', 'lainnya'];
    }
}
