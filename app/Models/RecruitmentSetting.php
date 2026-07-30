<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, string $value, string $group = 'recruitment'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }

    public static function getInt(string $key, int $default): int
    {
        $raw = static::getValue($key);

        if ($raw === null || $raw === '') {
            return $default;
        }

        return max(1, (int) $raw);
    }
}
