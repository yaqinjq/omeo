<?php

namespace App\Models\Walkin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    protected $table = 'walkin_candidates';

    protected $fillable = [
        'event_id',
        'referral_code',
        'referred_by_user_id',
        'candidate_name',
        'candidate_phone',
        'candidate_email',
        'ig_account',
        'applied_position',
        'registration_number',
        'registration_status',
        'check_in_time',
        'interview_status',
        'interviewed_by',
        'interview_notes',
        'application_form_data',
        'test_type',
        'test_score',
        'test_status',
        'plotting_outlet_id',
        'plotting_position',
        'final_status',
        'announcement_sent_at',
        'notes',
    ];

    protected $casts = [
        'check_in_time'         => 'datetime',
        'announcement_sent_at'  => 'datetime',
        'application_form_data' => 'array',
        'test_score'            => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewed_by');
    }

    public function getRegistrationStatusLabelAttribute(): string
    {
        return match ($this->registration_status) {
            'registered' => 'Terdaftar',
            'checked_in' => 'Hadir',
            'no_show'    => 'Tidak Hadir',
            'withdrawn'  => 'Mundur',
            default      => ucfirst($this->registration_status),
        };
    }

    public function getInterviewStatusLabelAttribute(): string
    {
        return match ($this->interview_status) {
            'pending' => 'Menunggu',
            'passed'  => 'Lolos',
            'failed'  => 'Tidak Lolos',
            'skipped' => 'Dilewati',
            default   => ucfirst($this->interview_status),
        };
    }

    public function getFinalStatusLabelAttribute(): string
    {
        return match ($this->final_status) {
            'pending'  => 'Menunggu',
            'accepted' => 'Diterima',
            'reserved' => 'Cadangan',
            'rejected' => 'Ditolak',
            default    => ucfirst($this->final_status),
        };
    }
}
