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
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $normalized = strtolower(trim((string) ($row->role ?? '')));

                    if ($normalized === '' || ! in_array($normalized, User::ALLOWED_ROLES, true)) {
                        $normalized = User::ROLE_APPLICANT;
                    }

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['role' => $normalized]);
                }
            });

        try {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('role', 'users_role_index');
            });
        } catch (\Throwable $e) {
            // Index mungkin sudah ada di environment tertentu.
        }
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
            // Abaikan jika index belum ada.
        }
    }
};
