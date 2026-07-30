@extends('layouts.app')

@section('content')
@php
  $session = $todaySession;
  $currentOutlet = $selectedOutlet;
  $tz = $currentOutlet?->timezone ?? $timezone;
  $attendanceAccessAllowed = (bool) data_get($attendanceEligibility ?? [], 'allowed', true);
  $canCheckIn = $attendanceAccessAllowed && $attendanceState === 'before_check_in';
  $canCheckOut = $attendanceAccessAllowed && $attendanceState === 'before_check_out';
  $statusLabel = $attendanceState === 'before_check_in' ? 'Belum hadir' : ($attendanceState === 'before_check_out' ? 'Menunggu pulang' : 'Presensi lengkap');
  $activeCard = $canCheckIn ? 'in' : ($canCheckOut ? 'out' : null);
  $needsProfileCompletion = ! (bool) data_get($attendanceEligibility ?? [], 'profile_complete', true);
  $needsPayrollCompletion = (bool) data_get($attendanceEligibility ?? [], 'requires_payroll', false) && ! (bool) data_get($attendanceEligibility ?? [], 'payroll_complete', true);
  $missingProfileSections = array_values(array_filter((array) data_get($attendanceEligibility ?? [], 'context.missing_profile_sections', [])));
  $attendanceLockTitle = $needsPayrollCompletion
    ? 'Presensi menunggu payroll lengkap dan verifikasi HRD'
    : 'Presensi menunggu profil karyawan lengkap';
  $stateHint = !$attendanceAccessAllowed
    ? 'Presensi dikunci sementara sampai kelengkapan data yang diwajibkan selesai.'
    : ($attendanceState === 'before_check_in'
      ? 'Langkah aktif sekarang adalah presensi datang. Setelah berhasil, tombol pulang otomatis aktif.'
      : ($attendanceState === 'before_check_out'
        ? 'Presensi datang sudah tersimpan. Sekarang lanjutkan presensi pulang dengan bukti live baru.'
        : 'Presensi hari ini sudah lengkap. Anda dapat meninjau status dan riwayat di bawah.'));
  $accuracyLimit = (int) ($maxAccuracyMeters ?? 50);
  $radiusLimit = (int) ($currentOutlet->radius_meters ?? $currentOutlet->geofence_radius_m ?? 5);
  $cards = [
    [
      'scan' => 'in',
      'title' => 'Presensi Datang',
      'route' => route('attendance.check-in'),
      'enabled' => $canCheckIn,
      'button' => 'Konfirmasi Hadir',
      'summary' => 'Ambil selfie live dan foto lingkungan live untuk menyimpan kehadiran hari ini.',
    ],
    [
      'scan' => 'out',
      'title' => 'Presensi Pulang',
      'route' => route('attendance.check-out'),
      'enabled' => $canCheckOut,
      'button' => 'Konfirmasi Pulang',
      'summary' => 'Gunakan bukti live baru untuk menyimpan kepulangan. Bukti hadir tidak dipakai ulang.',
    ],
  ];
@endphp

<div class="space-y-5 pb-28">
  @if(session('success'))
    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">{{ session('success') }}</div>
  @endif

  @if(session('error'))
    <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 shadow-sm">{{ session('error') }}</div>
  @endif

  @include('attendance.partials.hero')

  @unless($attendanceAccessAllowed)
    @include('attendance.partials.lock-notice')
  @endunless

  @if(!$assignedOutlet)
    @include('attendance.partials.outlet-picker')
  @endif

  @if(!$currentOutlet)
    <div class="rounded-[2rem] border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900 shadow-sm">Outlet belum tersedia. Minta HRD set outlet pada profil karyawan atau pilih outlet sementara di atas.</div>
  @else
    <div id="geoAlert" class="hidden rounded-[2rem] border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm"></div>

    <div class="space-y-5">
      <section class="space-y-4" aria-label="Langkah presensi utama">
        @foreach($cards as $card)
          @include('attendance.partials.action-card', ['card' => $card])
        @endforeach
      </section>

      @include('attendance.partials.sidebar')
    </div>

    @if($activeCard)
      <div class="fixed inset-x-0 bottom-3 z-50 px-3 sm:px-6 lg:hidden">
        <div class="mx-auto max-w-3xl rounded-[1.75rem] border border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur">
          <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
              <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Langkah aktif</div>
              <div class="truncate text-sm font-bold text-slate-900">{{ $activeCard === 'in' ? 'Presensi Datang siap dipakai' : 'Presensi Pulang siap dipakai' }}</div>
            </div>
            <a href="#attendance-card-{{ $activeCard }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm">Buka Panel</a>
          </div>
        </div>
      </div>
    @endif

    @include('attendance.partials.modals')
    @include('attendance.partials.scripts')
  @endif
</div>
@endsection
