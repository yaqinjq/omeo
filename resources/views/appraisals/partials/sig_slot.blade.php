@php
    $role         = $slot['role'];
    $signerField  = "signer_{$role}_id";
    $sigField     = "sig_{$role}";
    $sigAtField   = "sig_{$role}_at";

    $signerUser   = match($role) {
        'employee'   => $sigBatch->signerEmployee,
        'hrd'        => $sigBatch->signerHrd,
        'supervisor' => $sigBatch->signerSupervisor,
        'manager'    => $sigBatch->signerManager,
        default      => $sigBatch->signerDirector,
    };

    $signerId  = $sigBatch->$signerField;
    $sigData   = $sigBatch->$sigField;
    $sigAt     = $sigBatch->$sigAtField;
    $isSigned  = !empty($sigData);
    $isMySlot  = $signerId && (int)$signerId === (int)$currentUserId;
    $signRoute = route('appraisals.report-employee.sign', $employeeId);
    $signerRoute = route('appraisals.report-employee.save-signer', $employeeId);
@endphp
<div style="border:1.5px solid {{ $isSigned ? '#BBF7D0' : '#E2E8F0' }}; border-radius:12px; padding:14px 16px; background:{{ $isSigned ? '#F0FDF4' : 'white' }};">

    {{-- Header --}}
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
        <div style="background:{{ $slot['bg'] }}; color:{{ $slot['color'] }}; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700;">
            {{ $slot['label'] }}
        </div>
        @if($isSigned)
        <span style="background:#DCFCE7; color:#166534; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700;">&#10003; SUDAH DITANDATANGANI</span>
        @else
        <span style="background:#FEF9C3; color:#854D0E; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700;">&#9711; MENUNGGU</span>
        @endif
    </div>

    {{-- Signer info --}}
    <div style="font-size:11px; color:#64748B; margin-bottom:8px;">
        <b>SIGNER DITUGASKAN:</b>
        <span style="color:{{ $signerUser ? '#1e293b' : '#94A3B8' }}; font-weight:{{ $signerUser ? '600' : '400' }};">
            {{ $signerUser?->name ?? 'Belum ditentukan' }}
        </span>
        @if($signerUser)
        <span style="font-size:10px; color:#94A3B8;">({{ $signerUser->role }})</span>
        @endif
    </div>

    {{-- Signature display area --}}
    <div style="border:1.5px solid {{ $isSigned ? '#BBF7D0' : '#E2E8F0' }}; border-radius:8px; height:80px; background:{{ $isSigned ? '#F0FDF4' : '#F9FAFB' }}; display:flex; align-items:center; justify-content:center; margin-bottom:10px; overflow:hidden;">
        @if($isSigned)
        <img src="{{ $sigData }}" alt="Tanda Tangan" style="max-height:76px; max-width:100%; object-fit:contain;">
        @else
        <span style="font-size:11px; color:#CBD5E1; font-style:italic;">Belum ada tanda tangan digital</span>
        @endif
    </div>

    @if($isSigned && $sigAt)
    <div style="font-size:10px; color:#94A3B8; margin-bottom:8px;">
        &#128197; Ditandatangani: {{ $sigAt->format('d M Y H:i') }}
    </div>
    @endif

    {{-- Atur Signer (only admin/hrd) --}}
    @if($canManageSigner && !$isSigned)
    <form method="POST" action="{{ $signerRoute }}" style="display:flex; gap:6px; align-items:center; margin-bottom:8px;">
        @csrf
        <input type="hidden" name="batch_id" value="{{ $sigBatch->id }}">
        <input type="hidden" name="role"     value="{{ $role }}">
        <select name="signer_user_id"
                style="flex:1; border:1.5px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:11px; outline:none;">
            <option value="">— Belum ditentukan —</option>
            @foreach($allUsers as $u)
            <option value="{{ $u->id }}" {{ (int)$signerId === (int)$u->id ? 'selected' : '' }}>
                {{ $u->name }} ({{ $u->role }})
            </option>
            @endforeach
        </select>
        <button type="submit"
                style="background:#1e3a8a; color:white; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">
            Simpan Signer
        </button>
    </form>
    @endif

    {{-- Tanda Tangani button --}}
    @if($isMySlot && !$isSigned)
    <button @click="openFor({{ $sigBatch->id }}, '{{ $role }}', '{{ addslashes($slot['label']) }}', '{{ $signRoute }}')"
            style="width:100%; background:{{ $slot['color'] }}; color:white; border:none; padding:8px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
        &#9998; Tanda Tangani
    </button>
    @elseif($isMySlot && $isSigned)
    <div style="font-size:11px; color:#166534; font-weight:600; text-align:center; padding:6px;">&#10003; Tanda tangan Anda sudah tersimpan</div>
    @endif
</div>
