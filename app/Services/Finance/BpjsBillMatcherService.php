<?php

namespace App\Services\Finance;

use App\Models\BpjsOfficialBill;
use App\Models\BpjsOfficialBillRow;
use Illuminate\Support\Facades\DB;

class BpjsBillMatcherService
{
    /**
     * Match all pending rows in a bill by NIK exact match only.
     * Returns stats: ['auto' => n, 'not_found' => n]
     */
    public function matchBill(BpjsOfficialBill $bill): array
    {
        $stats = ['auto' => 0, 'not_found' => 0];

        $rows = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->where('match_status', 'pending')
            ->get();

        foreach ($rows as $row) {
            $result = $this->matchEmployee((string) ($row->nik ?? ''));
            $row->update([
                'match_status'     => $result['match_status'],
                'employee_id'      => $result['employee_id'],
                'match_confidence' => $result['match_confidence'],
                'match_note'       => $result['match_note'],
            ]);
            $stats[$result['match_status']] = ($stats[$result['match_status']] ?? 0) + 1;
        }

        $matched = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->whereIn('match_status', ['auto', 'manual_confirmed'])->count();
        $unmatched = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->whereIn('match_status', ['not_found', 'flagged'])->count();

        $bill->update([
            'matched_count'   => $matched,
            'unmatched_count' => $unmatched,
            'status'          => 'matched',
        ]);

        return $stats;
    }

    /**
     * Reset all 'flagged' rows in a bill where the assigned employee's NIK
     * does not match the NIK in the bill row.
     * Returns count of rows reset.
     */
    public function cleanupFlaggedMismatches(int $billId): int
    {
        $flaggedRows = DB::table('bpjs_official_bill_rows')
            ->where('bill_id', $billId)
            ->where('match_status', 'flagged')
            ->whereNotNull('employee_id')
            ->get(['id', 'nik', 'employee_id']);

        $resetCount = 0;

        foreach ($flaggedRows as $row) {
            $nikClean = preg_replace('/[\s\-]/', '', $row->nik ?? '');

            $emp = DB::table('employees')
                ->where('id', $row->employee_id)
                ->first(['id', 'nik']);

            if (! $emp) {
                DB::table('bpjs_official_bill_rows')
                    ->where('id', $row->id)
                    ->update([
                        'match_status'     => 'not_found',
                        'employee_id'      => null,
                        'match_confidence' => 0,
                        'match_note'       => 'Reset: employee tidak ditemukan',
                    ]);
                $resetCount++;
                continue;
            }

            $empNikClean = preg_replace('/[\s\-]/', '', $emp->nik ?? '');

            if ($nikClean !== $empNikClean) {
                DB::table('bpjs_official_bill_rows')
                    ->where('id', $row->id)
                    ->update([
                        'match_status'     => 'not_found',
                        'employee_id'      => null,
                        'match_confidence' => 0,
                        'match_note'       => 'Reset: NIK tidak cocok dengan employee yang di-assign',
                    ]);
                $resetCount++;
            }
        }

        // Reset remaining flagged rows that have no employee_id
        $resetCount += DB::table('bpjs_official_bill_rows')
            ->where('bill_id', $billId)
            ->where('match_status', 'flagged')
            ->update([
                'match_status'     => 'not_found',
                'employee_id'      => null,
                'match_confidence' => 0,
                'match_note'       => 'Reset: status flagged tidak lagi digunakan',
            ]);

        return $resetCount;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function matchEmployee(string $nik): array
    {
        $nikClean = preg_replace('/[\s\-]/', '', $nik);

        if (empty($nikClean)) {
            return [
                'employee_id'      => null,
                'match_status'     => 'not_found',
                'match_confidence' => 0,
                'match_note'       => 'NIK kosong di tagihan BPJS',
            ];
        }

        $employee = DB::table('employees')
            ->whereRaw("REPLACE(REPLACE(nik, ' ', ''), '-', '') = ?", [$nikClean])
            ->whereNull('deleted_at')
            ->first(['id', 'nik', 'full_name', 'employee_number']);

        if ($employee) {
            return [
                'employee_id'      => $employee->id,
                'match_status'     => 'auto',
                'match_confidence' => 100.00,
                'match_note'       => 'NIK match exact: ' . $employee->nik,
            ];
        }

        return [
            'employee_id'      => null,
            'match_status'     => 'not_found',
            'match_confidence' => 0,
            'match_note'       => 'NIK ' . $nik . ' tidak ditemukan di database OMEO',
        ];
    }
}
