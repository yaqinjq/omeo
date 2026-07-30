<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CandidatePromotionService
{
    public function __construct(private readonly ApplicantProfileResolver $applicantProfileResolver)
    {
    }

    public function promoteCandidateToEmployee(Candidate $candidate): Employee
    {
        if (!Schema::hasTable('employees')) {
            throw new RuntimeException('Tabel employees tidak ditemukan.');
        }

        $candidate->loadMissing('user');
        $this->attachExistingUserIfMissing($candidate);

        $applicantProfile = $this->applicantProfileResolver->resolveForCandidate($candidate);
        $employeeColumns = Schema::getColumnListing('employees');
        $candidateData = $this->buildEmployeePayload($candidate, $employeeColumns, $applicantProfile);

        $employee = $this->findExistingEmployee($candidate, $employeeColumns, $candidateData);
        if ($employee) {
            $updates = $this->buildEmployeeUpdatePayload($employee, $candidateData);
            if (!empty($updates)) {
                $employee->fill($updates)->save();
            }
        } else {
            $employee = Employee::create($candidateData);
        }

        $this->syncUserAsProbation($candidate, $employee->id, $candidateData);

        return $employee;
    }

    private function attachExistingUserIfMissing(Candidate $candidate): void
    {
        if ($candidate->user) {
            return;
        }

        $user = $this->applicantProfileResolver->resolveUserForCandidate($candidate);
        if (! $user) {
            return;
        }

        $candidate->user()->associate($user);
        $candidate->save();
        $candidate->setRelation('user', $user);
    }

    private function findExistingEmployee(Candidate $candidate, array $employeeColumns, array $candidateData): ?Employee
    {
        if ($candidate->user && $candidate->user->employee_id) {
            $linkedEmployee = Employee::query()->find($candidate->user->employee_id);
            if ($linkedEmployee) {
                return $linkedEmployee;
            }
        }

        $query = Employee::query();
        $hasCondition = false;

        $nik = trim((string) ($candidateData['nik'] ?? ''));
        if (in_array('nik', $employeeColumns, true) && $nik !== '') {
            $query->orWhere('nik', $nik);
            $hasCondition = true;
        }

        $email = trim((string) ($candidateData['email_private'] ?? ''));
        if (in_array('email_private', $employeeColumns, true) && $email !== '') {
            $query->orWhere('email_private', $email);
            $hasCondition = true;
        }

        if (!$hasCondition) {
            return null;
        }

        return $query->orderByDesc('id')->first();
    }

    private function buildEmployeePayload(Candidate $candidate, array $employeeColumns, $applicantProfile): array
    {
        $now = now();
        $payload = [];
        $personal = $applicantProfile?->normalizedPersonalJson() ?? [];
        $resolvedNik = trim((string) ($candidate->nik ?: data_get($personal, 'ktp_number') ?: ''));
        $resolvedName = trim((string) ($candidate->full_name ?: data_get($personal, 'full_name') ?: $candidate->user?->name ?: ''));
        $resolvedEmail = trim((string) ($candidate->email ?: data_get($personal, 'email') ?: $candidate->user?->email ?: ''));
        $resolvedPhone = trim((string) ($candidate->phone ?: data_get($personal, 'whatsapp') ?: ''));

        if (in_array('nik', $employeeColumns, true)) {
            $payload['nik'] = $resolvedNik !== '' ? $resolvedNik : 'CAND-' . $candidate->id;
        }

        if (in_array('external_id', $employeeColumns, true)) {
            $payload['external_id'] = 'CAND-' . $candidate->id;
        }

        if (in_array('full_name', $employeeColumns, true)) {
            $payload['full_name'] = $resolvedName !== '' ? $resolvedName : ('Candidate #' . $candidate->id);
        }

        if (in_array('email_private', $employeeColumns, true)) {
            $payload['email_private'] = $resolvedEmail !== '' ? $resolvedEmail : null;
        }

        if (in_array('phone_number', $employeeColumns, true)) {
            $payload['phone_number'] = $resolvedPhone !== '' ? $resolvedPhone : null;
        }

        if (in_array('join_date', $employeeColumns, true)) {
            $payload['join_date'] = $now->toDateString();
        }

        if (in_array('probation_end_date', $employeeColumns, true)) {
            $payload['probation_end_date'] = $now->copy()->addMonths(3)->toDateString();
        }

        if (in_array('status_employment', $employeeColumns, true)) {
            $payload['status_employment'] = 'probation';
        }

        return $payload;
    }

    private function buildEmployeeUpdatePayload(Employee $employee, array $candidateData): array
    {
        $updates = [];

        foreach ($candidateData as $column => $value) {
            if ($column === 'status_employment') {
                if ($employee->status_employment !== 'probation') {
                    $updates[$column] = 'probation';
                }
                continue;
            }

            if (in_array($column, ['join_date', 'external_id'], true)) {
                if (empty($employee->{$column}) && !empty($value)) {
                    $updates[$column] = $value;
                }
                continue;
            }

            if (empty($employee->{$column}) && !empty($value)) {
                $updates[$column] = $value;
            }
        }

        return $updates;
    }

    private function syncUserAsProbation(Candidate $candidate, int $employeeId, array $candidateData): void
    {
        if (!$candidate->user) {
            return;
        }

        $userColumns = Schema::getColumnListing('users');
        $updates = [];

        if (in_array('employee_id', $userColumns, true)) {
            $updates['employee_id'] = $employeeId;
        }

        if (in_array('role', $userColumns, true)) {
            $updates['role'] = 'probation';
        }

        if (in_array('employee_status', $userColumns, true)) {
            $updates['employee_status'] = 'probation';
        }

        if (in_array('name', $userColumns, true) && empty($candidate->user->name) && !empty($candidateData['full_name'])) {
            $updates['name'] = $candidateData['full_name'];
        }

        if (in_array('updated_at', $userColumns, true)) {
            $updates['updated_at'] = now();
        }

        if (!empty($updates)) {
            DB::table('users')
                ->where('id', $candidate->user->id)
                ->update($updates);
        }
    }
}
