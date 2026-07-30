<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateRetentionHistory extends Model
{
    protected $fillable = [
        'original_candidate_id',
        'user_id',
        'full_name',
        'email',
        'phone',
        'nik',
        'status',
        'decision_at',
        'deleted_at_retention',
        'retention_days',
        'delete_reason',
        'snapshot',
    ];

    protected $casts = [
        'decision_at' => 'datetime',
        'deleted_at_retention' => 'datetime',
        'snapshot' => 'array',
    ];
}
