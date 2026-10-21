<?php

namespace App\Models\Sport;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackdateAudit extends Model
{
    protected $table = 'sport_backdate_audits';

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';

    protected $fillable = [
        'sport_attendance_id',
        'sport_event_id',
        'employee_id',
        'action',
        'reason',
        'before_confirmed_at',
        'after_confirmed_at',
        'actor_user_id',
        'performed_at',
    ];

    protected $casts = [
        'before_confirmed_at' => 'datetime',
        'after_confirmed_at' => 'datetime',
        'performed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'sport_event_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
