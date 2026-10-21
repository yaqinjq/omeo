<?php

namespace App\Models\Sport;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'sport_attendances';

    protected $fillable = [
        'sport_event_id',
        'sport_registration_id',
        'employee_id',
        'confirmed_at',
        'is_backdated',
        'backdated_by_user_id',
        'backdate_reason',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'is_backdated' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'sport_event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'sport_registration_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
