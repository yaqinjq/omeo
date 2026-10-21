@extends('layouts.app')
@section('title', 'Peserta Non-MKO')
@section('content')
<div class="p-4" style="max-width:700px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Peserta Non-MKO</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">Peserta luar/customer/kenalan owner. Bukan karyawan HRD, tidak punya akun login OMEO.</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('sport.admin.participants.store') }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px; margin-bottom:16px; display:grid; gap:10px; grid-template-columns:1fr 1fr;">
        @csrf
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569;">Nama</label>
            <input type="text" name="full_name" required style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569;">Telepon (opsional)</label>
            <input type="text" name="phone" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; box-sizing:border-box;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569;">ID Eksternal (opsional)</label>
            <input type="text" name="external_reference" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; box-sizing:border-box;">
        </div>
        <div style="grid-column:1/-1; align-self:end;">
            <button type="submit" style="background:#4F46E5; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Tambah Peserta Non-MKO</button>
        </div>
    </form>

    <table style="width:100%; border-collapse:collapse; font-size:12.5px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:8px; text-align:left;">Nama</th><th style="padding:8px;">Telepon</th><th style="padding:8px;">ID Eksternal</th><th style="padding:8px;">Total Partisipasi</th></tr></thead>
        <tbody>
            @forelse($participants as $p)
            <tr style="border-top:1px solid #F1F5F9;">
                <td style="padding:7px;">{{ $p->full_name }}</td><td style="text-align:center;">{{ $p->phone ?: '-' }}</td><td style="text-align:center;">{{ $p->external_reference ?: '-' }}</td><td style="text-align:center;">{{ $p->attendances_count }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:16px; text-align:center; color:#94A3B8;">Belum ada peserta Non-MKO.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $participants->links() }}
</div>
@endsection
