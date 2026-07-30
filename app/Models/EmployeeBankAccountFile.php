<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBankAccountFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_bank_account_id',
        'file_path',
        'original_name',
        'mime_type',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(EmployeeBankAccount::class, 'employee_bank_account_id');
    }
}
