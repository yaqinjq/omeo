<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'bank_code',
        'bank_name',
        'account_number',
        'account_holder_name',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function files()
    {
        return $this->hasMany(EmployeeBankAccountFile::class)->whereNull('deleted_at');
    }
}
