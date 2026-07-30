<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAnnualSummary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'import_session_id', 'employee_id', 'outlet_id',
        'no_komp', 'nik', 'nama', 'outlet_name_raw', 'posisi',
        'join_date', 'tahun',
        'gaji_jan', 'gaji_feb', 'gaji_mar', 'gaji_apr', 'gaji_mei', 'gaji_jun',
        'gaji_jul', 'gaji_aug', 'gaji_sep', 'gaji_okt', 'gaji_nov', 'gaji_des',
        'thr', 'total_tahunan', 'bulan_aktif',
    ];

    protected $casts = [
        'join_date'     => 'date',
        'gaji_jan'      => 'decimal:2', 'gaji_feb' => 'decimal:2',
        'gaji_mar'      => 'decimal:2', 'gaji_apr' => 'decimal:2',
        'gaji_mei'      => 'decimal:2', 'gaji_jun' => 'decimal:2',
        'gaji_jul'      => 'decimal:2', 'gaji_aug' => 'decimal:2',
        'gaji_sep'      => 'decimal:2', 'gaji_okt' => 'decimal:2',
        'gaji_nov'      => 'decimal:2', 'gaji_des' => 'decimal:2',
        'thr'           => 'decimal:2',
        'total_tahunan' => 'decimal:2',
    ];

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(PayrollAnnualImportSession::class, 'import_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function gajiByBulan(int $bulan): float
    {
        $cols = [
            1 => 'gaji_jan', 2 => 'gaji_feb', 3 => 'gaji_mar', 4 => 'gaji_apr',
            5 => 'gaji_mei', 6 => 'gaji_jun', 7 => 'gaji_jul', 8 => 'gaji_aug',
            9 => 'gaji_sep', 10 => 'gaji_okt', 11 => 'gaji_nov', 12 => 'gaji_des',
        ];
        return (float) ($this->{$cols[$bulan]} ?? 0);
    }

    public function gajiPerBulan(): array
    {
        return [
            1  => (float) $this->gaji_jan,
            2  => (float) $this->gaji_feb,
            3  => (float) $this->gaji_mar,
            4  => (float) $this->gaji_apr,
            5  => (float) $this->gaji_mei,
            6  => (float) $this->gaji_jun,
            7  => (float) $this->gaji_jul,
            8  => (float) $this->gaji_aug,
            9  => (float) $this->gaji_sep,
            10 => (float) $this->gaji_okt,
            11 => (float) $this->gaji_nov,
            12 => (float) $this->gaji_des,
        ];
    }
}
