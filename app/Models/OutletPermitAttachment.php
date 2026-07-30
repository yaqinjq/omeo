<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletPermitAttachment extends Model
{
    protected $fillable = [
        'outlet_permit_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function permit()
    {
        return $this->belongsTo(OutletPermit::class, 'outlet_permit_id');
    }
}
