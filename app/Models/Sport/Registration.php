<?php

namespace App\Models\Sport;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    protected $table = 'sport_registrations';

    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sport_event_id',
        'employee_id',
        'user_id',
        'status',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'sport_event_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class, 'sport_registration_id');
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class, 'sport_registration_id');
    }
}
