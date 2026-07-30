<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWorkerContractStatusHistory extends Model
{
    protected $fillable = [
        'daily_worker_contract_id',
        'status',
        'actor_user_id',
        'note',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(DailyWorkerContract::class, 'daily_worker_contract_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
