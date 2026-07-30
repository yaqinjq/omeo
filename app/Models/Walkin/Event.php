<?php

namespace App\Models\Walkin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $table = 'walkin_events';

    protected $fillable = [
        'title',
        'event_start_datetime',
        'event_end_datetime',
        'timezone',
        'location',
        'description',
        'target_positions',
        'status',
        'registration_open_until',
        'created_by',
    ];

    protected $casts = [
        'event_start_datetime'    => 'datetime',
        'event_end_datetime'      => 'datetime',
        'registration_open_until' => 'datetime',
        'target_positions'        => 'array',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function referralLinks(): HasMany
    {
        return $this->hasMany(ReferralLink::class, 'event_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Computed status accessor ────────────────────────────────────────────────

    public function getComputedStatusAttribute(): string
    {
        $tz    = $this->timezone ?: 'Asia/Jakarta';
        $now   = now()->setTimezone($tz);
        $start = $this->event_start_datetime
            ? Carbon::parse($this->event_start_datetime)->setTimezone($tz)
            : null;
        $end   = $this->event_end_datetime
            ? Carbon::parse($this->event_end_datetime)->setTimezone($tz)
            : null;

        if (! $start) {
            return $this->status;
        }

        if ($now < $start) {
            return 'published';
        }

        if (! $end || $now <= $end) {
            return 'ongoing';
        }

        return 'completed';
    }

    // ── Status label/color use computed_status ─────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->computed_status) {
            'draft'     => 'Draft',
            'published' => 'Terbuka',
            'ongoing'   => 'Berlangsung',
            'completed' => 'Selesai',
            default     => ucfirst($this->computed_status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->computed_status) {
            'draft'     => '#94a3b8',
            'published' => '#1d4ed8',
            'ongoing'   => '#065f46',
            'completed' => '#6b7280',
            default     => '#94a3b8',
        };
    }

    public function getStatusBgAttribute(): string
    {
        return match ($this->computed_status) {
            'draft'     => '#f3f4f6',
            'published' => '#dbeafe',
            'ongoing'   => '#d1fae5',
            'completed' => '#f3f4f6',
            default     => '#f3f4f6',
        };
    }

    // ── Registration open check ────────────────────────────────────────────────

    public function isRegistrationOpen(): bool
    {
        $cs = $this->computed_status;

        if (! in_array($cs, ['published', 'ongoing'])) {
            return false;
        }

        if ($this->registration_open_until && $this->registration_open_until->isPast()) {
            return false;
        }

        return true;
    }
}
