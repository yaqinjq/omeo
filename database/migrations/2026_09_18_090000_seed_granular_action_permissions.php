<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pecah beberapa permission "manage" yang membungkus semua aksi jadi satu
 * (tambah+edit+hapus+aksi khusus semuanya lewat 1 checkbox) menjadi
 * permission per-aksi — supaya role custom bisa diberi akses lebih sempit
 * (mis. "boleh edit tugas tapi tidak boleh hapus"), tanpa mengubah perilaku
 * role yang sudah ada (setiap role yang tadinya punya permission "manage"
 * sekarang JUGA diberi semua permission granular turunannya, jadi akses
 * efektifnya tetap sama atau lebih luas, tidak pernah lebih sempit).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['code' => 'applicants.review', 'name' => 'Tandai Applicant Sudah Direview', 'group' => 'Recruitment'],
            ['code' => 'applicants.bulk_action', 'name' => 'Aksi Massal Applicant', 'group' => 'Recruitment'],
            ['code' => 'candidates.create', 'name' => 'Tambah Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.update', 'name' => 'Edit Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.delete', 'name' => 'Hapus Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.accept', 'name' => 'Terima Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.reject', 'name' => 'Tolak Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.block', 'name' => 'Blokir Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.restore', 'name' => 'Pulihkan Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.bulk_status', 'name' => 'Ubah Status Massal Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.assessment_update', 'name' => 'Input Skor Assessment Candidate', 'group' => 'Recruitment'],
            ['code' => 'candidates.export', 'name' => 'Export Data Candidate', 'group' => 'Recruitment'],
            ['code' => 'employees.create', 'name' => 'Tambah Karyawan', 'group' => 'Employees'],
            ['code' => 'employees.update', 'name' => 'Edit Data Karyawan', 'group' => 'Employees'],
            ['code' => 'employees.delete', 'name' => 'Hapus Karyawan', 'group' => 'Employees'],
            ['code' => 'departments.view', 'name' => 'Lihat Departemen', 'group' => 'Employees'],
            ['code' => 'positions.view', 'name' => 'Lihat Posisi', 'group' => 'Employees'],
            ['code' => 'outlets.view', 'name' => 'Lihat Outlet', 'group' => 'Employees'],
            ['code' => 'tasks.view', 'name' => 'Lihat Papan Tugas', 'group' => 'Employees'],
            ['code' => 'tasks.create', 'name' => 'Tambah Tugas', 'group' => 'Employees'],
            ['code' => 'tasks.update', 'name' => 'Edit Tugas', 'group' => 'Employees'],
            ['code' => 'tasks.delete', 'name' => 'Hapus Tugas', 'group' => 'Employees'],
            ['code' => 'appraisals.view', 'name' => 'Lihat Laporan Appraisal', 'group' => 'Appraisal'],
            ['code' => 'appraisals.approve', 'name' => 'Approve/Exclude Penilaian Appraisal', 'group' => 'Appraisal'],
            ['code' => 'appraisals.assign', 'name' => 'Assign Evaluator Appraisal', 'group' => 'Appraisal'],
            ['code' => 'appraisals.print', 'name' => 'Cetak/Export Laporan Appraisal', 'group' => 'Appraisal'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $perm['code']],
                [
                    'slug' => $perm['code'],
                    'name' => $perm['name'],
                    'group_name' => $perm['group'],
                    'group' => $perm['group'],
                    'description' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // hrd & manager: berikan semua permission granular baru yang relevan,
        // supaya akses efektif mereka tidak berkurang sedikit pun dibanding
        // sebelumnya (dulu cukup 1 permission "manage" untuk semuanya).
        $hrdCodes = [
            'applicants.review', 'applicants.bulk_action',
            'candidates.create', 'candidates.update', 'candidates.delete',
            'candidates.accept', 'candidates.reject', 'candidates.block', 'candidates.restore',
            'candidates.bulk_status', 'candidates.assessment_update', 'candidates.export',
            'employees.create', 'employees.update', 'employees.delete',
            'departments.view', 'positions.view', 'outlets.view',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'appraisals.view', 'appraisals.approve', 'appraisals.assign', 'appraisals.print',
        ];

        $managerCodes = [
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'appraisals.view', 'appraisals.approve', 'appraisals.assign', 'appraisals.print',
        ];

        $hrdRoleId = DB::table('roles')->where('slug', 'hrd')->value('id');
        $managerRoleId = DB::table('roles')->where('slug', 'manager')->value('id');

        $this->assignPermissionsToRole($hrdRoleId, $hrdCodes, $now);
        $this->assignPermissionsToRole($managerRoleId, $managerCodes, $now);
    }

    private function assignPermissionsToRole(?int $roleId, array $codes, $now): void
    {
        if (! $roleId) {
            return;
        }

        foreach ($codes as $code) {
            $permId = DB::table('permissions')->where('code', $code)->value('id');
            if (! $permId) {
                continue;
            }

            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $codes = [
            'applicants.review', 'applicants.bulk_action',
            'candidates.create', 'candidates.update', 'candidates.delete',
            'candidates.accept', 'candidates.reject', 'candidates.block', 'candidates.restore',
            'candidates.bulk_status', 'candidates.assessment_update', 'candidates.export',
            'employees.create', 'employees.update', 'employees.delete',
            'departments.view', 'positions.view', 'outlets.view',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'appraisals.view', 'appraisals.approve', 'appraisals.assign', 'appraisals.print',
        ];

        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
