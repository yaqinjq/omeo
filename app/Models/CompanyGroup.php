<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyGroup extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'npwp',
        'address',
        'description',
        'website',
        'headquarters_city',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function legalEntities()
    {
        return $this->hasMany(LegalEntity::class);
    }
}
