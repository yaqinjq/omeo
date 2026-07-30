<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalkInCheckinToken extends Model
{
    protected $fillable = [
        'walk_in_event_id',
        'token_hash',
        'valid_from',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WalkInEvent::class, 'walk_in_event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $builder): void {
                $builder->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where('expires_at', '>=', now());
    }

    public static function issueFor(WalkInEvent $event, ?User $user = null, int $ttlSeconds = 90): array
    {
        $plain = Str::random(48);

        $token = static::query()->create([
            'walk_in_event_id' => $event->id,
            'token_hash' => hash('sha256', $plain),
            'valid_from' => now(),
            'expires_at' => now()->addSeconds($ttlSeconds),
            'created_by' => $user?->id,
        ]);

        return [$plain, $token];
    }

    public static function findValidPlainToken(string $plain): ?self
    {
        return static::query()
            ->valid()
            ->where('token_hash', hash('sha256', $plain))
            ->with('event.activePositions')
            ->first();
    }
}
