<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use Illuminate\Http\Request;

class EmployeeBankAccountController extends Controller
{
    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'bank_name'           => ['required', 'string', 'max:150'],
            'account_number'      => ['required', 'string', 'max:100'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'bank_code'           => ['nullable', 'string', 'max:50'],
            'is_primary'          => ['nullable', 'boolean'],
        ]);

        $isPrimary = (bool) ($data['is_primary'] ?? false);

        if ($isPrimary) {
            EmployeeBankAccount::where('employee_id', $employee->id)
                ->whereNull('deleted_at')
                ->update(['is_primary' => false]);
        }

        $employee->bankAccounts()->create([
            'bank_name'           => $data['bank_name'],
            'account_number'      => $data['account_number'],
            'account_holder_name' => $data['account_holder_name'],
            'bank_code'           => $data['bank_code'] ?? null,
            'is_primary'          => $isPrimary,
        ]);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Rekening berhasil ditambahkan.');
    }

    public function destroy(EmployeeBankAccount $bankAccount)
    {
        $employee = $bankAccount->employee;
        $bankAccount->delete();

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Rekening berhasil dihapus.');
    }
}
