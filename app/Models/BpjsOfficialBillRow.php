<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsOfficialBillRow extends Model
{
    protected $fillable = [
        'bill_id', 'nik', 'no_peserta', 'nama',
        'iuran_jkk', 'iuran_jkm',
        'iuran_jht_employee', 'iuran_jht_employer',
        'iuran_jp_employee', 'iuran_jp_employer',
        'total_iuran',
        'match_status', 'employee_id', 'match_confidence', 'match_note',
    ];

    protected $casts = [
        'iuran_jkk'          => 'decimal:2',
        'iuran_jkm'          => 'decimal:2',
        'iuran_jht_employee' => 'decimal:2',
        'iuran_jht_employer' => 'decimal:2',
        'iuran_jp_employee'  => 'decimal:2',
        'iuran_jp_employer'  => 'decimal:2',
        'total_iuran'        => 'decimal:2',
        'match_confidence'   => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(BpjsOfficialBill::class, 'bill_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reconciliationResult(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BpjsReconciliationResult::class, 'bill_row_id');
    }
}
