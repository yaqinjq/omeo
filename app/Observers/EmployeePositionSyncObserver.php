<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\EmployeePosition;

/**
 * employees.position_id tetap 1 sumber kebenaran untuk semua kode lama
 * (LMS, Appraisal, org-chart, export, dst) — observer ini murni menjaga
 * baris "primary" di employee_positions supaya selalu sinkron dengan
 * position_id, TANPA perlu mengubah kode di tempat manapun yang sudah
 * menulis position_id hari ini (EmployeeController, OrgChartNodeController,
 * kedua pipeline import).
 */
class EmployeePositionSyncObserver
{
    public function created(Employee $employee): void
    {
        $this->syncPrimary($employee);
    }

    public function updated(Employee $employee): void
    {
        if (! $employee->wasChanged('position_id')) {
            return;
        }

        $this->syncPrimary($employee);
    }

    private function syncPrimary(Employee $employee): void
    {
        if (! $employee->position_id) {
            return;
        }

        EmployeePosition::where('employee_id', $employee->id)
            ->where('position_id', '!=', $employee->position_id)
            ->update(['is_primary' => false]);

        EmployeePosition::updateOrCreate(
            ['employee_id' => $employee->id, 'position_id' => $employee->position_id],
            ['is_primary' => true]
        );
    }
}
