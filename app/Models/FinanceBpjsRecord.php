<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceBpjsRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'outlet_id', 'import_session_id',
        'no_komp', 'nik', 'nama', 'outlet_name', 'posisi',
        'sub_dept', 'category', 'join_date_emp',
        'attd', 'hr', 's_expense',
        'total', 'ot1_amount', 'ot2_amount',
        'tunjangan_total', 'potongan_total', 'raw_csv_json',
        'periode', 'gaji_pokok',
        'bpjs_tk_employee', 'bpjs_jkes_employee',
        'bpjs_tk_employer', 'bpjs_jkes_employer',
        'is_bpjs_registered', 'source_format',
    ];

    protected $casts = [
        'is_bpjs_registered' => 'boolean',
        'join_date_emp'      => 'date',
        'attd'               => 'decimal:2',
        'hr'                 => 'decimal:2',
        's_expense'          => 'decimal:2',
        'total'              => 'decimal:2',
        'ot1_amount'         => 'decimal:2',
        'ot2_amount'         => 'decimal:2',
        'tunjangan_total'    => 'decimal:2',
        'potongan_total'     => 'decimal:2',
        'raw_csv_json'       => 'array',
        'gaji_pokok'         => 'decimal:2',
        'bpjs_tk_employee'   => 'decimal:2',
        'bpjs_jkes_employee' => 'decimal:2',
        'bpjs_tk_employer'   => 'decimal:2',
        'bpjs_jkes_employer' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(PayrollImportSession::class, 'import_session_id');
    }

    public function getTotalBpjsAttribute(): float
    {
        return (float) $this->bpjs_tk_employee + (float) $this->bpjs_jkes_employee
            + (float) $this->bpjs_tk_employer + (float) $this->bpjs_jkes_employer;
    }
}
