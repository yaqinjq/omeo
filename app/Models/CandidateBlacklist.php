<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateBlacklist extends Model
{
    protected $fillable = [
        'candidate_id',
        'identifier_type',
        'identifier_value',
        'is_active',
        'created_by',
        'blacklisted_at',
        'metadata',
        'nik',
        'email',
        'phone',
        'reason',
        'last_applied_position',
        'blocked_at',
        'source',
        'meta_json',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'blacklisted_at' => 'datetime',
        'meta_json' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];
}
