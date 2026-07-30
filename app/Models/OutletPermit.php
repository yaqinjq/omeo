<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletPermit extends Model
{
    protected $fillable = [
        'outlet_id',
        'permit_type',
        'document_number',
        'issuer_name',
        'issued_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function attachments()
    {
        return $this->hasMany(OutletPermitAttachment::class)->orderByDesc('id');
    }
}
