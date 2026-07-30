<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWorkerContract extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'candidate_id',
        'contract_template_id',
        'contract_number',
        'status',
        'contract_html',
        'signed_contract_html',
        'stamp_file_path',
        'stamp_number',
        'stamp_confirmed',
        'candidate_signature_path',
        'candidate_note',
        'review_note',
        'sent_by',
        'reviewed_by',
        'sent_at',
        'viewed_at',
        'signed_at',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'stamp_confirmed' => 'boolean',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusHistories()
    {
        return $this->hasMany(DailyWorkerContractStatusHistory::class);
    }
}
