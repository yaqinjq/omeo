<?php

namespace App\Models\Sport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Peserta Non-MKO (luar) — BUKAN record employees/HRD, dan tidak punya
 * akun login (lihat Spesifikasi v1.1 Section 2 & 9). Data minimum saja.
 */
class Participant extends Model
{
    protected $table = 'sport_participants';

    protected $fillable = [
        'full_name',
        'phone',
        'external_reference',
        'notes',
        'created_by_user_id',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(NonMkoAttendance::class, 'sport_participant_id');
    }
}
