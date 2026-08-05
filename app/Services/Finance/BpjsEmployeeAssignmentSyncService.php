<?php

namespace App\Services\Finance;

use App\Models\BpjsOfficialBill;
use App\Models\BpjsOfficialBillRow;
use App\Models\EmployeeBpjsAssignment;

class BpjsEmployeeAssignmentSyncService
{
    /**
     * Sinkron employee_bpjs_assignments dari baris tagihan yang sudah ter-match
     * (auto/manual_confirmed) pada SATU bill. Dipanggil otomatis setiap kali
     * matching sebuah bill selesai/berubah (confirm review, rematch, manual
     * confirm), supaya tab Cross-Billing selalu up to date tanpa perlu jalankan
     * command manual di server.
     *
     * Insert-only: tidak pernah mengubah/menghapus assignment yang sudah ada,
     * hanya menambah relasi karyawan-PT yang belum pernah tercatat.
     */
    public function syncBill(BpjsOfficialBill $bill): array
    {
        if (! $bill->legal_entity_id) {
            return ['created' => 0, 'skipped' => 0];
        }

        $employeeIds = BpjsOfficialBillRow::where('bill_id', $bill->id)
            ->whereIn('match_status', ['auto', 'manual_confirmed'])
            ->whereNotNull('employee_id')
            ->distinct()
            ->pluck('employee_id');

        $effectiveDate = $bill->periode . '-01';

        $created = 0;
        $skipped = 0;

        foreach ($employeeIds as $employeeId) {
            if ($this->ensureAssignment((int) $employeeId, (int) $bill->legal_entity_id, $effectiveDate)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Buat employee_bpjs_assignments untuk pasangan employee+legal_entity kalau
     * belum ada. Return true kalau baru dibuat, false kalau sudah ada (dilewati).
     * Tidak pernah meng-update baris yang sudah ada — sengaja insert-only supaya
     * aman dijalankan berulang kali tanpa risiko menimpa data yang sudah benar.
     */
    public function ensureAssignment(int $employeeId, int $legalEntityId, string $effectiveDate, string $notes = 'Auto-backfill dari data rekonsiliasi BPJS (bpjs_official_bill_rows).'): bool
    {
        $exists = EmployeeBpjsAssignment::where('employee_id', $employeeId)
            ->where('legal_entity_id', $legalEntityId)
            ->exists();

        if ($exists) {
            return false;
        }

        EmployeeBpjsAssignment::create([
            'employee_id'     => $employeeId,
            'legal_entity_id' => $legalEntityId,
            'effective_date'  => $effectiveDate,
            'reason'          => 'join',
            'notes'           => $notes,
        ]);

        return true;
    }
}
