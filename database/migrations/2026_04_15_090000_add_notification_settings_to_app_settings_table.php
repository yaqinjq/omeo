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
            if (! Schema::hasColumn('app_settings', 'notification_event_preferences_json')) {
                $table->json('notification_event_preferences_json')->nullable()->after('mail_test_last_ran_at');
            }
            if (! Schema::hasColumn('app_settings', 'notification_templates_json')) {
                $table->json('notification_templates_json')->nullable()->after('notification_event_preferences_json');
            }
            if (! Schema::hasColumn('app_settings', 'notification_whatsapp_provider')) {
                $table->string('notification_whatsapp_provider')->nullable()->after('notification_templates_json');
            }
            if (! Schema::hasColumn('app_settings', 'notification_whatsapp_api_version')) {
                $table->string('notification_whatsapp_api_version')->nullable()->after('notification_whatsapp_provider');
            }
            if (! Schema::hasColumn('app_settings', 'notification_whatsapp_business_account_id')) {
                $table->string('notification_whatsapp_business_account_id')->nullable()->after('notification_whatsapp_api_version');
            }
            if (! Schema::hasColumn('app_settings', 'notification_whatsapp_phone_number_id')) {
                $table->string('notification_whatsapp_phone_number_id')->nullable()->after('notification_whatsapp_business_account_id');
            }
            if (! Schema::hasColumn('app_settings', 'notification_whatsapp_access_token')) {
                $table->text('notification_whatsapp_access_token')->nullable()->after('notification_whatsapp_phone_number_id');
            }
            if (! Schema::hasColumn('app_settings', 'notification_whatsapp_default_country_code')) {
                $table->string('notification_whatsapp_default_country_code')->nullable()->after('notification_whatsapp_access_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            foreach ([
                'notification_whatsapp_default_country_code',
                'notification_whatsapp_access_token',
                'notification_whatsapp_phone_number_id',
                'notification_whatsapp_business_account_id',
                'notification_whatsapp_api_version',
                'notification_whatsapp_provider',
                'notification_templates_json',
                'notification_event_preferences_json',
            ] as $column) {
                if (Schema::hasColumn('app_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
