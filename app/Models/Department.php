<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'parent_id', 'sort_order', 'is_active'];

    protected $casts = [
        'deleted_at' => 'datetime',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
