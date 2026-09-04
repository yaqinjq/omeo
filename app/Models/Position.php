<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = [
        'name', 'code', 'level', 'department_id', 'approval_level', 'sort_order',
        'salary_min', 'salary_max', 'is_active', 'description',
        'parent_position_id', 'representative_employee_id',
    ];

    protected $casts = [
        'salary_min'     => 'decimal:2',
        'salary_max'     => 'decimal:2',
        'is_active'      => 'boolean',
        'level'          => 'integer',
        'approval_level' => 'integer',
        'sort_order'     => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function parentPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'parent_position_id');
    }

    public function childPositions(): HasMany
    {
        return $this->hasMany(Position::class, 'parent_position_id');
    }

    public function representativeEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'representative_employee_id');
    }
}
