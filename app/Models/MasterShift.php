<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterShift extends Model
{
    protected $fillable = [
        'outlet_id',
        'code',
        'name',
        'in_time',
        'out_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
