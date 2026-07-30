<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalInvitationLog extends Model
{
    protected $table = 'appraisal_invitation_logs';

    protected $fillable = [
        'appraisal_id',
        'actor_user_id',
        'target_user_id',
        'action',
        'notes',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class, 'appraisal_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
