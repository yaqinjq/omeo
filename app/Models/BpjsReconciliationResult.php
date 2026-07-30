<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsReconciliationResult extends Model
{
    protected $fillable = [
        'bill_id', 'bill_row_id', 'employee_id', 'periode',
        'official_jkk', 'official_jkm', 'official_jht_employee', 'official_jht_employer',
        'official_jp_employee', 'official_jp_employer', 'official_total',
        'omeo_gaji_pokok', 'omeo_bpjs_tk_employee', 'omeo_bpjs_tk_employer',
        'omeo_bpjs_jkes_employee', 'omeo_bpjs_jkes_employer', 'omeo_total',
        'selisih_total', 'penyebab', 'status', 'notes',
    ];

    protected $casts = [
        'official_jkk'          => 'decimal:2',
        'official_jkm'          => 'decimal:2',
        'official_jht_employee' => 'decimal:2',
        'official_jht_employer' => 'decimal:2',
        'official_jp_employee'  => 'decimal:2',
        'official_jp_employer'  => 'decimal:2',
        'official_total'        => 'decimal:2',
        'omeo_gaji_pokok'       => 'decimal:2',
        'omeo_bpjs_tk_employee' => 'decimal:2',
        'omeo_bpjs_tk_employer' => 'decimal:2',
        'omeo_bpjs_jkes_employee' => 'decimal:2',
        'omeo_bpjs_jkes_employer' => 'decimal:2',
        'omeo_total'            => 'decimal:2',
        'selisih_total'         => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(BpjsOfficialBill::class, 'bill_id');
    }

    public function billRow(): BelongsTo
    {
        return $this->belongsTo(BpjsOfficialBillRow::class, 'bill_row_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function selisihAbs(): float
    {
        return abs((float) $this->selisih_total);
    }
}
