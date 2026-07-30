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
}
