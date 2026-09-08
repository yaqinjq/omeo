<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeePosition;
use Illuminate\Http\Request;

class EmployeeAdditionalPositionController extends Controller
{
    /**
     * Daftar SELURUH jabatan karyawan ini (utama + tambahan) — dipakai org-chart
     * Builder untuk memilih "kotak ini mewakili jabatan yang mana" saat
     * karyawan yang sama muncul di lebih dari 1 node.
     */
    public function positions(Employee $employee)
    {
        $primary = $employee->position_id
            ? [['id' => $employee->position_id, 'name' => $employee->position?->name, 'is_primary' => true]]
            : [];

        $additional = $employee->additionalPositions()
            ->with('position:id,name')
            ->where('is_primary', false)
            ->get()
            ->map(fn ($ep) => ['id' => $ep->position_id, 'name' => $ep->position?->name, 'is_primary' => false])
            ->values()
            ->all();

        return response()->json(['positions' => array_merge($primary, $additional)]);
    }

    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
        ]);

        if ((int) $data['position_id'] === (int) $employee->position_id) {
            return back()->with('error', 'Posisi ini sudah jadi posisi utama karyawan ini.');
        }

        $alreadyAdded = $employee->additionalPositions()->where('position_id', $data['position_id'])->exists();
        if ($alreadyAdded) {
            return back()->with('error', 'Posisi ini sudah ada di daftar jabatan tambahan.');
        }

        $employee->additionalPositions()->create([
            'position_id' => $data['position_id'],
            'is_primary'  => false,
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function destroy(Employee $employee, EmployeePosition $employeePosition)
    {
        abort_if($employeePosition->employee_id !== $employee->id, 404);

        if ($employeePosition->is_primary) {
            return back()->with('error', 'Posisi utama tidak bisa dihapus dari sini — ganti lewat form edit karyawan.');
        }

        $employeePosition->delete();

        return back()->with('success', 'Jabatan tambahan dihapus.');
    }
}
