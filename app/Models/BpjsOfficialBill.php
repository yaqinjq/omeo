<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BpjsOfficialBill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'npp', 'nama_perusahaan', 'legal_entity_id', 'bpjs_legal_account_id',
        'periode', 'source_format', 'source_file_name', 'pdf_path',
        'total_rows_parsed', 'matched_count', 'unmatched_count',
        'total_iuran_official', 'status', 'notes', 'uploaded_by',
    ];

    protected $casts = [
        'total_iuran_official' => 'decimal:2',
        'total_rows_parsed'    => 'integer',
        'matched_count'        => 'integer',
        'unmatched_count'      => 'integer',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function bpjsLegalAccount(): BelongsTo
    {
        return $this->belongsTo(BpjsLegalAccount::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(BpjsOfficialBillRow::class, 'bill_id');
    }

    public function reconciliationResults(): HasMany
    {
        return $this->hasMany(BpjsReconciliationResult::class, 'bill_id');
    }

    public function getPeriodeLabelAttribute(): string
    {
        if (!$this->periode) return '-';
        [$y, $m] = explode('-', $this->periode);
        $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'][(int)$m - 1] ?? $m;
        return "{$bulan} {$y}";
    }
}
