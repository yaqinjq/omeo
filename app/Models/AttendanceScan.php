<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceScan extends Model
{
    protected $fillable = [
        'attendance_session_id',
        'scan_type',
        'scanned_at_utc',
        'scanned_at_local',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_meters',
        'is_within_geofence',
        'selfie_photo_path',
        'environment_photo_path',
        'device_json',
        'source',
    ];

    protected $casts = [
        'scanned_at_utc' => 'datetime',
        'scanned_at_local' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'integer',
        'distance_meters' => 'integer',
        'is_within_geofence' => 'boolean',
        'device_json' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }
}
