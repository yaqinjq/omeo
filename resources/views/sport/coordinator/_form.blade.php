{{-- Form field event Sport, dipakai bareng oleh create.blade.php & edit.blade.php --}}
@php($ev = $event ?? null)
<div style="display:grid; gap:12px;">
    <div>
        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Cabang Olahraga</label>
        <input type="text" name="sport" value="{{ old('sport', $ev->sport ?? '') }}" required placeholder="Contoh: badminton, tenis, padel"
               style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
    </div>
    <div>
        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Judul Event (opsional)</label>
        <input type="text" name="title" value="{{ old('title', $ev->title ?? '') }}" placeholder="Contoh: Badminton Rutin Jumat Sore"
               style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
    </div>
    <div>
        <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Lokasi/Alamat</label>
        <input type="text" name="location" value="{{ old('location', $ev->location ?? '') }}" required
               style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Tanggal</label>
            <input type="date" name="event_date" value="{{ old('event_date', isset($ev) ? $ev->event_date->format('Y-m-d') : '') }}" required
                   style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Jam Mulai (WIB)</label>
            <input type="datetime-local" name="start_at" value="{{ old('start_at', isset($ev) ? $ev->start_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('Y-m-d\TH:i') : '') }}" required
                   style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Jam Selesai (WIB)</label>
            <input type="datetime-local" name="end_at" value="{{ old('end_at', isset($ev) ? $ev->end_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('Y-m-d\TH:i') : '') }}" required
                   style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
        </div>
    </div>
    <p style="font-size:11px; color:#94A3B8; margin:-4px 0 0 0;">Jam mulai/selesai memakai Waktu Indonesia Barat (WIB, UTC+7) — sama seperti form event Walk-In Interview.</p>

    <div x-data="{ hasCoach: {{ old('has_coach', $ev->has_coach ?? false) ? 'true' : 'false' }} }">
        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#475569;">
            <input type="checkbox" name="has_coach" value="1" x-model="hasCoach" @checked(old('has_coach', $ev->has_coach ?? false))>
            Event ini memakai Coach
        </label>
        <div x-show="hasCoach" x-cloak style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Nama Coach</label>
                <input type="text" name="coach_name" value="{{ old('coach_name', $ev->coach_name ?? '') }}"
                       style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Kapasitas Peserta (wajib sebelum publish)</label>
                <input type="number" min="1" name="capacity" value="{{ old('capacity', $ev->capacity ?? '') }}"
                       style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
            </div>
        </div>
        <p style="font-size:11px; color:#94A3B8; margin-top:4px;">Tanpa coach, event ini tidak punya batas kuota peserta umum.</p>
    </div>
</div>
