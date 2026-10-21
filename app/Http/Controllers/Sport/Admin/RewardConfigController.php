<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\RewardConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Konfigurasi reward badminton — DRAFT SAJA (Spesifikasi v1.1 Section 7).
 * Controller ini SENGAJA tidak punya aksi "activate"/"approve payout" apa
 * pun. Field status selalu dipaksa 'draft' pada setiap save, terlepas dari
 * apa yang dikirim form — supaya tidak ada jalan, sengaja atau tidak, untuk
 * mengaktifkan kalkulasi/pembayaran dari sisi UI sebelum owner memutuskan
 * threshold (ambiguitas >=4 vs >4 — lihat tier3_comparison), tarif, periode,
 * dan proses approval/pembayaran yang sebenarnya.
 */
class RewardConfigController extends Controller
{
    public function index(): View
    {
        $config = RewardConfig::where('sport', 'badminton')->first();

        return view('sport.admin.reward.show', ['config' => $config]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tier1_min_sessions' => ['nullable', 'integer', 'min:0'],
            'tier1_max_sessions' => ['nullable', 'integer', 'min:0'],
            'tier1_rate' => ['nullable', 'numeric', 'min:0'],
            'tier2_min_sessions' => ['nullable', 'integer', 'min:0'],
            'tier2_max_sessions' => ['nullable', 'integer', 'min:0'],
            'tier2_rate' => ['nullable', 'numeric', 'min:0'],
            'tier3_min_sessions' => ['nullable', 'integer', 'min:0'],
            'tier3_comparison' => ['nullable', 'in:gte,gt'],
            'tier3_rate' => ['nullable', 'numeric', 'min:0'],
            'period_label' => ['nullable', 'string', 'max:50'],
            'effective_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['sport'] = 'badminton';
        $data['status'] = RewardConfig::STATUS_DRAFT; // dipaksa, bukan dari input
        $data['updated_by_user_id'] = $request->user()->id;

        $existing = RewardConfig::where('sport', 'badminton')->first();
        if ($existing) {
            $existing->update($data);
        } else {
            $data['created_by_user_id'] = $request->user()->id;
            RewardConfig::create($data);
        }

        return back()->with('success', 'Draft konfigurasi reward badminton tersimpan. Belum aktif — payout, approval, dan jurnal tetap tidak berjalan.');
    }
}
