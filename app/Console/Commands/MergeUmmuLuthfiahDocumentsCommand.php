<?php

namespace App\Console\Commands;

use App\Models\ApplicantProfile;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeUmmuLuthfiahDocumentsCommand extends Command
{
    protected $signature = 'employees:merge-ummu-luthfiah-documents {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Lanjutan dari employees:merge-ummu-luthfiah-duplicate — pindahkan dokumen onboarding (SIM/NPWP/BPJS Kes/KK di tabel employees, dan profil applicant_profiles 58-field) yang terlewat karena tersimpan langsung di kolom/tabel lain, bukan lewat FK employee_id biasa.';

    private const LOSER_EMPLOYEE_ID = 26;
    private const SURVIVOR_EMPLOYEE_ID = 1645;

    private const EMPTY_APPLICANT_PROFILE_ID = 198; // user_id 199, hampir kosong
    private const FULL_APPLICANT_PROFILE_ID = 199;  // user_id 200, 58 field lengkap tapi NIK typo

    private const CORRECT_NIK = '3671115601920003';

    private const DOCUMENT_COLUMNS = [
        'sim_number', 'sim_file_path',
        'npwp_number', 'npwp_file_path',
        'bpjs_kes_number', 'bpjs_kes_file_path',
        'bpjs_tk_number', 'bpjs_tk_file_path',
        'passport_number', 'passport_file_path',
        'kk_number', 'kk_file_path',
    ];

    private const PROFILE_JSON_COLUMNS = [
        'personal_json', 'family_json', 'address_json', 'education_json',
        'language_json', 'work_json', 'organization_json', 'course_json',
        'medical_json', 'social_json', 'completed_at',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $prefix = $isDryRun ? '[DRY RUN] ' : '';

        $loserEmployee = Employee::withTrashed()->find(self::LOSER_EMPLOYEE_ID);
        $survivorEmployee = Employee::find(self::SURVIVOR_EMPLOYEE_ID);
        $emptyProfile = ApplicantProfile::find(self::EMPTY_APPLICANT_PROFILE_ID);
        $fullProfile = ApplicantProfile::find(self::FULL_APPLICANT_PROFILE_ID);

        if (! $loserEmployee || ! $survivorEmployee || ! $emptyProfile || ! $fullProfile) {
            $this->error('Salah satu dari employee #' . self::LOSER_EMPLOYEE_ID . ', #' . self::SURVIVOR_EMPLOYEE_ID . ', applicant_profile #' . self::EMPTY_APPLICANT_PROFILE_ID . ', atau #' . self::FULL_APPLICANT_PROFILE_ID . ' tidak ditemukan. Batal.');
            return 1;
        }

        $this->info("{$prefix}Bagian 1: Dokumen employees (SIM/NPWP/BPJS Kes/BPJS TK/Passport/KK)");

        // Safety check: kolom survivor harus masih kosong (belum pernah diisi
        // manual sejak audit), dan loser harus masih punya nilai untuk kolom
        // yang mau disalin. Kalau salah satu sudah berubah, STOP.
        $toCopy = [];
        foreach (self::DOCUMENT_COLUMNS as $column) {
            $loserValue = $loserEmployee->{$column};
            $survivorValue = $survivorEmployee->{$column};

            if ($survivorValue !== null && $survivorValue !== '') {
                $this->error("employee #" . self::SURVIVOR_EMPLOYEE_ID . " kolom {$column} sudah terisi (\"{$survivorValue}\") sejak audit - tidak ditimpa otomatis. Cek manual.");
                return 1;
            }

            if ($loserValue !== null && $loserValue !== '') {
                $toCopy[$column] = $loserValue;
            }
        }

        if (empty($toCopy)) {
            $this->warn('Tidak ada kolom dokumen employees yang perlu disalin (semua sudah kosong di sumber, atau sudah terisi di tujuan).');
        } else {
            foreach ($toCopy as $column => $value) {
                $this->line("  {$column}: akan diisi dari employee #" . self::LOSER_EMPLOYEE_ID);
            }
        }

        $this->newLine();
        $this->info("{$prefix}Bagian 2: Profil onboarding lengkap (applicant_profiles)");

        if ((int) $emptyProfile->user_id !== 199 || (int) $fullProfile->user_id !== 200) {
            $this->error('user_id pada applicant_profiles #' . self::EMPTY_APPLICANT_PROFILE_ID . '/#' . self::FULL_APPLICANT_PROFILE_ID . ' sudah tidak sesuai hasil audit (199/200). STOP.');
            return 1;
        }

        foreach (['family_json', 'address_json', 'education_json', 'language_json', 'work_json', 'organization_json', 'course_json', 'medical_json', 'social_json'] as $column) {
            if (! empty($emptyProfile->{$column})) {
                $this->error("applicant_profiles #" . self::EMPTY_APPLICANT_PROFILE_ID . " kolom {$column} sudah terisi sejak audit - tidak ditimpa otomatis. Cek manual.");
                return 1;
            }
        }

        $this->line('  Semua kolom JSON (family/address/education/dst) dari profile #' . self::FULL_APPLICANT_PROFILE_ID . ' akan disalin ke profile #' . self::EMPTY_APPLICANT_PROFILE_ID . ' (yang terhubung ke login aktif)');
        $this->line('  ktp_number di dalam personal_json akan dikoreksi ke NIK yang benar (' . self::CORRECT_NIK . '), bukan yang typo');
        $this->line('  Profile #' . self::FULL_APPLICANT_PROFILE_ID . ' (sumber) akan di-soft-delete setelah datanya dipindah');

        if ($isDryRun) {
            $this->newLine();
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
            return 0;
        }

        DB::transaction(function () use ($toCopy, $emptyProfile, $fullProfile) {
            if (! empty($toCopy)) {
                DB::table('employees')->where('id', self::SURVIVOR_EMPLOYEE_ID)->update($toCopy);
                $this->line('  employees #' . self::SURVIVOR_EMPLOYEE_ID . ': ' . count($toCopy) . ' kolom dokumen diisi.');
            }

            $personalJson = $fullProfile->personal_json;
            $personalJson['ktp_number'] = self::CORRECT_NIK;

            $emptyProfile->update([
                'personal_json'      => $personalJson,
                'family_json'        => $fullProfile->family_json,
                'address_json'       => $fullProfile->address_json,
                'education_json'     => $fullProfile->education_json,
                'language_json'      => $fullProfile->language_json,
                'work_json'          => $fullProfile->work_json,
                'organization_json'  => $fullProfile->organization_json,
                'course_json'        => $fullProfile->course_json,
                'medical_json'       => $fullProfile->medical_json,
                'social_json'        => $fullProfile->social_json,
                'completed_at'       => $fullProfile->completed_at,
            ]);
            $this->line('  applicant_profiles #' . self::EMPTY_APPLICANT_PROFILE_ID . ': profil lengkap disalin, ktp_number dikoreksi.');

            $fullProfile->delete();
            $this->line('  applicant_profiles #' . self::FULL_APPLICANT_PROFILE_ID . ': soft-delete berhasil.');
        });

        $this->newLine();
        $this->info('Selesai. Dokumen dan profil onboarding UMMU LUTHFIAH sudah lengkap di akun yang aktif.');

        return 0;
    }
}
