<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'app_name',
        'app_logo_path',
        'app_favicon_path',
        'meta_title',
        'meta_description',
        'retention_enabled',
        'retention_rejected_days',
        'retention_blocked_days',
        'retention_last_run_at',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'mail_test_last_status',
        'mail_test_last_error',
        'mail_test_last_email',
        'mail_test_last_ran_at',
        'notification_event_preferences_json',
        'notification_templates_json',
        'notification_whatsapp_provider',
        'notification_whatsapp_api_version',
        'notification_whatsapp_business_account_id',
        'notification_whatsapp_phone_number_id',
        'notification_whatsapp_access_token',
        'notification_whatsapp_default_country_code',
    ];

    protected $casts = [
        'retention_enabled' => 'boolean',
        'retention_rejected_days' => 'integer',
        'retention_blocked_days' => 'integer',
        'retention_last_run_at' => 'datetime',
        'mail_port' => 'integer',
        'mail_password' => 'encrypted',
        'mail_test_last_ran_at' => 'datetime',
        'notification_event_preferences_json' => 'array',
        'notification_templates_json' => 'array',
        'notification_whatsapp_access_token' => 'encrypted',
    ];

    public function getLogoUrlAttribute()
    {
        return $this->app_logo_path
            ? asset('storage/' . $this->app_logo_path)
            : asset('images/default-logo.png');
    }

    public function getFaviconUrlAttribute()
    {
        return $this->app_favicon_path
            ? asset('storage/' . $this->app_favicon_path)
            : asset('favicon.ico');
    }
}
