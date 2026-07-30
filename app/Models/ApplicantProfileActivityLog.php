<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantProfileActivityLog extends Model
{
    protected $fillable = [
        'applicant_profile_id',
        'actor_user_id',
        'action_type',
        'old_status',
        'new_status',
        'ip_address',
        'user_agent',
        'source_page',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function applicantProfile()
    {
        return $this->belongsTo(ApplicantProfile::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
