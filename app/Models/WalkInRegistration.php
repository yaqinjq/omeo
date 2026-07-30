<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkInRegistration extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_SCREENED = 'screened';
    public const STATUS_PASSED = 'passed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_REGISTERED,
        self::STATUS_CHECKED_IN,
        self::STATUS_SCREENED,
        self::STATUS_PASSED,
        self::STATUS_REJECTED,
        self::STATUS_NO_SHOW,
    ];

    protected $fillable = [
        'walk_in_event_id',
        'walk_in_event_position_id',
        'registration_code',
        'full_name',
        'whatsapp_number',
        'email',
        'domicile',
        'referral_code',
        'referred_user_id',
        'status',
        'whatsapp_consent',
        'attendance_commitment',
        'checked_in_at',
        'screened_by',
        'screened_at',
        'screening_note',
    ];

    protected $casts = [
        'whatsapp_consent' => 'boolean',
        'attendance_commitment' => 'boolean',
        'checked_in_at' => 'datetime',
        'screened_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WalkInEvent::class, 'walk_in_event_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(WalkInEventPosition::class, 'walk_in_event_position_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function scopeForEvent(Builder $query, WalkInEvent $event): Builder
    {
        return $query->where('walk_in_event_id', $event->id);
    }
}
