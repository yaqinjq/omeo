<?php

namespace App\Http\Controllers;

use App\Exports\IqTemplateExport;
use App\Models\Department;
use App\Models\ApplicantProfile;
use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\Outlet;
use App\Models\Position;
use App\Models\User;
use App\Support\ImportHeaderValidator;
use App\Support\ImportTemplateColumn;
use App\Support\ImportTemplateSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeImportController extends Controller
{
    public function __construct(private readonly ImportHeaderValidator $headerValidator)
    {
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new IqTemplateExport($this->schema()->exportHeaders(), $this->templateSampleRows()),
            'template-import-karyawan-existing.xlsx'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ], [
            'file.required' => 'File import wajib dipilih.',
            'file.mimes' => 'Format file harus CSV atau XLSX.',
        ]);

        try {
            $summary = $this->import($validated['file']);
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Import data karyawan gagal diproses.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Terjadi kendala saat import data karyawan.');
        }

        $flashType = $summary['created'] > 0 ? 'success' : 'warning';
        $flashMessage = $summary['created'] > 0
            ? 'Import data karyawan existing selesai diproses.'
            : 'Tidak ada baris baru yang berhasil diimport. Periksa ringkasan import untuk detail konflik.';

        return redirect()
            ->route('employees.index')
            ->with($flashType, $flashMessage)
            ->with('employee_import_summary', $summary);
    }

    private function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $match = $this->matchHeaders($rows);

        $master = $this->masterLookup();
        $summary = [
            'total_rows' => max(count($rows) - 1, 0),
            'created' => 0,
            'linked_users' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'row_errors' => [],
            'warnings' => $match->warnings(),
        ];

        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $match->mapRow($rawRow);
            if ($this->isEmptyRow(array_values($row))) {
                continue;
            }

            $payload = $this->buildPayload($row, $master);
            if ($payload['error'] !== null) {
                $summary['failed']++;
                $summary['row_errors'][] = ['row' => $rowNumber, 'message' => $payload['error']];
                continue;
            }

            $employeeData = $payload['employee'];
            $duplicateMessage = $this->duplicateMessage($employeeData);
            if ($duplicateMessage !== null) {
                $summary['duplicates']++;
                $summary['row_errors'][] = ['row' => $rowNumber, 'message' => $duplicateMessage];
                continue;
            }

            $linkedExistingUser = false;
            DB::transaction(function () use ($employeeData, &$linkedExistingUser): void {
                $employee = Employee::query()->create($employeeData);
                $linkedExistingUser = $this->attachExistingUser($employee);
                $this->storeInitialSalaryHistory($employee, $employeeData);
            });

            $summary['created']++;
            if ($linkedExistingUser) {
                $summary['linked_users']++;
            }
        }

        $summary['skipped'] = $summary['duplicates'];

        return $summary;
    }

    private function attachExistingUser(Employee $employee): bool
    {
        $email = mb_strtolower(trim((string) ($employee->email_private ?? '')));
        $nik = trim((string) ($employee->nik ?? ''));

        if ($email === '' && $nik === '') {
            return false;
        }

        $user = null;
        if ($email !== '') {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if (! $user && $nik !== '') {
            $user = $this->resolveApplicantUserByNik($nik);
        }

        if (! $user || ($user->employee_id && (int) $user->employee_id !== (int) $employee->id)) {
            return false;
        }

        $user->forceFill([
            'employee_id' => $employee->id,
            'name' => $employee->full_name ?: $user->name,
            'role' => $employee->status_employment === 'probation' ? User::ROLE_PROBATION : User::ROLE_EMPLOYEE,
        ])->save();

        return true;
    }

    private function resolveApplicantUserByNik(string $nik): ?User
    {
        if (! Schema::hasTable('applicant_profiles')) {
            return null;
        }

        $nik = trim($nik);
        if ($nik === '') {
            return null;
        }

        if (DB::getDriverName() === 'mysql') {
            $profile = ApplicantProfile::query()
                ->with('user')
                ->whereRaw(
                    "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.ktp_number')), '') = ?",
                    [$nik]
                )
                ->first();

            return $profile?->user;
        }

        return ApplicantProfile::query()
            ->with('user')
            ->get()
            ->first(function (ApplicantProfile $profile) use ($nik): bool {
                return trim((string) data_get($profile->personal_json, 'ktp_number', '')) === $nik;
            })?->user;
    }

    private function storeInitialSalaryHistory(Employee $employee, array $employeeData): void
    {
        if (! Schema::hasTable('employee_salary_histories')) {
            return;
        }

        $amount = $employeeData['current_salary'] ?? null;
        if ($amount === null || trim((string) $amount) === '') {
            return;
        }

        EmployeeSalaryHistory::query()->create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'effective_date' => $employeeData['join_date'] ?? now()->toDateString(),
            'changed_by' => auth()->id(),
            'source' => 'employee_import',
            'notes' => 'Import data karyawan existing dari MEO/template.',
        ]);
    }

    private function duplicateMessage(array $employeeData): ?string
    {
        $employeeNumber = trim((string) ($employeeData['employee_number'] ?? ''));
        if ($employeeNumber !== '' && Employee::query()->where('employee_number', $employeeNumber)->exists()) {
            return 'Dilewati karena NOKOM/employee_number sudah ada.';
        }

        $nik = trim((string) ($employeeData['nik'] ?? ''));
        if ($nik !== '' && Employee::query()->where('nik', $nik)->exists()) {
            return 'Dilewati karena NIK sudah ada pada master karyawan.';
        }

        $email = mb_strtolower(trim((string) ($employeeData['email_private'] ?? '')));
        if ($email !== '' && Employee::query()->whereRaw('LOWER(email_private) = ?', [$email])->exists()) {
            return 'Dilewati karena email private sudah terdaftar pada master karyawan.';
        }

        return null;
    }

    private function buildPayload(array $row, array $master): array
    {
        $rawStatus = Str::lower(trim((string) ($row['status_employment'] ?? 'probation')));
        $status = match ($rawStatus) {
            '', 'probation', 'masa percobaan' => 'probation',
            'tetap', 'permanen', 'permanent' => 'permanent',
            'kontrak', 'contract' => 'contract',
            'resign', 'resigned' => 'resigned',
            default => null,
        };

        if ($status === null) {
            return ['employee' => [], 'error' => 'Status employment tidak dikenali. Gunakan probation, contract, permanent, atau resigned.'];
        }

        $fullName = trim((string) ($row['full_name'] ?? ''));
        if ($fullName === '') {
            return ['employee' => [], 'error' => 'Nama lengkap wajib diisi.'];
        }

        $nik = trim((string) ($row['nik'] ?? ''));
        if ($nik === '') {
            return ['employee' => [], 'error' => 'NIK wajib diisi untuk master karyawan existing.'];
        }

        $email = trim((string) ($row['email_private'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['employee' => [], 'error' => 'Email private tidak valid.'];
        }

        $joinDate = $this->normalizeDate($row['join_date'] ?? null);
        if (trim((string) ($row['join_date'] ?? '')) !== '' && $joinDate === null) {
            return ['employee' => [], 'error' => 'Join date tidak valid. Gunakan format tanggal yang benar.'];
        }

        $probationEndDate = $this->normalizeDate($row['probation_end_date'] ?? null);
        if (trim((string) ($row['probation_end_date'] ?? '')) !== '' && $probationEndDate === null) {
            return ['employee' => [], 'error' => 'Probation end date tidak valid. Gunakan format tanggal yang benar.'];
        }

        if ($joinDate && $probationEndDate && $probationEndDate < $joinDate) {
            return ['employee' => [], 'error' => 'Probation end date tidak boleh lebih awal dari join date.'];
        }

        $departmentId = $this->resolveDepartmentId($row, $master['departments']);
        if (($row['department_name'] ?? '') !== '' && $departmentId === null) {
            return ['employee' => [], 'error' => 'Departemen tidak ditemukan. Pastikan master departemen sudah diimport terlebih dahulu.'];
        }

        $positionName = trim((string) ($row['position_name'] ?? ''));
        $positionId = $this->resolvePositionId($row, $master['positions']);
        if ($positionName === '') {
            return ['employee' => [], 'error' => 'Jabatan/position wajib diisi agar karyawan existing langsung masuk ke master dengan benar.'];
        }
        if ($positionId === null) {
            return ['employee' => [], 'error' => 'Posisi tidak ditemukan. Pastikan master posisi sudah diimport terlebih dahulu.'];
        }

        $outletId = $this->resolveOutletId($row, $master['outlets']);
        if ((($row['outlet_name'] ?? '') !== '' || ($row['outlet_external_id'] ?? '') !== '') && $outletId === null) {
            return ['employee' => [], 'error' => 'Outlet tidak ditemukan. Pastikan master outlet sudah diimport terlebih dahulu.'];
        }

        $salaryInput = trim((string) ($row['current_salary'] ?? ''));
        $currentSalary = $this->normalizeNumeric($row['current_salary'] ?? null);
        if ($salaryInput !== '' && $currentSalary === null) {
            return ['employee' => [], 'error' => 'Gaji saat ini tidak valid. Isi angka tanpa format yang ambigu.'];
        }

        return [
            'employee' => [
                'nik' => $nik,
                'employee_number' => trim((string) ($row['employee_number'] ?? '')) ?: null,
                'external_id' => trim((string) ($row['external_id'] ?? '')) ?: null,
                'full_name' => $fullName,
                'email_private' => $email !== '' ? $email : null,
                'phone_number' => trim((string) ($row['phone_number'] ?? '')) ?: null,
                'join_date' => $joinDate,
                'probation_end_date' => $probationEndDate,
                'status_employment' => $status,
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'outlet_id' => $outletId,
                'current_salary' => $currentSalary,
                'nokom' => trim((string) ($row['employee_number'] ?? '')) ?: null,
                'jabatan' => $positionName,
            ],
            'error' => null,
        ];
    }

    private function resolveDepartmentId(array $row, array $departments): ?int
    {
        $code = Str::lower(trim((string) ($row['department_code'] ?? '')));
        if ($code !== '' && isset($departments['by_code'][$code])) {
            return $departments['by_code'][$code];
        }

        $name = Str::lower(trim((string) ($row['department_name'] ?? '')));
        if ($name !== '' && isset($departments['by_name'][$name])) {
            return $departments['by_name'][$name];
        }

        return null;
    }

    private function resolvePositionId(array $row, array $positions): ?int
    {
        $name = Str::lower(trim((string) ($row['position_name'] ?? '')));
        return $name !== '' ? ($positions[$name] ?? null) : null;
    }

    private function resolveOutletId(array $row, array $outlets): ?int
    {
        $externalId = Str::lower(trim((string) ($row['outlet_external_id'] ?? '')));
        if ($externalId !== '' && isset($outlets['by_external_id'][$externalId])) {
            return $outlets['by_external_id'][$externalId];
        }

        $name = Str::lower(trim((string) ($row['outlet_name'] ?? '')));
        return $name !== '' ? ($outlets['by_name'][$name] ?? null) : null;
    }

    private function masterLookup(): array
    {
        return [
            'departments' => [
                'by_code' => Department::query()->get(['id', 'code'])->mapWithKeys(fn ($item) => [Str::lower(trim((string) $item->code)) => (int) $item->id])->all(),
                'by_name' => Department::query()->get(['id', 'name'])->mapWithKeys(fn ($item) => [Str::lower(trim((string) $item->name)) => (int) $item->id])->all(),
            ],
            'positions' => Position::query()->get(['id', 'name'])->mapWithKeys(fn ($item) => [Str::lower(trim((string) $item->name)) => (int) $item->id])->all(),
            'outlets' => [
                'by_external_id' => Outlet::query()->whereNotNull('external_id')->get(['id', 'external_id'])->mapWithKeys(fn ($item) => [Str::lower(trim((string) $item->external_id)) => (int) $item->id])->all(),
                'by_name' => Outlet::query()->get(['id', 'name'])->mapWithKeys(fn ($item) => [Str::lower(trim((string) $item->name)) => (int) $item->id])->all(),
            ],
        ];
    }

    private function schema(): ImportTemplateSchema
    {
        return ImportTemplateSchema::make([
            ImportTemplateColumn::make('employee_number', false, ['nokom', 'employee number']),
            ImportTemplateColumn::make('nik', true),
            ImportTemplateColumn::make('full_name', true, ['nama lengkap', 'nama']),
            ImportTemplateColumn::make('email_private', false, ['email', 'email private']),
            ImportTemplateColumn::make('phone_number', false, ['no hp', 'no telepon']),
            ImportTemplateColumn::make('status_employment', true, ['status']),
            ImportTemplateColumn::make('join_date'),
            ImportTemplateColumn::make('probation_end_date', false, ['probation end date']),
            ImportTemplateColumn::make('department_code', false, ['department code']),
            ImportTemplateColumn::make('department_name', false, ['departemen', 'department']),
            ImportTemplateColumn::make('position_name', true, ['jabatan', 'position']),
            ImportTemplateColumn::make('outlet_external_id', false, ['outlet external id']),
            ImportTemplateColumn::make('outlet_name', false, ['outlet']),
            ImportTemplateColumn::make('brand_name', false, ['brand']),
            ImportTemplateColumn::make('external_id', false, ['external id']),
            ImportTemplateColumn::make('current_salary', false, ['current salary', 'gaji saat ini']),
        ]);
    }

    private function matchHeaders(array $rows): \App\Support\ImportHeaderMatchResult
    {
        $match = $this->headerValidator->match($rows[0] ?? [], $this->schema());
        $this->headerValidator->ensureValid($match, 'karyawan');

        return $match;
    }

    private function templateSampleRows(): array
    {
        return [[
            'EMP-0001',
            '3578123412341234',
            'Budi Santoso',
            'budi@example.com',
            '08123456789',
            'probation',
            now()->subMonth()->format('Y-m-d'),
            now()->addMonth()->format('Y-m-d'),
            'HRD',
            'Human Resource',
            'Crew Outlet',
            'OUTLET-001',
            'Outlet Darmo',
            'MEO',
            'MEO-123',
            '4500000',
        ]];
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeNumeric(mixed $value): ?string
    {
        $clean = preg_replace('/[^0-9,.-]/', '', trim((string) $value));
        if ($clean === null || $clean === '') {
            return null;
        }

        return str_replace(',', '.', $clean);
    }

    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray([], $file);
        $rows = $sheets[0] ?? [];

        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data yang dapat diimport.']);
        }

        return $rows;
    }
}
