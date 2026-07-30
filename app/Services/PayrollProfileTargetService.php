<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollProfileTargetService
{
    /**
     * @return array{table:string, id_column:string, id_value:int}|null
     */
    public function resolveTarget(int $userId, ?int $employeeId): ?array
    {
        if (Schema::hasTable('employee_profiles')) {
            if (Schema::hasColumn('employee_profiles', 'user_id')) {
                return ['table' => 'employee_profiles', 'id_column' => 'user_id', 'id_value' => $userId];
            }

            if ($employeeId !== null && $employeeId > 0 && Schema::hasColumn('employee_profiles', 'employee_id')) {
                return ['table' => 'employee_profiles', 'id_column' => 'employee_id', 'id_value' => $employeeId];
            }
        }

        if ($employeeId !== null && $employeeId > 0 && Schema::hasTable('employees')) {
            return ['table' => 'employees', 'id_column' => 'id', 'id_value' => $employeeId];
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function getCurrent(int $userId, ?int $employeeId): array
    {
        $target = $this->resolveTarget($userId, $employeeId);
        if (!$target) {
            return [];
        }

        $columns = $this->tableColumns($target['table']);
        if ($columns === []) {
            return [];
        }

        $row = DB::table($target['table'])
            ->where($target['id_column'], $target['id_value'])
            ->first();

        if (!$row) {
            return [];
        }

        return [
            'sim_number' => in_array('sim_number', $columns, true) ? ($row->sim_number ?? null) : null,
            'sim_file' => in_array('sim_file_path', $columns, true) ? ($row->sim_file_path ?? null) : null,
            'npwp_number' => in_array('npwp_number', $columns, true) ? ($row->npwp_number ?? null) : null,
            'npwp_file' => in_array('npwp_file_path', $columns, true) ? ($row->npwp_file_path ?? null) : null,
            'bpjs_kes_number' => in_array('bpjs_kes_number', $columns, true) ? ($row->bpjs_kes_number ?? null) : null,
            'bpjs_kes_file' => in_array('bpjs_kes_file_path', $columns, true) ? ($row->bpjs_kes_file_path ?? null) : null,
            'bpjs_tk_number' => in_array('bpjs_tk_number', $columns, true) ? ($row->bpjs_tk_number ?? null) : null,
            'bpjs_tk_file' => in_array('bpjs_tk_file_path', $columns, true) ? ($row->bpjs_tk_file_path ?? null) : null,
            'passport_number' => in_array('passport_number', $columns, true) ? ($row->passport_number ?? null) : null,
            'passport_file' => in_array('passport_file_path', $columns, true) ? ($row->passport_file_path ?? null) : null,
            'kk_number' => in_array('kk_number', $columns, true) ? ($row->kk_number ?? null) : null,
            'kk_file' => in_array('kk_file_path', $columns, true) ? ($row->kk_file_path ?? null) : null,
            'payroll_verified_at' => in_array('payroll_verified_at', $columns, true) ? ($row->payroll_verified_at ?? null) : null,
        ];
    }

    /**
     * @param array<string,mixed> $changes
     * @param array<string,mixed> $attachments
     */
    public function applyApprovedChanges(int $userId, ?int $employeeId, array $changes, array $attachments): bool
    {
        $target = $this->resolveTarget($userId, $employeeId);
        if (!$target) {
            return false;
        }

        $columns = $this->tableColumns($target['table']);
        if ($columns === []) {
            return false;
        }

        $payload = [];
        $fieldMap = [
            'sim_number' => $changes['sim_number'] ?? null,
            'npwp_number' => $changes['npwp_number'] ?? null,
            'bpjs_kes_number' => $changes['bpjs_kes_number'] ?? null,
            'bpjs_tk_number' => $changes['bpjs_tk_number'] ?? null,
            'passport_number' => $changes['passport_number'] ?? null,
            'kk_number' => $changes['kk_number'] ?? null,
            'sim_file_path' => $attachments['sim_file'] ?? null,
            'npwp_file_path' => $attachments['npwp_file'] ?? null,
            'bpjs_kes_file_path' => $attachments['bpjs_kes_file'] ?? null,
            'bpjs_tk_file_path' => $attachments['bpjs_tk_file'] ?? null,
            'passport_file_path' => $attachments['passport_file'] ?? null,
            'kk_file_path' => $attachments['kk_file'] ?? null,
            'payroll_verified_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($fieldMap as $column => $value) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $value;
            }
        }

        if ($payload === []) {
            return false;
        }

        $query = DB::table($target['table'])->where($target['id_column'], $target['id_value']);
        $exists = $query->exists();

        if ($exists) {
            $query->update($payload);
            return true;
        }

        $insert = $payload;
        $insert[$target['id_column']] = $target['id_value'];
        if (in_array('created_at', $columns, true)) {
            $insert['created_at'] = now();
        }
        DB::table($target['table'])->insert($insert);

        return true;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function isRequiredComplete(array $data): bool
    {
        $required = [
            'sim_number', 'sim_file',
            'npwp_number', 'npwp_file',
            'bpjs_kes_number', 'bpjs_kes_file',
            'kk_number', 'kk_file',
        ];

        foreach ($required as $key) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return Schema::getColumnListing($table);
    }
}
