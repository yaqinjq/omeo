<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalBatchSignatureSlot extends Model
{
    public const MAX_SLOTS = 4;

    public const CATEGORIES = [
        'pic'              => 'PIC',
        'hrd'              => 'HRD / Super Administrator',
        'supervisor'       => 'Supervisor',
        'manager'          => 'Manager / ASPV / ASM / Head Dept',
        'director'         => 'Managing Director',
        'owner_in_charge'  => 'Owner In Charge',
    ];

    protected $fillable = [
        'batch_signature_id',
        'slot_order',
        'slot_type',
        'category',
        'label',
        'signer_user_id',
        'external_name',
        'signature_data',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AppraisalBatchSignature::class, 'batch_signature_id');
    }

    public function signerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    public function getIsSignedAttribute(): bool
    {
        return ! empty($this->signature_data);
    }
}
