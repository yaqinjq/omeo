<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormAssignment extends Model
{
    public const STATUS_LOCKED = 'locked';
    public const STATUS_OPENED = 'opened';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_LOCKED => 'Locked',
        self::STATUS_OPENED => 'Opened',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_EXPIRED => 'Expired',
    ];

    protected $fillable = [
        'form_id',
        'candidate_id',
        'status',
        'opened_at',
        'expires_at',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'form_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function attempt()
    {
        return $this->hasOne(FormAttempt::class, 'form_assignment_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_EXPIRED], true);
    }
}
