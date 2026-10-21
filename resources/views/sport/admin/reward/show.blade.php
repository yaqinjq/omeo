@extends('layouts.app')
@section('title', 'Draft Reward Badminton')
@section('content')
<div class="p-4" style="max-width:600px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Draft Kebijakan Reward Badminton</h1>
    <div style="background:#FEF2F2; border:1.5px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:12.5px; margin-bottom:16px; font-weight:600;">
        ⚠ DRAFT SAJA — belum aktif. Tidak ada kalkulasi payout, approval, pembayaran, atau jurnal yang berjalan dari halaman ini.
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('sport.admin.reward.store') }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:16px; display:grid; gap:12px;">
        @csrf
        <p style="font-size:11.5px; color:#64748B; margin:0;">Ambiguitas threshold "4 kali" vs "lebih dari 4 kali" pada Tier 3 BELUM diputuskan owner — pilih pembanding secara eksplisit di bawah atau biarkan kosong sampai diputuskan.</p>

        @foreach(['tier1' => 'Tier 1', 'tier2' => 'Tier 2'] as $key => $label)
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; align-items:end;">
            <div><label style="font-size:11px; color:#475569; font-weight:600;">{{ $label }} Min Sesi</label><input type="number" min="0" name="{{ $key }}_min_sessions" value="{{ old($key.'_min_sessions', $config?->{$key.'_min_sessions'}) }}" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
            <div><label style="font-size:11px; color:#475569; font-weight:600;">{{ $label }} Max Sesi</label><input type="number" min="0" name="{{ $key }}_max_sessions" value="{{ old($key.'_max_sessions', $config?->{$key.'_max_sessions'}) }}" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
            <div><label style="font-size:11px; color:#475569; font-weight:600;">Tarif (Rp)</label><input type="number" min="0" name="{{ $key }}_rate" value="{{ old($key.'_rate', $config?->{$key.'_rate'}) }}" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
        </div>
        @endforeach

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; align-items:end;">
            <div><label style="font-size:11px; color:#475569; font-weight:600;">Tier 3 Min Sesi</label><input type="number" min="0" name="tier3_min_sessions" value="{{ old('tier3_min_sessions', $config?->tier3_min_sessions) }}" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
            <div>
                <label style="font-size:11px; color:#475569; font-weight:600;">Pembanding</label>
                <select name="tier3_comparison" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;">
                    <option value="">— Belum diputuskan —</option>
                    <option value="gte" @selected(old('tier3_comparison', $config?->tier3_comparison) === 'gte')>>= (minimal)</option>
                    <option value="gt" @selected(old('tier3_comparison', $config?->tier3_comparison) === 'gt')>&gt; (lebih dari)</option>
                </select>
            </div>
            <div><label style="font-size:11px; color:#475569; font-weight:600;">Tarif (Rp)</label><input type="number" min="0" name="tier3_rate" value="{{ old('tier3_rate', $config?->tier3_rate) }}" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
            <div><label style="font-size:11px; color:#475569; font-weight:600;">Periode (bebas teks — belum disepakati)</label><input type="text" name="period_label" value="{{ old('period_label', $config?->period_label) }}" placeholder="misal: per bulan" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
            <div><label style="font-size:11px; color:#475569; font-weight:600;">Tanggal Efektif (draft)</label><input type="date" name="effective_date" value="{{ old('effective_date', $config?->effective_date?->format('Y-m-d')) }}" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px;"></div>
        </div>

        <div><label style="font-size:11px; color:#475569; font-weight:600;">Catatan</label><textarea name="notes" rows="2" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:6px; font-size:12.5px; box-sizing:border-box;">{{ old('notes', $config?->notes) }}</textarea></div>

        <button type="submit" style="background:#4F46E5; color:white; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Simpan Draft</button>
    </form>
</div>
@endsection
