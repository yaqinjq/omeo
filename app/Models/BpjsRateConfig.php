<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BpjsRateConfig extends Model
{
    protected $fillable = [
        'region_id', 'periode_mulai', 'periode_selesai',
        'jkk_rate', 'jkm_rate',
        'jht_employer_rate', 'jht_employee_rate',
        'jp_employer_rate', 'jp_employee_rate',
        'bpjskes_employer_rate', 'bpjskes_employee_rate',
        'bpjskes_salary_cap', 'basis_perhitungan', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'jkk_rate'              => 'decimal:4',
        'jkm_rate'              => 'decimal:4',
        'jht_employer_rate'     => 'decimal:4',
        'jht_employee_rate'     => 'decimal:4',
        'jp_employer_rate'      => 'decimal:4',
        'jp_employee_rate'      => 'decimal:4',
        'bpjskes_employer_rate' => 'decimal:4',
        'bpjskes_employee_rate' => 'decimal:4',
        'bpjskes_salary_cap'    => 'decimal:2',
        'is_active'             => 'boolean',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
