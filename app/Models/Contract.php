<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_AWAITING_STAMP = 'awaiting_stamp';
    public const STATUS_AWAITING_SIGNATURE = 'awaiting_signature';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_HR_REVIEW = 'hr_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'contract_template_id',
        'candidate_id',
        'contract_number',
        'status',
        'sent_at',
        'viewed_at',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'created_by',
        'updated_by',
        'pdf_path_original',
        'pdf_path_signed',
        'meta_json',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function signatures()
    {
        return $this->hasMany(ContractSignature::class);
    }

    public function latestCandidateSignature()
    {
        return $this->hasOne(ContractSignature::class)
            ->where('signer_role', 'candidate')
            ->orderByDesc('id');
    }

    public function stamps()
    {
        return $this->hasMany(ContractStamp::class);
    }

    public function latestStamp()
    {
        return $this->hasOne(ContractStamp::class)
            ->orderByDesc('id');
    }
}
