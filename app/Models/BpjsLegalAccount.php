<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BpjsLegalAccount extends Model
{
    protected $fillable = [
        'legal_entity_id', 'bpjs_type', 'account_number', 'account_name',
        'npp', 'upah_basis_perhitungan',
        'bpjs_branch', 'registered_date', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'registered_date'      => 'date',
        'is_active'            => 'boolean',
        'upah_basis_perhitungan' => 'decimal:2',
    ];

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
