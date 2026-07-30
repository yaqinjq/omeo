<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractSignature extends Model
{
    protected $fillable = [
        'contract_id',
        'signer_role',
        'signer_name',
        'signature_image_path',
        'signed_at',
        'meta_json',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
