<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 30)->default(User::ROLE_APPLICANT)->after('password');
            });
        }

        $this->normalizeRoles();
        $this->syncLegacyRoleSignals();
        $this->ensureAdminSafety();
        $this->ensureRoleIndex();
        $this->ensureApplicantDefaultForRoleColumn();
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        try {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_role_index');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function normalizeRoles(): void
    {
        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $role = strtolower(trim((string) ($row->role ?? '')));

                    if ($role === '' || ! in_array($role, User::ALLOWED_ROLES, true)) {
                        $role = User::ROLE_APPLICANT;
                    }

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['role' => $role]);
                }
            });
    }

    private function syncLegacyRoleSignals(): void
    {
        if (Schema::hasColumn('users', 'is_admin')) {
            DB::table('users')
                ->where('is_admin', 1)
                ->where(function ($query): void {
                    $query->whereNull('role')
                        ->orWhere('role', '')
                        ->orWhere('role', User::ROLE_APPLICANT);
                })
                ->update(['role' => User::ROLE_ADMIN]);
        }

        if (Schema::hasColumn('users', 'level')) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(CAST(level AS CHAR))'), ['admin', 'hrd', 'manager'])
                ->where(function ($query): void {
                    $query->whereNull('role')
                        ->orWhere('role', '')
                        ->orWhere('role', User::ROLE_APPLICANT);
                })
                ->update(['role' => DB::raw('LOWER(CAST(level AS CHAR))')]);
        }

        if (Schema::hasColumn('users', 'user_type')) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(CAST(user_type AS CHAR))'), ['admin', 'hrd', 'manager'])
                ->where(function ($query): void {
                    $query->whereNull('role')
                        ->orWhere('role', '')
                        ->orWhere('role', User::ROLE_APPLICANT);
                })
                ->update(['role' => DB::raw('LOWER(CAST(user_type AS CHAR))')]);
        }
    }

    private function ensureAdminSafety(): void
    {
        if (DB::table('users')->where('role', User::ROLE_ADMIN)->exists()) {
            return;
        }

        $idOneExists = DB::table('users')->where('id', 1)->exists();
        if ($idOneExists) {
            DB::table('users')->where('id', 1)->update(['role' => User::ROLE_ADMIN]);
            return;
        }

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update(['role' => User::ROLE_ADMIN]);
        }
    }

    private function ensureRoleIndex(): void
    {
        try {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('role', 'users_role_index');
            });
        } catch (\Throwable $e) {
            // ignore if already indexed
        }
    }

    private function ensureApplicantDefaultForRoleColumn(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE users MODIFY role VARCHAR(30) NOT NULL DEFAULT 'applicant'");
            }
        } catch (\Throwable $e) {
            // best effort only
        }
    }
};
