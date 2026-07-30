<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLeave extends Model
{
    protected $fillable = [
        'user_id',
        'date_from',
        'date_to',
        'type',
        'approved_by',
        'approved_at',
        'note',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
