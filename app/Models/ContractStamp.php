<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractStamp extends Model
{
    public const TYPE_UPLOAD_PROOF = 'upload_proof';
    public const TYPE_NUMBER_INPUT = 'number_input';

    protected $fillable = [
        'contract_id',
        'stamp_type',
        'stamp_number',
        'stamp_proof_path',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
