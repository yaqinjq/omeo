<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAssessment extends Model
{
    public const STATUS_IN_PROCESS = 'in_process';
    public const STATUS_PASSED = 'passed';
    public const STATUS_RESERVE = 'reserve';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'candidate_id',
        'iq_score',
        'disc_result',
        'interview_score',
        'interview_notes',
        'status',
    ];

    protected $casts = [
        'disc_result' => 'array',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}

