<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    const TYPE_OPERATIONAL = 'operational';
    const TYPE_PAYROLL     = 'payroll';
    const TYPE_PRODUCTION  = 'production';

    protected $fillable = [
        'name',
        'location',
        'brand_name',
        'external_id',
        'latitude',
        'longitude',
        'radius_meters',
        'geofence_radius_m',
        'timezone',
        'work_start_time',
        'work_end_time',
        'legal_entity_id',
        'region_id',
        'outlet_type',
        'is_active',
    ];

    protected $casts = [
        'latitude'          => 'float',
        'longitude'         => 'float',
        'radius_meters'     => 'integer',
        'geofence_radius_m' => 'integer',
        'is_active'         => 'boolean',
    ];

    public function scopeOperational($query)
    {
        return $query->where('outlet_type', self::TYPE_OPERATIONAL);
    }

    public function scopeForPayroll($query)
    {
        return $query->whereIn('outlet_type', [self::TYPE_OPERATIONAL, self::TYPE_PAYROLL, self::TYPE_PRODUCTION]);
    }

    public function getOutletTypeLabelAttribute(): string
    {
        return match ($this->outlet_type) {
            'operational' => 'Operasional',
            'payroll'     => 'Payroll / Legal',
            'production'  => 'Unit Produksi',
            default       => $this->outlet_type,
        };
    }

    public function getOutletTypeBadgeColorAttribute(): string
    {
        return match ($this->outlet_type) {
            'operational' => 'display:inline-flex;align-items:center;padding:2px 8px;font-size:11px;font-weight:500;border-radius:9999px;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0',
            'payroll'     => 'display:inline-flex;align-items:center;padding:2px 8px;font-size:11px;font-weight:500;border-radius:9999px;background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe',
            'production'  => 'display:inline-flex;align-items:center;padding:2px 8px;font-size:11px;font-weight:500;border-radius:9999px;background:#ffedd5;color:#c2410c;border:1px solid #fed7aa',
            default       => 'display:inline-flex;align-items:center;padding:2px 8px;font-size:11px;font-weight:500;border-radius:9999px;background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb',
        };
    }

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function permits()
    {
        return $this->hasMany(OutletPermit::class)->orderBy('permit_type')->orderByDesc('issued_at');
    }
}
