<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FinanceSetting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'description'];

    public static function getValue(string $key, $default = null)
    {
        return Cache::remember("finance_setting_{$key}", 3600, function () use ($key, $default) {
            $row = self::where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public static function setValue(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("finance_setting_{$key}");
    }
}
