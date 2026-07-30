<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalEditRequest extends Model
{
    protected $table = 'appraisal_edit_requests';

    protected $fillable = [
        'appraisal_id',
        'requested_by_user_id',
        'reason',
        'status',
        'reviewed_by_user_id',
        'review_note',
        'reviewed_at',
        'used_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'used_at'     => 'datetime',
    ];

    public const MAX_APPROVED_PER_APPRAISAL = 2;

    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
