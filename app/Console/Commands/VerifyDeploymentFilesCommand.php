<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyDeploymentFilesCommand extends Command
{
    protected $signature = 'deploy:verify-files';

    protected $description = 'Cek apakah semua file yang seharusnya diupload sudah ada di server DAN isinya persis sama (MD5) dengan versi yang disiapkan — read-only, aman dijalankan kapan saja';

    /** path relatif => md5 hash yang diharapkan */
    private const EXPECTED = [
        'app/Http/Controllers/Finance/AnnualSummaryController.php' => '7ccb6a108d6fedf6e8dee89e77e7f663',
        'app/Http/Controllers/ChangelogController.php' => 'b4cc2b4924126380e925e7b388b33cdc',
        'app/Console/Commands/MergeDuplicateDepartmentsCommand.php' => 'a65fccbf626cf2c340ddfc0f2ca82846',
        'app/Http/Controllers/Master/DepartmentController.php' => '3363f121196448008c8fe9c53456fa7d',
        'app/Http/Controllers/Master/PositionController.php' => '5570caafddc49592ad7b05146cfe5cb6',
        'app/Services/Finance/BpjsLegalEntityResolverService.php' => '65b229a79c00d96226831d12309f59b8',
        'app/Http/Controllers/Finance/BpjsReconciliationController.php' => '12117732dfbc33755649803e5957fa3c',
        'app/Console/Commands/BackfillBpjsBillLegalEntitiesCommand.php' => 'd87677fe351bc4c7e5d87f5861fb1c55',
        'app/Console/Commands/BackfillEmployeeBpjsAssignmentsCommand.php' => '66b1426f0c5f91c264c706d5efb33fd2',
        'app/Http/Controllers/Master/OutletController.php' => 'aef28b5f5ae55449ea4871d28f2daea0',
        'app/Http/Controllers/MasterBpjs/BpjsAssignmentController.php' => '7e34648040883ea7d05fadb5af83542e',
        'routes/web.php' => 'dbf39e3521095b8a91795895841b8380',
        'database/migrations/2026_07_25_145835_create_employee_outlet_assignments_table.php' => '35807914cc7457bc58d0a6d55d3211c1',
        'app/Models/EmployeeOutletAssignment.php' => 'e9e769091fd1db074af19652afe3049d',
        'app/Models/Employee.php' => '54038a22aac1daf8bfbe5270276b4dd8',
        'app/Http/Controllers/EmployeeAssignmentController.php' => 'f5ced36a2c008288008fe1afcece7f4c',
        'app/Console/Commands/BackfillEmployeeOutletAssignmentsFromPayrollCommand.php' => 'f8a124122956e14e44a7b54d716f1f0b',
        'app/Console/Commands/SeedMissingOutletsFromPayrollCommand.php' => 'af634d65fbf178e648d48685b72fa063',
        'app/Http/Controllers/Hrd/AttendanceReportController.php' => '4afffae444214c33f075e1d66802ff4a',
        'resources/views/changelog/index.blade.php' => 'e1e588e9ce5ede64373b19387eaad4d3',
        'resources/views/master/outlets/create.blade.php' => '409bb17a052ea9fe9f9d230283da0285',
        'resources/views/master/outlets/edit.blade.php' => '5d4d2bce0359f3914b35a2e37a131ecc',
        'resources/views/finance/bpjs_assignments/cross_billing.blade.php' => 'f17dc6c9f86f8ed101ac70ac872a78e3',
        'resources/views/finance/bpjs_assignments/cross_billing_outlet.blade.php' => 'ec7b9df2da3216d1d427234d006e856f',
        'resources/views/hrd/attendance/pdf.blade.php' => '5ad52f04c669e67257aacbf54b6c7673',
        'resources/views/positions/org_chart.blade.php' => '66f5cd85c1d08c1869f9811cb3ea0c14',
    ];

    /** signature artisan command baru yang wajib terdaftar setelah deploy */
    private const EXPECTED_COMMANDS = [
        'departments:merge-duplicates',
        'bpjs:backfill-legal-entities',
        'bpjs:backfill-employee-assignments',
        'employee-outlet-assignments:backfill',
        'outlets:seed-missing-from-payroll',
    ];

    public function handle(): int
    {
        $ok = 0;
        $missing = 0;
        $mismatch = 0;

        $this->info('== CEK FILE (' . count(self::EXPECTED) . ' file) ==');
        foreach (self::EXPECTED as $relativePath => $expectedHash) {
            $fullPath = base_path($relativePath);

            if (! file_exists($fullPath)) {
                $this->error("  HILANG   : {$relativePath}");
                $missing++;
                continue;
            }

            $actualHash = md5_file($fullPath);
            if ($actualHash !== $expectedHash) {
                $this->warn("  BEDA ISI : {$relativePath} (kemungkinan upload sebagian/versi lama)");
                $mismatch++;
                continue;
            }

            $this->line("  OK       : {$relativePath}");
            $ok++;
        }

        $this->newLine();
        $this->info('== CEK ARTISAN COMMAND TERDAFTAR (' . count(self::EXPECTED_COMMANDS) . ' command) ==');
        $registered = collect($this->getApplication()->all())->keys();
        $commandOk = 0;
        $commandMissing = 0;
        foreach (self::EXPECTED_COMMANDS as $signature) {
            if ($registered->contains($signature)) {
                $this->line("  OK       : {$signature}");
                $commandOk++;
            } else {
                $this->error("  TIDAK ADA: {$signature} (file command mungkin belum ke-upload, atau composer dump-autoload belum dijalankan)");
                $commandMissing++;
            }
        }

        $this->newLine();
        $this->info("File cocok: {$ok}/" . count(self::EXPECTED) . " | Hilang: {$missing} | Beda isi: {$mismatch}");
        $this->info('Command terdaftar: ' . $commandOk . '/' . count(self::EXPECTED_COMMANDS) . " | Tidak ada: {$commandMissing}");

        if ($missing > 0 || $mismatch > 0 || $commandMissing > 0) {
            $this->newLine();
            $this->error('BELUM LENGKAP. Cek ulang file yang HILANG / BEDA ISI / command TIDAK ADA di atas, upload ulang, lalu jalankan command ini lagi.');

            return 1;
        }

        $this->newLine();
        $this->info('SEMUA FILE LENGKAP DAN COCOK. Aman lanjut ke langkah migrate + backfill.');

        return 0;
    }
}
