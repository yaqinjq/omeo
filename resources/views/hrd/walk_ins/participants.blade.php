@extends('layouts.app')

@section('page_title', 'Peserta Walk In')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Peserta: {{ $event->title }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $event->event_date?->format('d/m/Y') }} - {{ $event->location ?: '-' }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('dashboard.walk-ins.checkin-qr', $event) }}" class="btn-primary">Buka QR Check-in</a>
        <a href="{{ route('dashboard.walk-ins.index') }}" class="btn-outline">Kembali</a>
      </div>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-3">
    <div class="card p-4"><div class="text-sm text-muted">Total Pendaftar</div><div class="mt-2 text-2xl font-bold">{{ $event->registrations()->count() }}</div></div>
    <div class="card p-4"><div class="text-sm text-muted">Hadir</div><div class="mt-2 text-2xl font-bold">{{ $event->registrations()->whereNotNull('checked_in_at')->count() }}</div></div>
    <div class="card p-4"><div class="text-sm text-muted">Lolos</div><div class="mt-2 text-2xl font-bold">{{ $event->registrations()->where('status', \App\Models\WalkInRegistration::STATUS_PASSED)->count() }}</div></div>
  </div>

  <div class="card p-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
      <label class="text-sm font-semibold">Status
        <select name="status" class="mt-1 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
          <option value="">Semua</option>
          @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-semibold">Posisi
        <select name="position" class="mt-1 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
          <option value="">Semua</option>
          @foreach($event->positions as $position)
            <option value="{{ $position->id }}" @selected((string) request('position') === (string) $position->id)>{{ $position->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-semibold">Referral
        <select name="referral" class="mt-1 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
          <option value="">Semua</option>
          @foreach($referrals as $referral)
            <option value="{{ $referral->referral_code }}" @selected(request('referral') === $referral->referral_code)>{{ $referral->referral_code }} ({{ $referral->total }})</option>
          @endforeach
        </select>
      </label>
      <button class="btn" type="submit">Filter</button>
      <a href="{{ route('dashboard.walk-ins.participants', $event) }}" class="btn-ghost">Reset</a>
    </form>
  </div>

  <div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          <tr>
            <th class="px-4 py-3">Peserta</th>
            <th class="px-4 py-3">Posisi</th>
            <th class="px-4 py-3">Referral</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Waktu</th>
            <th class="px-4 py-3 text-right">Screening</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          @forelse($registrations as $registration)
            <tr>
              <td class="px-4 py-3">
                <div class="font-semibold">{{ $registration->full_name }}</div>
                <div class="text-xs text-muted">{{ $registration->registration_code }}</div>
                <div class="text-xs text-muted">WA: {{ $registration->whatsapp_number }}</div>
                <div class="text-xs text-muted">{{ $registration->email ?: '-' }}</div>
              </td>
              <td class="px-4 py-3">{{ $registration->position?->name ?? '-' }}</td>
              <td class="px-4 py-3">
                {{ $registration->referral_code ?: '-' }}
                @if($registration->referredUser)
                  <div class="text-xs text-muted">{{ $registration->referredUser->name }}</div>
                @endif
              </td>
              <td class="px-4 py-3">
                <span class="badge">{{ ucfirst(str_replace('_', ' ', $registration->status)) }}</span>
                @if($registration->status === \App\Models\WalkInRegistration::STATUS_PASSED)
                  <a href="{{ route('register') }}" class="mt-2 block text-xs font-semibold text-brand">Lanjut application form</a>
                @endif
              </td>
              <td class="px-4 py-3 text-xs text-muted">
                Daftar: {{ $registration->created_at?->format('d/m/Y H:i') }}<br>
                Hadir: {{ $registration->checked_in_at?->format('d/m/Y H:i') ?: '-' }}<br>
                Screen: {{ $registration->screened_at?->format('d/m/Y H:i') ?: '-' }}
              </td>
              <td class="px-4 py-3">
                <form method="POST" action="{{ route('dashboard.walk-ins.participants.update', [$event, $registration]) }}" class="ml-auto max-w-xs space-y-2">
                  @csrf
                  @method('PUT')
                  <select name="status" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950/50">
                    @foreach([\App\Models\WalkInRegistration::STATUS_SCREENED, \App\Models\WalkInRegistration::STATUS_PASSED, \App\Models\WalkInRegistration::STATUS_REJECTED, \App\Models\WalkInRegistration::STATUS_NO_SHOW] as $status)
                      <option value="{{ $status }}" @selected($registration->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                  </select>
                  <textarea name="screening_note" rows="2" class="w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950/50" placeholder="Catatan screening">{{ $registration->screening_note }}</textarea>
                  <button class="btn-primary w-full justify-center" type="submit">Simpan</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">Belum ada peserta.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $registrations->links() }}
</div>
@endsection
