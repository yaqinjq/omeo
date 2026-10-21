<?php

namespace App\Models\Sport;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting tunggal (1 baris) untuk fitur backdate attendance MKO. NULL pada
 * max_backdate_days berarti fitur backdate NONAKTIF TOTAL, termasuk untuk
 * Admin Finance — lihat Spesifikasi v1.1 Section 9 & 13 (nilai belum
 * ditetapkan owner, sengaja tidak diisi default apa pun).
 */
class BackdateSetting extends Model
{
    protected $table = 'sport_backdate_settings';

    protected $fillable = [
        'max_backdate_days',
        'updated_by_user_id',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new self();
    }

    public function isEnabled(): bool
    {
        return $this->max_backdate_days !== null && $this->max_backdate_days > 0;
    }
}
