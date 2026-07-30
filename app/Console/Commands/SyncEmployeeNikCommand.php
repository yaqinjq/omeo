<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

class SyncEmployeeNikCommand extends Command
{
    protected $signature = 'employee:sync-nik {--dry-run : Preview tanpa ubah data}';

    protected $description = 'Sync NIK dari applicant_profiles (personal_json.ktp_number) ke employees.nik';

    /**
     * Relasi: employees.id -> users.employee_id -> users.id -> applicant_profiles.user_id
     * (bukan lewat candidate_id — kolom itu tidak ada di employees).
     */
    public function handle(): void
    {
        // Join yang benar: employees → users → applicant_profiles
        $employees = \App\Models\Employee::whereNull('nik')
            ->orWhere('nik', '')
            ->with(['user.applicantProfile'])
            ->get();

        if ($employees->isEmpty()) {
            $this->info('Semua karyawan sudah punya NIK.');
            return;
        }

        $this->info("Ditemukan {$employees->count()} karyawan tanpa NIK:");
        $synced  = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $nik = $employee->user?->applicantProfile?->ktp_number ?? null;

            if (empty($nik)) {
                $this->line(
                    "  SKIP — {$employee->full_name} (id:{$employee->id}): " .
                    "tidak ada NIK di applicant_profile"
                );
                $skipped++;
                continue;
            }

            $this->line(
                "  SYNC — {$employee->full_name} (id:{$employee->id}): {$nik}"
            );

            if (!$this->option('dry-run')) {
                $employee->update(['nik' => $nik]);
            }

            $synced++;
        }

        $this->newLine();
        $this->info(
            "Siap di-sync: {$synced} | " .
            "Skip (tidak ada data): {$skipped}"
        );

        if ($this->option('dry-run')) {
            $this->warn('[DRY RUN] Tidak ada yang diubah.');
        }
    }
}
