<?php

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\OutletNameMapping;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class EmployeeImportService
{
    // ── Parse ─────────────────────────────────────────────────────────────────

    public function parseFile(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheetByName('Page 0') ?? $spreadsheet->getActiveSheet();

        // formatData=false so dates come as serial floats; we parse them ourselves
        $rows = $sheet->toArray(null, true, false, false);

        $parsed = [];
        foreach ($rows as $row) {
            // Data rows: col[0] must be a positive integer (row number)
            if (!isset($row[0]) || !is_numeric($row[0]) || (int) $row[0] < 1) {
                continue;
            }

            // col[3] = Name (col[4] is Religion — skip)
            $fullName = trim((string) ($row[3] ?? ''));
            if ($fullName === '') {
                continue;
            }

            // phone: prefer col[20] (Telepon), fallback col[21] (HP/WA)
            $phone = trim((string) ($row[20] ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($row[21] ?? ''));
            }

            $parsed[] = [
                'no_urut'         => (int) $row[0],
                'employee_number' => trim((string) ($row[1] ?? '')),  // NIK. (internal)
                'npk'             => trim((string) ($row[2] ?? '')),  // NPK.
                'full_name'       => $fullName,                       // Name (col[3])
                // col[4] = Religion → abaikan
                'posisi'          => trim((string) ($row[5] ?? '')),  // Position
                'company'         => trim((string) ($row[6] ?? '')),  // Company
                'brand'           => trim((string) ($row[7] ?? '')),  // Project
                'bisnis_division' => strtolower(trim((string) ($row[8] ?? ''))),  // Bisnis Division
                'department'      => trim((string) ($row[9] ?? '')),  // Department
                'sub_dept'        => trim((string) ($row[10] ?? '')), // Sub Dept
                'dept_payroll'    => trim((string) ($row[11] ?? '')), // Dept. Payroll
                // col[12] = NPWP, col[13] = Marital, col[14] = PTKP
                // col[15] = L/P, col[16] = Place of Birth
                // col[17] = Birthday, col[18] = Address
                'nik'             => trim((string) ($row[19] ?? '')), // KTP 16 digit
                'phone_number'    => $phone,                          // Telepon/HP
                'join_date'       => $this->parseDate($row[22] ?? null), // Join Date
                'status_kerja'    => trim((string) ($row[23] ?? '')), // Status Kerja
                // col[24] = masa kerja, skip
                'payroll_session' => trim((string) ($row[25] ?? '')), // Payroll Session
                'email'           => strtolower(trim((string) ($row[26] ?? ''))), // Email
            ];
        }

        return $parsed;
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function preview(array $parsedRows): array
    {
        $result = ['new' => [], 'update' => [], 'skip' => [], 'errors' => []];

        foreach ($parsedRows as $row) {
            if ($row['full_name'] === '') {
                $result['errors'][] = array_merge($row, ['reason' => 'Nama kosong']);
                continue;
            }

            $existing = $this->findExistingEmployee($row);

            if ($existing) {
                $result['update'][] = array_merge($row, ['existing_id' => $existing->id, 'existing_name' => $existing->full_name]);
            } else {
                $result['new'][] = $row;
            }
        }

        return $result;
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(array $parsedRows, int $importedBy): array
    {
        $stats = ['new' => 0, 'updated' => 0, 'skipped' => 0, 'accounts_created' => 0, 'errors' => []];

        foreach ($parsedRows as $row) {
            if ($row['full_name'] === '') {
                $stats['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $importedBy, &$stats) {
                    $existing  = $this->findExistingEmployee($row);
                    $isNew     = $existing === null;
                    $outletId  = OutletNameMapping::resolveOutletId($row['department']);
                    $legalId   = $this->resolveLegalEntity($row['company']);
                    $positionId = $this->resolvePosition($row['posisi']);
                    $lokasi    = $this->resolveLokasiKerja($row['bisnis_division']);
                    $status    = $this->resolveStatusEmployment($row['status_kerja']);
                    $joinDate  = $row['join_date'];

                    $employeeData = array_filter([
                        'nik'                    => $row['nik'] ?: null,
                        'employee_number'        => $row['employee_number'] ?: null,
                        'npk'                    => $row['npk'] ?: null,
                        'nokom'                  => $row['employee_number'] ?: null,
                        'full_name'              => $row['full_name'],
                        'email_private'          => $row['email'] ?: null,
                        'phone_number'           => $row['phone_number'] ?: null,
                        'join_date'              => $joinDate,
                        'status_employment'      => $status,
                        'outlet_id'              => $outletId,
                        'legal_entity_kontrak_id'=> $legalId,
                        'lokasi_kerja'           => $lokasi,
                        'jabatan'                => $row['sub_dept'] ?: null,
                        'position_id'            => $positionId,
                        'payroll_session_tag'    => $row['payroll_session'] ?: null,
                    ], fn ($v) => $v !== null);

                    if ($existing) {
                        // Only fill columns that are currently empty
                        $fillable = array_filter($employeeData, fn ($v, $k) =>
                            $existing->$k === null || $existing->$k === '',
                            ARRAY_FILTER_USE_BOTH
                        );
                        if ($fillable) {
                            $existing->update($fillable);
                        }
                        $employee = $existing->fresh();
                        $stats['updated']++;
                    } else {
                        $employee = Employee::create($employeeData);
                        $stats['new']++;
                    }

                    // User account
                    if ($row['email'] !== '') {
                        $role  = $this->resolveRole($status, $joinDate);
                        $aUser = User::whereRaw('LOWER(email) = ?', [$row['email']])->first();
                        if ($aUser) {
                            $aUser->update([
                                'name'        => $row['full_name'],
                                'role'        => $role,
                                'employee_id' => $employee->id,
                            ]);
                        } else {
                            $password = $row['employee_number'] !== ''
                                ? $row['employee_number']
                                : Str::random(8);
                            User::create([
                                'name'        => $row['full_name'],
                                'email'       => $row['email'],
                                'password'    => bcrypt($password),
                                'role'        => $role,
                                'employee_id' => $employee->id,
                            ]);
                            $stats['accounts_created']++;
                        }
                    }
                });
            } catch (Throwable $e) {
                $stats['errors'][] = [
                    'name'   => $row['full_name'],
                    'reason' => $e->getMessage(),
                ];
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findExistingEmployee(array $row): ?Employee
    {
        if ($row['nik'] !== '') {
            $emp = Employee::where('nik', $row['nik'])->first();
            if ($emp) return $emp;
        }
        if ($row['employee_number'] !== '') {
            $emp = Employee::where('employee_number', $row['employee_number'])->first();
            if ($emp) return $emp;
        }
        if ($row['email'] !== '') {
            $emp = Employee::whereRaw('LOWER(email_private) = ?', [$row['email']])->first();
            if ($emp) return $emp;
        }
        return null;
    }

    private function resolveLegalEntity(string $company): ?int
    {
        if ($company === '') return null;
        return LegalEntity::whereRaw('UPPER(name) = ?', [strtoupper($company)])->value('id');
    }

    private function resolvePosition(string $posisi): ?int
    {
        if ($posisi === '') return null;
        return Position::whereRaw('UPPER(name) = ?', [strtoupper($posisi)])->value('id');
    }

    private function resolveLokasiKerja(string $bisnis): ?string
    {
        return match ($bisnis) {
            'outlet'      => 'outlet',
            'office'      => 'office',
            'production'  => 'production',
            default       => null,
        };
    }

    private function resolveStatusEmployment(string $raw): string
    {
        $lower = strtolower(trim($raw));
        return match (true) {
            in_array($lower, ['tetap', 'permanent', 'permanen'])        => 'permanent',
            in_array($lower, ['kontrak', 'contract'])                   => 'contract',
            in_array($lower, ['resign', 'resigned', 'keluar'])          => 'resigned',
            in_array($lower, ['magang', 'intern', 'harian', 'daily'])   => 'probation',
            default                                                     => 'probation',
        };
    }

    private function resolveRole(string $status, ?string $joinDate): string
    {
        if ($status !== 'probation') {
            return User::ROLE_EMPLOYEE;
        }
        if ($joinDate && Carbon::parse($joinDate)->diffInMonths(now()) >= 3) {
            return User::ROLE_EMPLOYEE;
        }
        return User::ROLE_PROBATION;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        // Excel serial date float
        if (is_float($value) || (is_numeric($value) && $value > 1 && $value < 60000)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (Throwable) {}
        }

        // String date
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
