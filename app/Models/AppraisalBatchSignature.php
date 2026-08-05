<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalBatchSignature extends Model
{
    protected $fillable = [
        'employee_id', 'appraisal_period_id',
        'signer_employee_id', 'sig_employee', 'sig_employee_at',
        'signer_hrd_id',      'sig_hrd',      'sig_hrd_at',
        'signer_supervisor_id','sig_supervisor','sig_supervisor_at',
        'signer_manager_id',  'sig_manager',  'sig_manager_at',
        'signer_director_id', 'sig_director', 'sig_director_at',
    ];

    protected $casts = [
        'sig_employee_at'   => 'datetime',
        'sig_hrd_at'        => 'datetime',
        'sig_supervisor_at' => 'datetime',
        'sig_manager_at'    => 'datetime',
        'sig_director_at'   => 'datetime',
    ];

    public function signerEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_employee_id');
    }

    public function signerHrd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_hrd_id');
    }

    public function signerSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_supervisor_id');
    }

    public function signerManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_manager_id');
    }

    public function signerDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_director_id');
    }

    public function slots()
    {
        return $this->hasMany(AppraisalBatchSignatureSlot::class, 'batch_signature_id')->orderBy('slot_order');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function signedCount(): int
    {
        return $this->slots->filter(fn ($slot) => ! empty($slot->signature_data))->count();
    }
}
