<?php

namespace App\Services;

use App\Models\ProfileChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeProfileCompletenessService
{
    public function __construct(private readonly PayrollProfileTargetService $targetService)
    {
    }

    public function isPayrollCompleteAndVerified(mixed $employee): bool
    {
        $employeeRow = $this->resolveEmployeeRow($employee);
        if (!$employeeRow) {
            return false;
        }

        $userRow = $this->resolveLinkedUser((int) $employeeRow->id);
        if (!$this->isProbationEmployee($employeeRow, $userRow)) {
            return false;
        }

        if ($userRow && Schema::hasTable('profile_change_requests')) {
            $hasPending = DB::table('profile_change_requests')
                ->where('user_id', (int) $userRow->id)
                ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                ->where('status', ProfileChangeRequest::STATUS_PENDING)
                ->exists();

            if ($hasPending) {
                return false;
            }
        }

        $payroll = $this->targetService->getCurrent((int) ($userRow->id ?? 0), (int) $employeeRow->id);

        return $this->targetService->isRequiredComplete($payroll)
            && !empty($payroll['payroll_verified_at']);
    }

    public function needsNokomSetup(mixed $employee): bool
    {
        $employeeRow = $this->resolveEmployeeRow($employee);
        if (!$employeeRow) {
            return false;
        }

        if (!$this->isPayrollCompleteAndVerified($employeeRow)) {
            return false;
        }

        $employeeNumber = trim((string) ($employeeRow->employee_number ?? ''));
        $joinDate = $employeeRow->join_date ?? null;

        $positionMissing = true;
        if (Schema::hasColumn('employees', 'position_id')) {
            $positionMissing = empty($employeeRow->position_id);
        } elseif (Schema::hasColumn('employees', 'job_title')) {
            $positionMissing = trim((string) ($employeeRow->job_title ?? '')) === '';
        }

        return $employeeNumber === '' || empty($joinDate) || $positionMissing;
    }

    /**
     * @return array<int,object>
     */
    public function listEmployeesNeedsNokomSetup(int $limit = 20): array
    {
        if (!Schema::hasTable('employees')) {
            return [];
        }

        // All schema checks done ONCE — never inside the per-row loop
        $employeeColumns = Schema::getColumnListing('employees');
        $hasStatusEmployment = in_array('status_employment', $employeeColumns, true);
        $hasDeletedAt        = in_array('deleted_at', $employeeColumns, true);
        $hasEmployeeNumber   = in_array('employee_number', $employeeColumns, true);
        $hasJoinDate         = in_array('join_date', $employeeColumns, true);
        $hasPositionId       = in_array('position_id', $employeeColumns, true);
        $hasJobTitle         = in_array('job_title', $employeeColumns, true);

        $select = ['id'];
        foreach (['nik', 'full_name', 'employee_number', 'join_date', 'position_id', 'status_employment'] as $col) {
            if (in_array($col, $employeeColumns, true)) {
                $select[] = $col;
            }
        }

        // Filter at DB level: only employees that are actually missing a NOKOM field.
        // This avoids loading all 2000+ probation employees when most already have data.
        $query = DB::table('employees')
            ->select($select)
            ->orderBy('id')
            ->where(function ($q) use ($hasEmployeeNumber, $hasJoinDate, $hasPositionId, $hasJobTitle) {
                if ($hasEmployeeNumber) {
                    $q->orWhereNull('employee_number')->orWhere('employee_number', '');
                }
                if ($hasJoinDate) {
                    $q->orWhereNull('join_date');
                }
                if ($hasPositionId) {
                    $q->orWhereNull('position_id');
                } elseif ($hasJobTitle) {
                    $q->orWhereNull('job_title')->orWhere('job_title', '');
                }
            });

        if ($hasStatusEmployment) {
            $query->where('status_employment', 'probation');
        }
        if ($hasDeletedAt) {
            $query->whereNull('deleted_at');
        }

        // Hard cap: never scan more than 500 rows — dashboard widget doesn't need full table scan
        $rows = $query->limit(500)->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $employeeIds = $rows->pluck('id')->all();

        // Pre-fetch all users in ONE query (not per-row)
        $users = collect();
        $hasUsersTable = Schema::hasTable('users');
        if ($hasUsersTable && Schema::hasColumn('users', 'employee_id')) {
            $userColumns = Schema::getColumnListing('users');
            $userSelect  = ['id', 'employee_id', 'email'];
            if (in_array('role', $userColumns, true)) {
                $userSelect[] = 'role';
            }
            if (in_array('employee_status', $userColumns, true)) {
                $userSelect[] = 'employee_status';
            }

            $users = DB::table('users')
                ->select($userSelect)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy('employee_id');
        }

        // Pre-fetch pending profile change requests in ONE query (not per-row)
        $usersWithPending = collect();
        if (Schema::hasTable('profile_change_requests') && $users->isNotEmpty()) {
            $userIds = $users->pluck('id')->filter()->values()->all();
            if (!empty($userIds)) {
                $usersWithPending = DB::table('profile_change_requests')
                    ->whereIn('user_id', $userIds)
                    ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                    ->where('status', ProfileChangeRequest::STATUS_PENDING)
                    ->pluck('user_id')
                    ->flip();
            }
        }

        // Determine profile table ONCE — same for every employee in this deployment
        [$profileTable, $profileIdCol, $profileColumns] = $this->resolveProfileTableMeta($employeeColumns);

        // Payroll column presence checked ONCE — not inside the loop
        $requiredPayrollCols = [
            'sim_number', 'sim_file_path', 'npwp_number', 'npwp_file_path',
            'bpjs_kes_number', 'bpjs_kes_file_path', 'kk_number', 'kk_file_path',
        ];
        $payrollSchemaOk = empty(array_diff($requiredPayrollCols, $profileColumns))
            && in_array('payroll_verified_at', $profileColumns, true);

        // Pre-fetch payroll profiles in ONE batch query (replaces per-row getCurrent() calls)
        $profilesMap = collect();
        if ($payrollSchemaOk) {
            if ($profileTable === 'employee_profiles') {
                $lookupIds = ($profileIdCol === 'user_id')
                    ? $users->pluck('id')->filter()->values()->all()
                    : $employeeIds;
                if (!empty($lookupIds)) {
                    $profilesMap = DB::table('employee_profiles')
                        ->whereIn($profileIdCol, $lookupIds)
                        ->get()
                        ->keyBy($profileIdCol);
                }
            } else {
                // Payroll lives on employees table — batch-fetch payroll columns separately
                $payrollSelect = array_values(array_filter(
                    array_merge(['id'], $requiredPayrollCols, ['payroll_verified_at']),
                    fn ($c) => in_array($c, $employeeColumns, true)
                ));
                $profilesMap = DB::table('employees')
                    ->select($payrollSelect)
                    ->whereIn('id', $employeeIds)
                    ->get()
                    ->keyBy('id');
            }
        }

        $result = [];
        foreach ($rows as $row) {
            // Probation check — inline, no schema query
            $byEmployee = $hasStatusEmployment
                && ((string) ($row->status_employment ?? '')) === 'probation';

            $linkedUser = $users->get((int) $row->id);

            $byUserRole = $linkedUser && (
                ((string) ($linkedUser->role ?? '')) === 'probation' ||
                ((string) ($linkedUser->employee_status ?? '')) === 'probation'
            );

            if (!$byEmployee && !$byUserRole) {
                continue;
            }

            // Pending change request check
            if ($linkedUser && $usersWithPending->has((int) $linkedUser->id)) {
                continue;
            }

            // Payroll completeness — uses pre-fetched map, zero extra queries per row
            if (!$payrollSchemaOk) {
                continue;
            }

            $lookupId = ($profileTable === 'employee_profiles' && $profileIdCol === 'user_id')
                ? (int) ($linkedUser?->id ?? 0)
                : (int) $row->id;
            $profile = $profilesMap->get($lookupId);

            if (!$this->isProfilePayrollComplete($profile, $requiredPayrollCols)) {
                continue;
            }

            $result[] = (object) [
                'employee_id'     => (int) $row->id,
                'full_name'       => (string) ($row->full_name ?? '-'),
                'nik'             => (string) ($row->nik ?? '-'),
                'email'           => (string) ($linkedUser->email ?? '-'),
                'employee_number' => (string) ($row->employee_number ?? ''),
                'join_date'       => $row->join_date ?? null,
                'position_id'     => $row->position_id ?? null,
                'edit_url'        => route('employees.edit', (int) $row->id),
            ];

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<int,string> $employeeColumns
     * @return array{0:string, 1:string, 2:array<int,string>}
     */
    private function resolveProfileTableMeta(array $employeeColumns): array
    {
        if (Schema::hasTable('employee_profiles')) {
            $cols = Schema::getColumnListing('employee_profiles');
            if (in_array('user_id', $cols, true)) {
                return ['employee_profiles', 'user_id', $cols];
            }
            if (in_array('employee_id', $cols, true)) {
                return ['employee_profiles', 'employee_id', $cols];
            }
        }

        return ['employees', 'id', $employeeColumns];
    }

    /**
     * @param array<int,string> $requiredCols
     */
    private function isProfilePayrollComplete(?object $profile, array $requiredCols): bool
    {
        if (!$profile) {
            return false;
        }

        foreach ($requiredCols as $col) {
            if (trim((string) ($profile->$col ?? '')) === '') {
                return false;
            }
        }

        return !empty($profile->payroll_verified_at);
    }

    private function resolveEmployeeRow(mixed $employee): ?object
    {
        if (is_object($employee) && isset($employee->id)) {
            return $employee;
        }

        $id = is_numeric($employee) ? (int) $employee : 0;
        if ($id <= 0 || !Schema::hasTable('employees')) {
            return null;
        }

        $query = DB::table('employees')->where('id', $id);
        if (Schema::hasColumn('employees', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
    }

    private function resolveLinkedUser(int $employeeId): ?object
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'employee_id')) {
            return null;
        }

        $select = ['id', 'employee_id', 'email'];
        foreach (['role', 'employee_status'] as $optionalColumn) {
            if (Schema::hasColumn('users', $optionalColumn)) {
                $select[] = $optionalColumn;
            }
        }

        return DB::table('users')
            ->select($select)
            ->where('employee_id', $employeeId)
            ->orderByDesc('id')
            ->first();
    }

    private function isProbationEmployee(object $employee, ?object $user): bool
    {
        $byEmployee = ((string) ($employee->status_employment ?? '')) === 'probation';

        $byUserRole = false;
        if ($user) {
            $byUserRole = ((string) ($user->role ?? '')) === 'probation'
                || ((string) ($user->employee_status ?? '')) === 'probation';
        }

        return $byEmployee || $byUserRole;
    }
}
