<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeOutletAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeAssignmentController extends Controller
{
    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'outlet_id'         => ['nullable', 'integer', 'exists:outlets,id'],
            'payroll_outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
            'legal_entity_id'   => ['nullable', 'integer'],
            'department'        => ['nullable', 'string', 'max:150'],
            'effective_date'    => ['required', 'date'],
            'assignment_type'   => ['nullable', 'in:primary,secondary'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            // Close any currently-open assignment whose effective_date is before the new one
            $employee->assignments()
                ->whereNull('end_date')
                ->where('effective_date', '<', $data['effective_date'])
                ->update(['end_date' => $data['effective_date']]);

            $employee->assignments()->create([
                ...$data,
                'assignment_type' => $data['assignment_type'] ?? 'primary',
                'created_by'      => auth()->user()?->id,
            ]);

            DB::commit();

            return back()->with('success', 'Penugasan berhasil disimpan.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /**
     * Reassign outlet lewat drag-and-drop di canvas org-chart per-orang
     * (JSON, dipanggil via fetch). Menulis DUA tempat sekaligus dalam 1
     * transaction — employees.outlet_id (dibaca hampir semua bagian app
     * lain: filter, export, dst) DAN employee_outlet_assignments (riwayat
     * penugasan, pola sama seperti store() di atas) — supaya drag-and-drop
     * tidak menambah sumber-kebenaran baru yang bisa desync dari keduanya.
     */
    public function dragReassignOutlet(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
        ]);

        DB::beginTransaction();
        try {
            $now = now();

            $employee->assignments()
                ->whereNull('end_date')
                ->update(['end_date' => $now]);

            $employee->assignments()->create([
                'outlet_id'       => $data['outlet_id'],
                'effective_date'  => $now,
                'assignment_type' => 'primary',
                'reason'          => 'Drag-and-drop org-chart',
                'created_by'      => auth()->user()?->id,
            ]);

            $employee->update(['outlet_id' => $data['outlet_id']]);

            DB::commit();

            return response()->json(['message' => 'Outlet berhasil diperbarui.']);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Employee $employee, EmployeeOutletAssignment $assignment)
    {
        abort_if($assignment->employee_id !== $employee->id, 404);

        $assignment->delete();

        return back()->with('success', 'Riwayat penugasan dihapus.');
    }
}
