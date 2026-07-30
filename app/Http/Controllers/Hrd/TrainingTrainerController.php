<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TrainingTrainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TrainingTrainerController extends Controller
{
    public function index(): View
    {
        $trainers = Schema::hasTable('training_trainers')
            ? TrainingTrainer::query()
                ->with(['employee.department:id,name', 'employee.position:id,name', 'employee.user:id,name,email,employee_id', 'user:id,name,email', 'approvedBy:id,name'])
                ->latest('id')
                ->paginate(20)
            : new LengthAwarePaginator([], 0, 20);

        $employees = Schema::hasTable('employees')
            ? Employee::query()
                ->with(['department:id,name', 'position:id,name', 'user:id,name,email,employee_id'])
                ->whereIn('status_employment', ['probation', 'contract', 'permanent'])
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number', 'department_id', 'position_id', 'status_employment'])
            : collect();

        return view('training_trainers.index', [
            'trainers' => $trainers,
            'employees' => $employees,
            'statuses' => TrainingTrainer::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('training_trainers')) {
            return back()->with('error', 'Tabel trainer training belum tersedia. Jalankan migrasi database terlebih dahulu.');
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:pending,active'],
        ]);

        $employee = Employee::query()->with('user:id,name,email,employee_id')->findOrFail($validated['employee_id']);
        $status = $validated['status'];

        TrainingTrainer::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'user_id' => $employee->user?->id,
                'status' => $status,
                'specialty' => $validated['specialty'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'appointed_by' => $request->user()?->id,
                'approved_by' => $status === TrainingTrainer::STATUS_ACTIVE ? $request->user()?->id : null,
                'approved_at' => $status === TrainingTrainer::STATUS_ACTIVE ? now() : null,
                'requested_at' => now(),
            ]
        );

        return back()->with('success', 'Data trainer berhasil disimpan.');
    }

    public function update(Request $request, TrainingTrainer $training_trainer): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', TrainingTrainer::STATUSES)],
            'specialty' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $training_trainer->loadMissing('employee.user:id,name,email,employee_id');

        $payload = [
            'status' => $validated['status'],
            'specialty' => $validated['specialty'] ?? $training_trainer->specialty,
            'notes' => $validated['notes'] ?? $training_trainer->notes,
            'user_id' => $training_trainer->employee?->user?->id,
        ];

        if ($validated['status'] === TrainingTrainer::STATUS_ACTIVE) {
            $payload['approved_by'] = $request->user()?->id;
            $payload['approved_at'] = now();
        }

        $training_trainer->update($payload);

        return back()->with('success', 'Status trainer berhasil diperbarui.');
    }

    public function apply(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('training_trainers')) {
            return back()->with('error', 'Fitur pengajuan trainer belum siap. Jalankan migrasi database terlebih dahulu.');
        }

        $employee = $request->user()?->employee;
        if (! $employee) {
            return back()->with('error', 'Akun Anda belum terhubung ke data karyawan. Hubungi HRD sebelum mengajukan trainer.');
        }

        $validated = $request->validate([
            'specialty' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $existing = TrainingTrainer::query()->where('employee_id', $employee->id)->first();
        if ($existing?->status === TrainingTrainer::STATUS_ACTIVE) {
            return back()->with('success', 'Anda sudah aktif sebagai trainer.');
        }

        TrainingTrainer::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'user_id' => $request->user()?->id,
                'status' => TrainingTrainer::STATUS_PENDING,
                'specialty' => $validated['specialty'] ?? $existing?->specialty,
                'notes' => $validated['notes'] ?? $existing?->notes,
                'requested_by' => $request->user()?->id,
                'requested_at' => now(),
            ]
        );

        return back()->with('success', 'Pengajuan trainer berhasil dikirim ke HRD.');
    }
}
