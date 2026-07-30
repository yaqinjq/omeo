<?php

namespace App\Models\Walkin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralLink extends Model
{
    protected $table = 'walkin_referral_links';

    protected $fillable = [
        'event_id',
        'user_id',
        'referral_code',
        'total_registrations',
    ];

    protected $casts = [
        'total_registrations' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('walkin.register', $this->referral_code);
    }
}
