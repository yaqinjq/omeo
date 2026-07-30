<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Models\Employee;
use App\Models\User;
use App\Services\ApplicantProfileResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairEmployeeProfileLinksCommand extends Command
{
    protected $signature = 'employees:repair-profile-links {--dry-run : Simulasi tanpa menyimpan perubahan} {--employee-id= : Batasi ke employee tertentu}';

    protected $description = 'Perbaiki linkage employee-user-candidate yang aman agar profile employee bisa membaca applicant profile lama.';

    public function handle(ApplicantProfileResolver $resolver): int
    {
        if (! Schema::hasTable('employees') || ! Schema::hasTable('users') || ! Schema::hasTable('candidates')) {
            $this->warn('Tabel employees/users/candidates belum lengkap di environment ini.');
            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $employeeId = (int) $this->option('employee-id');
        $stats = [
            'scanned' => 0,
            'resolved' => 0,
            'candidate_linked' => 0,
            'user_linked' => 0,
            'skipped' => 0,
        ];

        Employee::query()
            ->when($employeeId > 0, fn ($query) => $query->whereKey($employeeId))
            ->with('user.applicantProfile')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($resolver, $dryRun, &$stats): void {
                foreach ($employees as $employee) {
                    $stats['scanned']++;

                    $existingProfile = $resolver->resolveForEmployee($employee);
                    $candidate = $this->findCandidateForEmployee($employee);
                    $user = $candidate ? $resolver->resolveUserForCandidate($candidate) : null;

                    $candidateLinked = $candidate
                        && $user
                        && (int) ($candidate->user_id ?? 0) <= 0;

                    $userLinked = $user
                        && (int) ($user->employee_id ?? 0) <= 0
                        && ! User::query()->where('employee_id', $employee->id)->where('id', '!=', $user->id)->exists();

                    if (! $candidateLinked && ! $userLinked) {
                        if ($existingProfile) {
                            $stats['resolved']++;
                        } else {
                            $stats['skipped']++;
                        }
                        continue;
                    }

                    if (! $dryRun) {
                        DB::transaction(function () use ($candidate, $user, $employee, $candidateLinked, $userLinked): void {
                            if ($candidateLinked) {
                                $candidate->forceFill(['user_id' => $user->id])->save();
                            }

                            if ($userLinked) {
                                $user->forceFill(['employee_id' => $employee->id])->save();
                            }
                        });
                    }

                    if ($candidateLinked) {
                        $stats['candidate_linked']++;
                    }

                    if ($userLinked) {
                        $stats['user_linked']++;
                    }
                }
            });

        $this->info(sprintf(
            'Repair selesai. scanned=%d resolved=%d candidate_linked=%d user_linked=%d skipped=%d dry_run=%s',
            $stats['scanned'],
            $stats['resolved'],
            $stats['candidate_linked'],
            $stats['user_linked'],
            $stats['skipped'],
            $dryRun ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }

    private function findCandidateForEmployee(Employee $employee): ?Candidate
    {
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

        $nik = trim((string) ($employee->nik ?? ''));
        if ($nik !== '') {
            $query->orWhere('nik', $nik);
            $hasCondition = true;
        }

        if (! $hasCondition) {
            return null;
        }

        $matches = $query->latest('id')->take(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}

