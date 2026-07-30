<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletNameMapping extends Model
{
    protected $fillable = ['raw_name', 'outlet_id', 'is_active', 'notes'];

    protected $casts = ['is_active' => 'boolean'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Resolve outlet_id dari nama raw, tiga level:
     * 1. Exact match di outlet_name_mappings (case-insensitive)
     * 2. Exact match di outlets.name (case-insensitive)
     * 3. Partial contains match di outlets.name
     */
    public static function resolveOutletId(string $rawName): ?int
    {
        $raw = trim($rawName);
        if ($raw === '') {
            return null;
        }

        // 1. Cek tabel mapping (prioritas tertinggi)
        $mapping = self::whereRaw('LOWER(raw_name) = ?', [strtolower($raw)])
            ->where('is_active', true)
            ->value('outlet_id');
        if ($mapping) {
            return (int) $mapping;
        }

        // 2. Exact match di master outlet
        $outletId = Outlet::whereRaw('LOWER(name) = ?', [strtolower($raw)])->value('id');
        if ($outletId) {
            return (int) $outletId;
        }

        // 3. Partial: raw mengandung nama outlet atau sebaliknya
        $rawUp   = strtoupper($raw);
        $outlets = Outlet::all(['id', 'name']);
        foreach ($outlets as $o) {
            $nameUp = strtoupper($o->name);
            if (str_contains($rawUp, $nameUp) || str_contains($nameUp, $rawUp)) {
                return (int) $o->id;
            }
        }

        return null;
    }
}
