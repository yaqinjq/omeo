<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentCredentialLog extends Model
{
    protected $fillable = [
        'candidate_id',
        'user_id',
        'nik',
        'email',
        'phone',
        'last_applied_position',
        'status',
        'applied_at',
        'purged_at',
        'meta_json',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'purged_at' => 'datetime',
        'meta_json' => 'array',
    ];
}
