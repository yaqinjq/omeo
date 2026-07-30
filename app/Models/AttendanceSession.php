<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'user_id',
        'outlet_id',
        'shift_code',
        'work_date',
        'scheduled_in_local',
        'scheduled_out_local',
        'first_in_at_utc',
        'last_out_at_utc',
        'total_work_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'status',
        'notes',
        'summary_json',
    ];

    protected $casts = [
        'work_date' => 'date',
        'first_in_at_utc' => 'datetime',
        'last_out_at_utc' => 'datetime',
        'total_work_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'summary_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function scans()
    {
        return $this->hasMany(AttendanceScan::class);
    }
}
