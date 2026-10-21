<?php

namespace App\Models\Sport;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting tunggal (1 baris): bulan mulai tahun fiskal untuk laporan
 * triwulanan Owner. NULL = belum dikonfigurasi — jangan default ke bulan
 * apa pun (lihat migration & ReportController::buildQuarterly()).
 */
class ReportSetting extends Model
{
    protected $table = 'sport_report_settings';

    protected $fillable = [
        'fiscal_year_start_month',
        'updated_by_user_id',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new self();
    }

    public function isFiscalConfigured(): bool
    {
        return $this->fiscal_year_start_month !== null;
    }
}
