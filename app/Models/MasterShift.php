<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterShift extends Model
{
    protected $fillable = [
        'code',
        'name',
        'in_time',
        'out_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
