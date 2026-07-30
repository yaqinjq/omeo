<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Candidate extends Model
{
    use SoftDeletes;

    public const STATUS_APPLIED = 'applied';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_BLOCKED = 'blocked';

    public const EDITABLE_STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_SHORTLISTED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_BLOCKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_APPLIED => 'Applied',
        self::STATUS_SHORTLISTED => 'Shortlist',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_BLOCKED => 'Blocked',
    ];

    /**
     * @var array<string,bool>
     */
    private static array $columnSupportCache = [];

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'nik',
        'status',
        'notes',
        'applied_position_id',
        'applied_position_name',
        'applied_department_id',
        'applied_department_name',
        'applied_outlet_id',
        'applied_outlet_name',
        'applied_at',
        'accepted_at',
        'rejected_at',
        'blocked_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public static function supportsAppliedProfileColumns(): bool
    {
        foreach ([
            'applied_position_id',
            'applied_position_name',
            'applied_department_id',
            'applied_department_name',
            'applied_outlet_id',
            'applied_outlet_name',
        ] as $column) {
            $cacheKey = static::class . '|' . $column;
            $supported = self::$columnSupportCache[$cacheKey] ??= Schema::hasColumn((new static())->getTable(), $column);
            if (! $supported) {
                return false;
            }
        }

        return true;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessment()
    {
        return $this->hasOne(CandidateAssessment::class);
    }

    public function formAssignments()
    {
        return $this->hasMany(FormAssignment::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(CandidateActivityLog::class)->latest('id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function latestContract()
    {
        return $this->hasOne(Contract::class)->latestOfMany();
    }

    public function dailyWorkerContracts()
    {
        return $this->hasMany(DailyWorkerContract::class);
    }

    public function latestDailyWorkerContract()
    {
        return $this->hasOne(DailyWorkerContract::class)->latestOfMany();
    }

    public function appliedPosition()
    {
        return $this->belongsTo(Position::class, 'applied_position_id');
    }

    public function appliedDepartment()
    {
        return $this->belongsTo(Department::class, 'applied_department_id');
    }

    public function appliedOutlet()
    {
        return $this->belongsTo(Outlet::class, 'applied_outlet_id');
    }
}
