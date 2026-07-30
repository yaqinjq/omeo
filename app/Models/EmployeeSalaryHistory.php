<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalaryHistory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'amount',
        'effective_date',
        'changed_by',
        'source',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
