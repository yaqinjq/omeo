<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $event->title }} | Walk In Interview</title>
  <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($event->description ?: $event->title), 155) }}">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#E4A853',
            dark: { 900: '#120F0D', 800: '#1E1915', 700: '#2A241E' }
          }
        }
      }
    }
  </script>
  <style>
    .glass{background:rgba(30,25,21,.72);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08)}
    .glass-card{background:linear-gradient(145deg,rgba(255,255,255,.06),rgba(255,255,255,.015));border:1px solid rgba(255,255,255,.1);box-shadow:0 8px 32px rgba(0,0,0,.3)}
  </style>
</head>
<body class="bg-dark-900 text-gray-100 antialiased">
  <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(228,168,83,.18),transparent_35%),linear-gradient(to_bottom,#120F0D,#1E1915)]"></div>

  {{-- Navbar --}}
  <nav class="sticky top-0 z-40">
    <div class="glass mx-auto mt-4 flex h-16 max-w-5xl items-center justify-between rounded-3xl px-4 sm:px-6">
      <a href="{{ route('landing') }}" class="flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand font-bold text-dark-900">O</span>
        <span class="font-bold">Walk In Interview</span>
      </a>
      <a href="{{ route('landing') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm hover:bg-white/10">
        Beranda
      </a>
    </div>
  </nav>

  <main class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
    <div class="glass-card rounded-[2rem] p-6 md:p-10">

      {{-- Header badges --}}
      @php
        $start = $event->event_start_datetime
            ? $event->event_start_datetime->setTimezone('Asia/Jakarta')
            : null;
        $end = $event->event_end_datetime
            ? $event->event_end_datetime->setTimezone('Asia/Jakarta')
            : null;
      @endphp

      <div class="flex flex-wrap items-center gap-2 text-xs">
        @if($start)
          <span class="rounded-full bg-brand/10 px-3 py-1 font-semibold text-brand">
            {{ $start->translatedFormat('l, d F Y') }}
          </span>
          <span class="rounded-full bg-white/5 px-3 py-1">
            {{ $start->format('H:i') }} WIB@if($end) — {{ $end->format('H:i') }} WIB@endif
          </span>
        @endif
        @if($event->location)
          <span class="rounded-full bg-white/5 px-3 py-1">📍 {{ $event->location }}</span>
        @endif
        <span style="background-color:{{ $event->status_color }};color:#fff;font-size:11px;padding:2px 10px;border-radius:999px;font-weight:700;">
          {{ $event->status_label }}
        </span>
      </div>

      <h1 class="mt-5 text-4xl font-extrabold tracking-tight md:text-5xl">{{ $event->title }}</h1>

      @if($event->description)
        <p class="mt-5 whitespace-pre-line text-gray-300">{{ $event->description }}</p>
      @endif

      <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_340px]">

        {{-- Kiri: detail event --}}
        <div class="space-y-8">

          {{-- Posisi yang dibuka --}}
          @if(!empty($event->target_positions))
            <div>
              <h2 class="text-xl font-bold text-brand">Posisi yang Dicari</h2>
              <div class="mt-3 flex flex-wrap gap-2">
                @foreach($event->target_positions as $pos)
                  <span class="rounded-full bg-white/5 px-4 py-2 text-sm">{{ $pos }}</span>
                @endforeach
              </div>
            </div>
          @endif

          {{-- Lokasi --}}
          @if($event->location)
            <div>
              <h2 class="text-xl font-bold text-brand">Lokasi</h2>
              <p class="mt-3 text-gray-300">{{ $event->location }}</p>
            </div>
          @endif

          {{-- Jadwal --}}
          @if($start)
            <div>
              <h2 class="text-xl font-bold text-brand">Jadwal</h2>
              <p class="mt-3 text-gray-300">
                {{ $start->translatedFormat('l, d F Y') }}<br>
                Pukul {{ $start->format('H:i') }} WIB@if($end) s.d. {{ $end->format('H:i') }} WIB@endif
              </p>
            </div>
          @endif

        </div>

        {{-- Kanan: cara daftar --}}
        <aside class="h-fit rounded-3xl border border-white/10 bg-white/5 p-6 space-y-4">
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand/10 text-brand">
              <i data-lucide="info" class="h-5 w-5"></i>
            </div>
            <h2 class="text-lg font-bold">Cara Mendaftar</h2>
          </div>

          <p class="text-sm text-gray-300 leading-relaxed">
            Pendaftaran Walk In Interview dilakukan melalui link referral yang dibagikan oleh
            <strong class="text-white">Tim HR</strong> atau staf yang mengundang Anda.
          </p>

          <ul class="space-y-3 text-sm text-gray-300">
            <li class="flex gap-3">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand/20 text-brand text-xs font-bold">1</span>
              Minta link pendaftaran dari Tim HR atau staf Omeo yang mengundang Anda
            </li>
            <li class="flex gap-3">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand/20 text-brand text-xs font-bold">2</span>
              Buka link tersebut dan isi formulir pendaftaran
            </li>
            <li class="flex gap-3">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand/20 text-brand text-xs font-bold">3</span>
              Simpan nomor antrian Anda dan hadir tepat waktu di lokasi event
            </li>
          </ul>

          @if($event->isRegistrationOpen())
            <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
              ✅ Pendaftaran event ini <strong>masih terbuka</strong>
            </div>
          @else
            <div class="rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
              ⏳ Pendaftaran event ini sudah ditutup
            </div>
          @endif

          <a href="{{ route('landing') }}" class="block w-full rounded-full border border-white/10 px-5 py-3 text-center text-sm font-semibold hover:bg-white/10 transition-colors">
            ← Kembali ke Beranda
          </a>
        </aside>

      </div>
    </div>
  </main>

  <script>lucide.createIcons();</script>
</body>
</html>
