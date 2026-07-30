<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBpjsAssignment;
use Illuminate\Http\Request;

class BpjsAssignmentController extends Controller
{
    public function index(Employee $employee)
    {
        $assignments = $employee->bpjsAssignments()
            ->with('legalEntity')
            ->orderByDesc('effective_date')
            ->get();

        return response()->json($assignments);
    }

    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id'],
            'effective_date'  => ['required', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:effective_date'],
            'reason'          => ['required', 'in:join,transfer,resign'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        // Tutup assignment aktif sebelumnya
        $employee->bpjsAssignments()
            ->whereNull('end_date')
            ->where('effective_date', '<', $data['effective_date'])
            ->update(['end_date' => $data['effective_date']]);

        $assignment = $employee->bpjsAssignments()->create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success',
            'PT BPJS berhasil diset untuk ' . $employee->full_name . '.'
        );
    }

    public function update(Request $request, Employee $employee, EmployeeBpjsAssignment $bpjsAssignment)
    {
        abort_if($bpjsAssignment->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id'],
            'effective_date'  => ['required', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:effective_date'],
            'reason'          => ['required', 'in:join,transfer,resign'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $bpjsAssignment->update($data);

        return back()->with('success', 'Data penugasan BPJS berhasil diperbarui.');
    }

    public function destroy(Employee $employee, EmployeeBpjsAssignment $bpjsAssignment)
    {
        abort_if($bpjsAssignment->employee_id !== $employee->id, 404);

        $bpjsAssignment->delete();

        return back()->with('success', 'Riwayat penugasan BPJS berhasil dihapus.');
    }
}
