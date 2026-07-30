<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceBpjsOutletSummary extends Model
{
    protected $fillable = [
        'outlet_id', 'periode', 'total_karyawan',
        'karyawan_terdaftar_bpjs', 'karyawan_tidak_terdaftar',
        'total_bpjs_tk', 'total_bpjs_jkes', 'total_bpjs_keseluruhan',
        'status_bayar', 'tanggal_bayar', 'bukti_bayar_path',
        'nomor_referensi', 'verified_by', 'verified_at', 'catatan',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'verified_at'   => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status_bayar) {
            'belum_bayar' => 'Belum Bayar',
            'sudah_bayar' => 'Sudah Bayar',
            'verified'    => 'Terverifikasi',
            default       => $this->status_bayar,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status_bayar) {
            'belum_bayar' => 'red',
            'sudah_bayar' => 'yellow',
            'verified'    => 'green',
            default       => 'gray',
        };
    }
}
