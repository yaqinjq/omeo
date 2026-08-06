<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MergeUmmuLuthfiahDuplicateCommand extends Command
{
    protected $signature = 'employees:merge-ummu-luthfiah-duplicate {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Gabungkan 2 data karyawan duplikat UMMU LUTHFIAH (employee_id 26 -> 1645). Dibuat khusus untuk kasus ini setelah verifikasi manual data di production pada 2026-08-06 — bukan tool umum untuk employee lain.';

    private const LOSER_ID = 26;
    private const SURVIVOR_ID = 1645;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $prefix = $isDryRun ? '[DRY RUN] ' : '';

        $loser = Employee::find(self::LOSER_ID);
        $survivor = Employee::find(self::SURVIVOR_ID);

        if (! $loser || ! $survivor) {
            $this->error('Employee loser (#' . self::LOSER_ID . ') atau survivor (#' . self::SURVIVOR_ID . ') tidak ditemukan. Batal, tidak menebak ID lain.');
            return 1;
        }

        $this->info("{$prefix}Menggabungkan employee #" . self::LOSER_ID . " \"{$loser->full_name}\" (NIK {$loser->nik}) -> #" . self::SURVIVOR_ID . " \"{$survivor->full_name}\" (NIK {$survivor->nik})");
        $this->newLine();

        // Safety check pemakaian ulang: kalau angka-angka ini beda dari hasil
        // audit manual 2026-08-06, STOP - jangan lanjut jalan pakai asumsi
        // yang sudah basi, data production mungkin sudah berubah.
        $expectedCounts = [
            'appraisals'                    => 3,
            'employee_bank_accounts'        => 1,
            'appraisal_batch_signatures'    => 1,
            'training_program_enrollments'  => 3,
        ];

        $actualCounts = [
            'appraisals'                   => DB::table('appraisals')->where('employee_id', self::LOSER_ID)->count(),
            'employee_bank_accounts'       => DB::table('employee_bank_accounts')->where('employee_id', self::LOSER_ID)->count(),
            'appraisal_batch_signatures'   => DB::table('appraisal_batch_signatures')->where('employee_id', self::LOSER_ID)->count(),
            'training_program_enrollments' => DB::table('training_program_enrollments')->where('employee_id', self::LOSER_ID)->count(),
        ];

        foreach ($expectedCounts as $table => $expected) {
            if ($actualCounts[$table] !== $expected) {
                $this->error("Jumlah baris di {$table} untuk employee #" . self::LOSER_ID . " sekarang {$actualCounts[$table]}, tapi waktu audit manual 2026-08-06 jumlahnya {$expected}. Data production sudah berubah sejak audit - STOP, jangan lanjut tanpa audit ulang manual.");
                return 1;
            }
        }

        $this->line('Cek keamanan: jumlah baris di semua tabel sesuai hasil audit manual 2026-08-06. Lanjut.');
        $this->newLine();

        // Training enrollments: 26 dan 1645 sama-sama sudah enroll ke program
        // 1/2/3 (status=assigned, progress=0% di kedua sisi, tidak ada yang
        // benar-benar dikerjakan) - dicek ulang di sini juga, bukan cuma
        // percaya hasil audit manual, supaya tidak menghapus progress asli
        // kalau ternyata ada yang mulai mengerjakan sejak saat itu.
        $loserEnrollments = DB::table('training_program_enrollments')->where('employee_id', self::LOSER_ID)->get();
        foreach ($loserEnrollments as $enrollment) {
            $survivorHasSameProgram = DB::table('training_program_enrollments')
                ->where('employee_id', self::SURVIVOR_ID)
                ->where('training_program_id', $enrollment->training_program_id)
                ->exists();

            if ($survivorHasSameProgram && ((float) $enrollment->progress_percent > 0 || $enrollment->started_at !== null)) {
                $this->error("Enrollment training_program_id={$enrollment->training_program_id} milik employee #" . self::LOSER_ID . " punya progress ({$enrollment->progress_percent}%) - tidak aman dihapus otomatis. Cek manual dulu.");
                return 1;
            }
        }

        if ($isDryRun) {
            $this->line('  Akan pindah: appraisals (3), employee_bank_accounts (1), appraisal_batch_signatures (1)');
            $this->line('  Akan dihapus (duplikat progress 0%): training_program_enrollments milik #' . self::LOSER_ID . ' (' . $loserEnrollments->count() . ')');
            $this->line('  Login user_id=200 (ummuluthfiahahpeakbintaro@gmail.com): employee_id dilepas + password dikunci');
            $this->line('  Employee #' . self::LOSER_ID . ': soft-delete (deleted_at diisi, tidak dihapus permanen)');
            $this->newLine();
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
            return 0;
        }

        DB::transaction(function () use ($loserEnrollments) {
            $moved = [
                'appraisals'                 => DB::table('appraisals')->where('employee_id', self::LOSER_ID)->update(['employee_id' => self::SURVIVOR_ID]),
                'employee_bank_accounts'     => DB::table('employee_bank_accounts')->where('employee_id', self::LOSER_ID)->update(['employee_id' => self::SURVIVOR_ID]),
                'appraisal_batch_signatures' => DB::table('appraisal_batch_signatures')->where('employee_id', self::LOSER_ID)->update(['employee_id' => self::SURVIVOR_ID]),
            ];

            foreach ($moved as $table => $count) {
                $this->line("  {$table}: {$count} baris dipindah ke employee #" . self::SURVIVOR_ID);
            }

            $deletedEnrollments = DB::table('training_program_enrollments')->where('employee_id', self::LOSER_ID)->delete();
            $this->line("  training_program_enrollments: {$deletedEnrollments} baris duplikat (progress 0%) milik #" . self::LOSER_ID . ' dihapus');

            $otherLogin = User::where('employee_id', self::LOSER_ID)->first();
            if ($otherLogin) {
                $otherLogin->update([
                    'employee_id' => null,
                    'password'    => Str::random(64),
                ]);
                $this->line("  Login \"{$otherLogin->email}\" (user_id={$otherLogin->id}): dilepas dari data karyawan, password dikunci - tidak bisa dipakai login lagi");
            }

            Employee::find(self::LOSER_ID)->delete();
            $this->line('  Employee #' . self::LOSER_ID . ': soft-delete berhasil (deleted_at diisi)');
        });

        $this->newLine();
        $this->info('Selesai. Data UMMU LUTHFIAH sudah digabung ke employee #' . self::SURVIVOR_ID . '.');

        return 0;
    }
}
