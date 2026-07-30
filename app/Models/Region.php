<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'legal_entity_id', 'name', 'province', 'bpjs_area_code',
        'umr_amount', 'umr_year', 'is_active', 'notes',
    ];

    protected $casts = [
        'umr_amount' => 'decimal:2',
        'umr_year'   => 'integer',
        'is_active'  => 'boolean',
    ];

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function bpjsRateConfigs()
    {
        return $this->hasMany(BpjsRateConfig::class);
    }

    public function activeRateConfig()
    {
        return $this->hasOne(BpjsRateConfig::class)->where('is_active', true)->latest();
    }
}
