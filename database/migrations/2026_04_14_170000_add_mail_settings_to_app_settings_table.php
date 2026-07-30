<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('app_settings', 'mail_mailer')) {
                $table->string('mail_mailer')->nullable()->after('retention_last_run_at');
            }
            if (! Schema::hasColumn('app_settings', 'mail_host')) {
                $table->string('mail_host')->nullable()->after('mail_mailer');
            }
            if (! Schema::hasColumn('app_settings', 'mail_port')) {
                $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            }
            if (! Schema::hasColumn('app_settings', 'mail_username')) {
                $table->string('mail_username')->nullable()->after('mail_port');
            }
            if (! Schema::hasColumn('app_settings', 'mail_password')) {
                $table->text('mail_password')->nullable()->after('mail_username');
            }
            if (! Schema::hasColumn('app_settings', 'mail_encryption')) {
                $table->string('mail_encryption')->nullable()->after('mail_password');
            }
            if (! Schema::hasColumn('app_settings', 'mail_from_address')) {
                $table->string('mail_from_address')->nullable()->after('mail_encryption');
            }
            if (! Schema::hasColumn('app_settings', 'mail_from_name')) {
                $table->string('mail_from_name')->nullable()->after('mail_from_address');
            }
            if (! Schema::hasColumn('app_settings', 'mail_test_last_status')) {
                $table->string('mail_test_last_status')->nullable()->after('mail_from_name');
            }
            if (! Schema::hasColumn('app_settings', 'mail_test_last_error')) {
                $table->text('mail_test_last_error')->nullable()->after('mail_test_last_status');
            }
            if (! Schema::hasColumn('app_settings', 'mail_test_last_email')) {
                $table->string('mail_test_last_email')->nullable()->after('mail_test_last_error');
            }
            if (! Schema::hasColumn('app_settings', 'mail_test_last_ran_at')) {
                $table->timestamp('mail_test_last_ran_at')->nullable()->after('mail_test_last_email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            $drops = [];
            foreach ([
                'mail_test_last_ran_at',
                'mail_test_last_email',
                'mail_test_last_error',
                'mail_test_last_status',
                'mail_from_name',
                'mail_from_address',
                'mail_encryption',
                'mail_password',
                'mail_username',
                'mail_port',
                'mail_host',
                'mail_mailer',
            ] as $column) {
                if (Schema::hasColumn('app_settings', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
