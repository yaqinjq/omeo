<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalEntity extends Model
{
    protected $fillable = [
        'company_group_id', 'name', 'short_name', 'entity_type',
        'npwp', 'nib', 'address', 'city', 'phone', 'email',
        'director_name', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function companyGroup()
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function regions()
    {
        return $this->hasMany(Region::class);
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function bpjsAccounts()
    {
        return $this->hasMany(BpjsLegalAccount::class);
    }
}
