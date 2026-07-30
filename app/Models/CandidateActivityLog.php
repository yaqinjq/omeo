<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateActivityLog extends Model
{
    protected $fillable = [
        'candidate_id',
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

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
