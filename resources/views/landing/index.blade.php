@extends('layouts.app')

@section('content')
<div class="space-y-8">
  <div class="rounded-3xl border border-white/10 bg-gradient-to-b from-white/[0.06] to-white/[0.03] p-8 shadow-[0_30px_120px_rgba(0,0,0,0.55)]">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/30 px-3 py-1 text-xs text-slate-200">
          <span class="h-2 w-2 rounded-full bg-amber-400 shadow-[0_0_18px_rgba(251,191,36,0.8)]"></span>
          HRD Console • Modern Suite
        </div>

        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white md:text-4xl">
          OMEŌ HR Suite
        </h1>
        <p class="mt-2 max-w-2xl text-slate-300">
          HRD yang rapi, cepat, dan terlihat profesional: probation tracking, appraisal bertingkat, recruitment, training & LMS.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
          <a href="{{ route('login') }}"
             class="inline-flex items-center justify-center rounded-xl bg-amber-400 px-5 py-3 font-medium text-black shadow-[0_12px_40px_rgba(251,191,36,0.25)] hover:brightness-110">
            Masuk HRD
          </a>
          @if (Route::has('register'))
          <a href="{{ route('register') }}"
             class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-3 font-medium text-white hover:bg-white/10">
            Buat Akun
          </a>
          @endif
        </div>

        <div class="mt-6 flex flex-wrap gap-3 text-sm text-slate-300">
          <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-2">✅ Probation & reminder</div>
          <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-2">✅ Appraisal bertingkat</div>
          <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-2">✅ LMS & Training</div>
        </div>
      </div>

      <div class="w-full max-w-lg">
        <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-black/25 p-6">
          <div class="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-amber-400/20 blur-3xl"></div>
          <div class="absolute -left-24 -bottom-24 h-64 w-64 rounded-full bg-sky-400/10 blur-3xl"></div>

          <div class="text-sm text-slate-300">Preview</div>
          <div class="mt-2 grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-xs text-slate-400">Total Karyawan</div>
              <div class="mt-2 text-2xl font-semibold text-white">1,248</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-xs text-slate-400">Probation H-14</div>
              <div class="mt-2 text-2xl font-semibold text-white">12</div>
            </div>
            <div class="col-span-2 rounded-2xl border border-white/10 bg-white/5 p-4">
              <div class="text-xs text-slate-400">Appraisal Pending</div>
              <div class="mt-2 h-2 w-full rounded-full bg-white/10">
                <div class="h-2 w-2/3 rounded-full bg-amber-400/80"></div>
              </div>
              <div class="mt-2 text-xs text-slate-400">Pipeline penilaian & approval</div>
            </div>
          </div>

          <div class="mt-5 grid grid-cols-3 gap-3 text-xs text-slate-300">
            <div class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-center">Recruitment</div>
            <div class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-center">Offering</div>
            <div class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-center">LMS Live</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-3">
    @foreach ([
      ['Probation & Reminder', 'Notifikasi in-app, daftar due, dan auto-create appraisal.'],
      ['Appraisal Dinamis', 'Form indikator, reviewer bertingkat, summary & print.'],
      ['LMS Modern', 'Pretest/posttest, KKM, progress & kelas live.'],
    ] as $f)
      <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
        <div class="text-lg font-semibold text-white">{{ $f[0] }}</div>
        <div class="mt-2 text-sm text-slate-300">{{ $f[1] }}</div>
      </div>
    @endforeach
  </div>
</div>
@endsection
