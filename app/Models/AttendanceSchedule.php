<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'outlet_id',
        'master_shift_id',
        'shift_code',
        'work_date',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function shift()
    {
        return $this->belongsTo(MasterShift::class, 'master_shift_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
