@php
    $isSigned  = $slot->is_signed;
    $isMySlot  = $slot->signer_user_id && (int) $slot->signer_user_id === (int) $currentUserId;
    $signRoute = route('appraisals.report-employee.sign', $employeeId);
    $signerRoute = route('appraisals.report-employee.save-signer', $employeeId);
    $badgeColor = match($slot->category) {
        'hrd'             => ['color' => '#DC2626', 'bg' => '#FEF2F2'],
        'supervisor'      => ['color' => '#059669', 'bg' => '#F0FDF4'],
        'manager'         => ['color' => '#D97706', 'bg' => '#FEF3C7'],
        'director'        => ['color' => '#0369A1', 'bg' => '#E0F2FE'],
        'owner_in_charge' => ['color' => '#7C2D92', 'bg' => '#FAF5FF'],
        default           => ['color' => '#7C3AED', 'bg' => '#F3E8FF'],
    };
    $candidates = $categoryCandidates[$slot->category] ?? null;
@endphp
<div style="border:1.5px solid {{ $isSigned ? '#BBF7D0' : '#E2E8F0' }}; border-radius:12px; padding:14px 16px; background:{{ $isSigned ? '#F0FDF4' : 'white' }};">

    {{-- Header --}}
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
        <div style="background:{{ $badgeColor['bg'] }}; color:{{ $badgeColor['color'] }}; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700;">
            {{ strtoupper($slot->label) }}
        </div>
        @if($isSigned)
        <span style="background:#DCFCE7; color:#166534; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700;">&#10003; SUDAH DITANDATANGANI</span>
        @else
        <span style="background:#FEF9C3; color:#854D0E; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700;">&#9711; MENUNGGU</span>
        @endif
    </div>

    {{-- Kategori picker (hanya untuk slot bukan Karyawan, bisa diubah HRD) --}}
    @if($canManageSigner && $slot->slot_type !== 'employee' && !$isSigned)
    <form method="POST" action="{{ $signerRoute }}" style="margin-bottom:8px;" x-data="{ cat: '{{ $slot->category }}' }">
        @csrf
        <input type="hidden" name="slot_id" value="{{ $slot->id }}">
        <div style="display:flex; gap:6px; align-items:center; margin-bottom:6px;">
            <label style="font-size:10px; color:#64748B; width:60px; flex-shrink:0;">Kategori</label>
            <select name="category" x-model="cat" style="flex:1; border:1.5px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:11px; outline:none;">
                <option value="">— Pilih kategori —</option>
                @foreach(\App\Models\AppraisalBatchSignatureSlot::CATEGORIES as $catKey => $catLabel)
                <option value="{{ $catKey }}" @selected($slot->category === $catKey)>{{ $catLabel }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="cat !== 'owner_in_charge'" style="display:flex; gap:6px; align-items:center;">
            <label style="font-size:10px; color:#64748B; width:60px; flex-shrink:0;">Penanda Tangan</label>
            <select name="signer_user_id" style="flex:1; border:1.5px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:11px; outline:none;">
                <option value="">— Belum ditentukan —</option>
                @if($candidates)
                    @foreach($candidates['users'] as $u)
                    <option value="{{ $u->id }}" {{ (int) $slot->signer_user_id === (int) $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->role }})</option>
                    @endforeach
                @endif
            </select>
        </div>
        @if($candidates && ! $candidates['filtered'])
        <div style="font-size:10px; color:#B45309; margin-top:4px;">&#9888; Belum ada karyawan dengan Posisi kategori ini terdaftar — menampilkan semua user. Lengkapi Master Data Posisi supaya daftar ini otomatis terfilter.</div>
        @endif

        <div x-show="cat === 'owner_in_charge'" style="margin-top:6px;">
            <label style="font-size:10px; color:#64748B;">Nama Owner In Charge</label>
            <input type="text" name="external_name" value="{{ $slot->external_name }}" placeholder="Diambil dari Master Outlet kalau kosong" style="width:100%; border:1.5px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:11px; outline:none;">
        </div>

        <button type="submit" style="margin-top:6px; width:100%; background:#1e3a8a; color:white; border:none; padding:6px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer;">
            Simpan
        </button>
    </form>
    @endif

    {{-- Signer info (view-only, slot Karyawan atau setelah ditandatangani) --}}
    @if($slot->slot_type === 'employee' || $isSigned || !$canManageSigner)
    <div style="font-size:11px; color:#64748B; margin-bottom:8px;">
        <b>{{ $slot->slot_type === 'manual' ? 'NAMA' : 'SIGNER' }}:</b>
        <span style="color:{{ ($slot->signerUser || $slot->external_name) ? '#1e293b' : '#94A3B8' }}; font-weight:{{ ($slot->signerUser || $slot->external_name) ? '600' : '400' }};">
            {{ $slot->signerUser?->name ?? $slot->external_name ?? 'Belum ditentukan' }}
        </span>
        @if($slot->signerUser)
        <span style="font-size:10px; color:#94A3B8;">({{ $slot->signerUser->role }})</span>
        @endif
    </div>
    @endif

    {{-- Signature display area --}}
    <div style="border:1.5px solid {{ $isSigned ? '#BBF7D0' : '#E2E8F0' }}; border-radius:8px; height:80px; background:{{ $isSigned ? '#F0FDF4' : '#F9FAFB' }}; display:flex; align-items:center; justify-content:center; margin-bottom:10px; overflow:hidden;">
        @if($isSigned)
        <img src="{{ $slot->signature_data }}" alt="Tanda Tangan" style="max-height:76px; max-width:100%; object-fit:contain;">
        @elseif($slot->slot_type === 'manual')
        <span style="font-size:11px; color:#CBD5E1; font-style:italic;">Tanda tangan fisik — tidak lewat sistem</span>
        @else
        <span style="font-size:11px; color:#CBD5E1; font-style:italic;">Belum ada tanda tangan digital</span>
        @endif
    </div>

    @if($isSigned && $slot->signed_at)
    <div style="font-size:10px; color:#94A3B8; margin-bottom:8px;">
        &#128197; Ditandatangani: {{ $slot->signed_at->format('d M Y H:i') }}
    </div>
    @endif

    {{-- Tanda Tangani button --}}
    @if($isMySlot && !$isSigned)
    <button @click="openFor({{ $slot->batch_signature_id }}, {{ $slot->id }}, '{{ addslashes($slot->label) }}', '{{ $signRoute }}')"
            style="width:100%; background:{{ $badgeColor['color'] }}; color:white; border:none; padding:8px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
        &#9998; Tanda Tangani
    </button>
    @elseif($isMySlot && $isSigned)
    <div style="font-size:11px; color:#166534; font-weight:600; text-align:center; padding:6px;">&#10003; Tanda tangan Anda sudah tersimpan</div>
    @endif
</div>
