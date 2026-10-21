<?php

namespace App\Models\Sport;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi reward badminton — HANYA DRAFT (lihat Spesifikasi v1.1
 * Section 7 & 13). Model ini TIDAK punya method kalkulasi payout, approval,
 * atau pembayaran apa pun secara sengaja — jangan tambahkan tanpa keputusan
 * owner soal threshold final (>=4 vs >4), tarif, periode, dan proses bayar.
 */
class RewardConfig extends Model
{
    protected $table = 'sport_reward_configs';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'sport',
        'tier1_min_sessions',
        'tier1_max_sessions',
        'tier1_rate',
        'tier2_min_sessions',
        'tier2_max_sessions',
        'tier2_rate',
        'tier3_min_sessions',
        'tier3_comparison',
        'tier3_rate',
        'period_label',
        'effective_date',
        'approver_user_id',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'tier1_rate' => 'decimal:2',
        'tier2_rate' => 'decimal:2',
        'tier3_rate' => 'decimal:2',
        'effective_date' => 'date',
    ];
}
