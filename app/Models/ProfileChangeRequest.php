<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ENTITY_EMPLOYEE_PROFILE = 'employee_profile';

    protected $fillable = [
        'user_id',
        'entity_type',
        'changes_json',
        'attachments_json',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'changes_json' => 'array',
        'attachments_json' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
