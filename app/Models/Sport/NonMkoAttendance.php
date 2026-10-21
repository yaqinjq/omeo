<?php

namespace App\Models\Sport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partisipasi peserta Non-MKO pada 1 event — selalu diinput manual oleh
 * Admin Finance (tidak ada alur self-service untuk Non-MKO).
 */
class NonMkoAttendance extends Model
{
    protected $table = 'sport_non_mko_attendances';

    protected $fillable = [
        'sport_event_id',
        'sport_participant_id',
        'recorded_by_user_id',
        'attended_at',
        'notes',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'sport_event_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'sport_participant_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
