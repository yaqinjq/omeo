<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalCriteriaTemplate extends Model
{
    protected $table = 'appraisal_criteria_templates';
    protected $fillable = ['name', 'description', 'lokasi_kerja', 'is_default', 'is_active'];
    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function indicators()
    {
        return $this->hasMany(AppraisalIndicator::class, 'template_id')->orderBy('category')->orderBy('sort_order')->orderBy('id');
    }

    public const LOKASI_KERJA_OPTIONS = [
        'office'     => 'Office',
        'outlet'     => 'Operational Outlet',
        'production' => 'Production',
    ];

    /**
     * Resolve the active template for a given employee lokasi_kerja value.
     * Falls back to the template flagged is_default when no exact match exists,
     * or null if neither exists (caller should then show all indicators).
     */
    public static function resolveFor(?string $lokasiKerja): ?self
    {
        $query = static::query()->where('is_active', true);

        if ($lokasiKerja !== null) {
            $match = (clone $query)->where('lokasi_kerja', $lokasiKerja)->first();
            if ($match) {
                return $match;
            }

            // "Operational" mengelompokkan outlet+production jadi satu
            // kategori (belum ada kebutuhan template terpisah untuk
            // produksi) — kalau karyawan production tidak match langsung,
            // coba template outlet dulu sebelum jatuh ke Default.
            if ($lokasiKerja === 'production') {
                $outletMatch = (clone $query)->where('lokasi_kerja', 'outlet')->first();
                if ($outletMatch) {
                    return $outletMatch;
                }
            }
        }

        return (clone $query)->where('is_default', true)->first();
    }
}
