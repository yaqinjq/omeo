<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'group',
        'description',
        // backward compatibility for old schema consumers
        'code',
        'group_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    public function getSlugAttribute($value): string
    {
        $slug = strtolower(trim((string) $value));
        if ($slug !== '') {
            return $slug;
        }

        return strtolower(trim((string) ($this->attributes['code'] ?? '')));
    }

    public function getGroupAttribute($value): string
    {
        $group = trim((string) $value);
        if ($group !== '') {
            return $group;
        }

        return trim((string) ($this->attributes['group_name'] ?? 'General'));
    }
}
