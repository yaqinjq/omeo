<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicantProfileResolver
{
    public function resolveForEmployee(Employee $employee): ?ApplicantProfile
    {
        if (! Schema::hasTable('applicant_profiles')) {
            return null;
        }

        $employee->loadMissing('user.applicantProfile');
        if ($employee->user?->applicantProfile) {
            return $employee->user->applicantProfile;
        }

        $candidate = $this->resolveCandidateForEmployee($employee);
        if ($candidate) {
            $profile = $this->resolveForCandidate($candidate);
            if ($profile) {
                return $profile;
            }
        }

        $userId = (int) ($employee->user?->id ?? 0);
        if ($userId > 0) {
            $profile = ApplicantProfile::query()
                ->with('user')
                ->where('user_id', $userId)
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        foreach ([$employee->email_private, $employee->user?->email] as $emailCandidate) {
            $profile = $this->findProfileByEmail($emailCandidate);
            if ($profile) {
                return $profile;
            }
        }

        return $this->findProfileByNik($employee->nik);
    }

    public function resolveForCandidate(Candidate $candidate): ?ApplicantProfile
    {
        if (! Schema::hasTable('applicant_profiles')) {
            return null;
        }

        $candidate->loadMissing('user');

        $userId = (int) ($candidate->user_id ?? $candidate->user?->id ?? 0);
        if ($userId > 0) {
            $profile = ApplicantProfile::query()
                ->with('user')
                ->where('user_id', $userId)
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        $profile = $this->findProfileByEmail($candidate->email);
        if ($profile) {
            return $profile;
        }

        return $this->findProfileByNik($candidate->nik);
    }

    public function resolveUserForCandidate(Candidate $candidate): ?User
    {
        $candidate->loadMissing('user');
        if ($candidate->user) {
            return $candidate->user;
        }

        $email = mb_strtolower(trim((string) $candidate->email));
        if ($email !== '') {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                return $user;
            }
        }

        $profile = $this->findProfileByEmail($candidate->email);
        if ($profile?->user) {
            return $profile->user;
        }

        $profile = $this->findProfileByNik($candidate->nik);
        if ($profile?->user) {
            return $profile->user;
        }

        return null;
    }

    private function resolveCandidateForEmployee(Employee $employee): ?Candidate
    {
        $employee->loadMissing('user.applicantProfile');

        if ($employee->user) {
            $candidate = $employee->user->resolveCandidate();
            if ($candidate) {
                return $candidate;
            }
        }

        $query = Candidate::query();
        $hasCondition = false;

        $email = mb_strtolower(trim((string) ($employee->email_private ?? '')));
        if ($email !== '') {
            $query->orWhereRaw('LOWER(email) = ?', [$email]);
            $hasCondition = true;
        }

        $employeeUserEmail = mb_strtolower(trim((string) ($employee->user?->email ?? '')));
        if ($employeeUserEmail !== '' && $employeeUserEmail !== $email) {
            $query->orWhereRaw('LOWER(email) = ?', [$employeeUserEmail]);
            $hasCondition = true;
        }

        $nik = trim((string) ($employee->nik ?? ''));
        if ($nik !== '') {
            $query->orWhere('nik', $nik);
            $hasCondition = true;
        }

        if (! $hasCondition) {
            return null;
        }

        return $query->latest('id')->first();
    }

    private function findProfileByEmail(?string $email): ?ApplicantProfile
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '' || ! Schema::hasTable('applicant_profiles')) {
            return null;
        }

        if (DB::getDriverName() === 'sqlite') {
            return ApplicantProfile::query()
                ->with('user')
                ->get()
                ->first(function (ApplicantProfile $profile) use ($email): bool {
                    $profileEmail = mb_strtolower(trim((string) data_get($profile->personal_json, 'email', '')));
                    $userEmail = mb_strtolower(trim((string) ($profile->user?->email ?? '')));

                    return $profileEmail === $email || $userEmail === $email;
                });
        }

        return ApplicantProfile::query()
            ->with('user')
            ->where(function ($query) use ($email): void {
                $query->whereRaw(
                    "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.email')), '')) = ?",
                    [$email]
                )->orWhereHas('user', function ($userQuery) use ($email): void {
                    $userQuery->whereRaw('LOWER(email) = ?', [$email]);
                });
            })
            ->first();
    }

    private function findProfileByNik(?string $nik): ?ApplicantProfile
    {
        $nik = trim((string) $nik);
        if ($nik === '' || ! Schema::hasTable('applicant_profiles')) {
            return null;
        }

        if (DB::getDriverName() === 'sqlite') {
            return ApplicantProfile::query()
                ->with('user')
                ->get()
                ->first(function (ApplicantProfile $profile) use ($nik): bool {
                    return trim((string) data_get($profile->personal_json, 'ktp_number', '')) === $nik;
                });
        }

        return ApplicantProfile::query()
            ->with('user')
            ->whereRaw(
                "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.ktp_number')), '') = ?",
                [$nik]
            )
            ->first();
    }
}
