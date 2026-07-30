<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WalkInEvent extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'title',
        'slug',
        'status',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'address',
        'maps_url',
        'description',
        'quota',
        'participant_instruction',
        'banner_path',
    ];

    protected $casts = [
        'event_date' => 'date',
        'quota' => 'integer',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(WalkInEventPosition::class);
    }

    public function activePositions(): HasMany
    {
        return $this->positions()->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(WalkInRegistration::class);
    }

    public function checkinTokens(): HasMany
    {
        return $this->hasMany(WalkInCheckinToken::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->banner_path ? asset('storage/' . ltrim($this->banner_path, '/')) : null;
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->event_date
            && $this->event_date->endOfDay()->greaterThanOrEqualTo(now());
    }

    public function isCheckinOpen(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED || ! $this->event_date) {
            return false;
        }

        $start = Carbon::parse($this->event_date->toDateString() . ' ' . ($this->start_time ?: '00:00:00'))->subHours(2);
        $end = Carbon::parse($this->event_date->toDateString() . ' ' . ($this->end_time ?: '23:59:59'))->addHours(2);

        return now()->betweenIncluded($start, $end);
    }

    public function referralCodeFor(User $user): string
    {
        return 'HRD' . $user->id;
    }
}
