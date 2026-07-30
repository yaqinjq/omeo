<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingEvent extends Model
{
    public const TYPE_LMS = 'lms';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_PRACTICAL = 'practical';

    public const TYPES = [
        self::TYPE_LMS,
        self::TYPE_MEETING,
        self::TYPE_PRACTICAL,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_STARTED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'training_program_id',
        'training_material_id',
        'title',
        'event_type',
        'platform',
        'meeting_url',
        'location_name',
        'location_address',
        'participant_instruction',
        'latitude',
        'longitude',
        'starts_at',
        'ends_at',
        'registration_deadline_at',
        'check_in_opens_at',
        'check_in_closes_at',
        'max_participants',
        'mentor_user_id',
        'requires_registration',
        'requires_photo_validation',
        'requires_geolocation',
        'status',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_deadline_at' => 'datetime',
        'check_in_opens_at' => 'datetime',
        'check_in_closes_at' => 'datetime',
        'max_participants' => 'integer',
        'requires_registration' => 'boolean',
        'requires_photo_validation' => 'boolean',
        'requires_geolocation' => 'boolean',
        'metadata' => 'array',
    ];

    public function program()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function material()
    {
        return $this->belongsTo(TrainingMaterial::class, 'training_material_id');
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function participants()
    {
        return $this->hasMany(TrainingEventParticipant::class)->orderBy('id');
    }

    public function isOpenForRegistration(): bool
    {
        if (! in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_STARTED], true)) {
            return false;
        }

        if ($this->registration_deadline_at && $this->registration_deadline_at->isPast()) {
            return false;
        }

        if ($this->max_participants && $this->participants()->whereIn('status', [
            TrainingEventParticipant::STATUS_REGISTERED,
            TrainingEventParticipant::STATUS_CHECKED_IN,
            TrainingEventParticipant::STATUS_ATTENDED,
        ])->count() >= $this->max_participants) {
            return false;
        }

        return true;
    }

    public function isCheckInOpen(): bool
    {
        if (! in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_STARTED], true)) {
            return false;
        }

        $opensAt = $this->check_in_opens_at ?: $this->starts_at?->copy()->subMinutes(30);
        $closesAt = $this->check_in_closes_at ?: ($this->ends_at?->copy()->addHours(2) ?: $this->starts_at?->copy()->addHours(6));

        if ($opensAt && now()->lt($opensAt)) {
            return false;
        }

        if ($closesAt && now()->gt($closesAt)) {
            return false;
        }

        return true;
    }

    public function meetingLinkVisibleFor(?TrainingEventParticipant $participant): bool
    {
        return $this->event_type === self::TYPE_MEETING
            && filled($this->meeting_url)
            && $participant
            && in_array($participant->status, [
                TrainingEventParticipant::STATUS_CHECKED_IN,
                TrainingEventParticipant::STATUS_ATTENDED,
            ], true);
    }
}
