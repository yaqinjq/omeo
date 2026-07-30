<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollImportRow extends Model
{
    protected $fillable = [
        'session_id', 'no_komp', 'nik', 'nama', 'outlet_name_raw',
        'posisi', 'join_date', 'gaji_pokok', 'bpjs_tk_raw', 'bpjs_jkes_raw',
        'total_gaji', 'bank_name', 'no_rekening', 'rekening_flag', 'tanggal_resign',
        'source_format', 'matched_employee_id', 'matched_outlet_id',
        'row_status', 'diff_snapshot', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'join_date'      => 'date',
        'tanggal_resign' => 'date',
        'reviewed_at'    => 'datetime',
        'diff_snapshot'  => 'array',
        'gaji_pokok'     => 'decimal:2',
        'bpjs_tk_raw'    => 'decimal:2',
        'bpjs_jkes_raw'  => 'decimal:2',
        'total_gaji'     => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PayrollImportSession::class, 'session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'matched_employee_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'matched_outlet_id');
    }

    public function getBpjsTkAmountAttribute(): float
    {
        return abs((float) $this->bpjs_tk_raw);
    }

    public function getBpjsJkesAmountAttribute(): float
    {
        return abs((float) $this->bpjs_jkes_raw);
    }

    public function isBpjsRegistered(): bool
    {
        return $this->getBpjsTkAmountAttribute() > 0 || $this->getBpjsJkesAmountAttribute() > 0;
    }
}
