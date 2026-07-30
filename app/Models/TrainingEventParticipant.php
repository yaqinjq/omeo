<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingEventParticipant extends Model
{
    public const STATUS_INVITED = 'invited';
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_ATTENDED = 'attended';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_REGISTERED,
        self::STATUS_CHECKED_IN,
        self::STATUS_ATTENDED,
        self::STATUS_ABSENT,
        self::STATUS_CANCELLED,
    ];

    public const TRAINER_MANAGED_STATUSES = [
        self::STATUS_REGISTERED,
        self::STATUS_CHECKED_IN,
        self::STATUS_ATTENDED,
        self::STATUS_ABSENT,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'training_event_id',
        'employee_id',
        'status',
        'invited_at',
        'invited_by',
        'registered_at',
        'checked_in_at',
        'attendance_marked_at',
        'attendance_marked_by',
        'selfie_photo_path',
        'environment_photo_path',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_address',
        'attendance_note',
        'metadata',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'registered_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'attendance_marked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(TrainingEvent::class, 'training_event_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function attendanceMarkedBy()
    {
        return $this->belongsTo(User::class, 'attendance_marked_by');
    }
}
