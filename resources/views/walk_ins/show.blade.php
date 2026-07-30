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
    tailwind.config = { theme: { extend: { colors: { brand: '#E4A853', dark: { 900: '#120F0D', 800: '#1E1915', 700: '#2A241E' } } } } }
  </script>
  <style>
    .glass{background:rgba(30,25,21,.72);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08)}
    .glass-card{background:linear-gradient(145deg,rgba(255,255,255,.06),rgba(255,255,255,.015));border:1px solid rgba(255,255,255,.1);box-shadow:0 8px 32px rgba(0,0,0,.3)}
  </style>
</head>
<body class="bg-dark-900 text-gray-100 antialiased">
  <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(228,168,83,.18),transparent_35%),linear-gradient(to_bottom,#120F0D,#1E1915)]"></div>

  <nav class="sticky top-0 z-40">
    <div class="glass mx-auto mt-4 flex h-16 max-w-5xl items-center justify-between rounded-3xl px-4 sm:px-6">
      <a href="{{ route('walk-ins.index') }}" class="flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand font-bold text-dark-900">O</span>
        <span class="font-bold">Walk In Interview</span>
      </a>
      <a href="{{ route('landing') }}" class="rounded-full border border-white/10 px-4 py-2 text-sm hover:bg-white/10">Landing</a>
    </div>
  </nav>

  <main class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
    <div class="glass-card rounded-[2rem] p-6 md:p-10">
      @if($event->banner_url)
        <img src="{{ $event->banner_url }}" alt="{{ $event->title }}" class="mb-8 h-64 w-full rounded-[2rem] object-cover">
      @endif

      <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
        <span class="rounded-full bg-brand/10 px-3 py-1 font-semibold text-brand">{{ $event->event_date?->format('d M Y') }}</span>
        <span class="rounded-full bg-white/5 px-3 py-1">{{ $event->start_time ? substr($event->start_time, 0, 5) : '-' }} - {{ $event->end_time ? substr($event->end_time, 0, 5) : '-' }}</span>
        <span class="rounded-full bg-white/5 px-3 py-1">{{ $event->location ?: 'Lokasi menyusul' }}</span>
      </div>
      <h1 class="mt-5 text-4xl font-extrabold tracking-tight md:text-6xl">{{ $event->title }}</h1>
      <p class="mt-5 whitespace-pre-line text-gray-300">{{ $event->description ?: 'Detail event akan diinformasikan oleh Tim HR.' }}</p>

      <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="space-y-6">
          <div>
            <h2 class="text-xl font-bold text-brand">Posisi Dibuka</h2>
            <div class="mt-3 flex flex-wrap gap-2">
              @foreach($event->activePositions as $position)
                <span class="rounded-full bg-white/5 px-4 py-2 text-sm">{{ $position->name }}</span>
              @endforeach
            </div>
          </div>

          <div>
            <h2 class="text-xl font-bold text-brand">Lokasi</h2>
            <p class="mt-3 whitespace-pre-line text-gray-300">{{ $event->address ?: $event->location ?: 'Lokasi akan diinformasikan oleh Tim HR.' }}</p>
            @if($event->maps_url)
              <a href="{{ $event->maps_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex text-brand hover:text-white">Buka Google Maps</a>
            @endif
          </div>

          @if($event->participant_instruction)
            <div>
              <h2 class="text-xl font-bold text-brand">Instruksi Peserta</h2>
              <p class="mt-3 whitespace-pre-line text-gray-300">{{ $event->participant_instruction }}</p>
            </div>
          @endif
        </section>

        <aside class="h-fit rounded-3xl border border-white/10 bg-white/5 p-5">
          <h2 class="text-xl font-bold">Daftar Antrian</h2>
          @if(session('success'))
            <div class="mt-4 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
          @endif
          @if($event->isRegistrationOpen())
            <form method="POST" action="{{ route('walk-ins.register', $event) }}" class="mt-5 space-y-4">
              @csrf
              <input type="hidden" name="referral_code" value="{{ old('referral_code', $referralCode) }}">
              <label class="block text-sm font-semibold">Nama Lengkap
                <input name="full_name" value="{{ old('full_name') }}" class="mt-1 w-full rounded-2xl border-white/10 bg-dark-800 px-4 py-3" required>
              </label>
              <label class="block text-sm font-semibold">Nomor WhatsApp
                <input name="whatsapp_number" value="{{ old('whatsapp_number') }}" class="mt-1 w-full rounded-2xl border-white/10 bg-dark-800 px-4 py-3" required>
                @error('whatsapp_number')<span class="mt-1 block text-xs text-red-300">{{ $message }}</span>@enderror
              </label>
              <label class="block text-sm font-semibold">Email
                <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-2xl border-white/10 bg-dark-800 px-4 py-3">
              </label>
              <label class="block text-sm font-semibold">Posisi Dilamar
                <select name="walk_in_event_position_id" class="mt-1 w-full rounded-2xl border-white/10 bg-dark-800 px-4 py-3" required>
                  <option value="">Pilih posisi</option>
                  @foreach($event->activePositions as $position)
                    <option value="{{ $position->id }}" @selected((string) old('walk_in_event_position_id') === (string) $position->id)>{{ $position->name }}</option>
                  @endforeach
                </select>
                @error('walk_in_event_position_id')<span class="mt-1 block text-xs text-red-300">{{ $message }}</span>@enderror
              </label>
              <label class="block text-sm font-semibold">Domisili
                <input name="domicile" value="{{ old('domicile') }}" class="mt-1 w-full rounded-2xl border-white/10 bg-dark-800 px-4 py-3">
              </label>
              <label class="flex gap-3 text-sm text-gray-300">
                <input type="checkbox" name="whatsapp_consent" value="1" class="mt-1 rounded border-white/20 bg-dark-800" required>
                Saya bersedia menerima informasi melalui WhatsApp.
              </label>
              <label class="flex gap-3 text-sm text-gray-300">
                <input type="checkbox" name="attendance_commitment" value="1" class="mt-1 rounded border-white/20 bg-dark-800" required>
                Saya siap menghadiri walk-in tepat waktu sesuai jadwal.
              </label>
              @if($errors->any())
                <div class="rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">Periksa kembali data pendaftaran.</div>
              @endif
              <button class="w-full rounded-full bg-brand px-5 py-3 font-bold text-dark-900 hover:bg-white" type="submit">Daftar Antrian</button>
            </form>
          @else
            <div class="mt-5 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">Pendaftaran event ini sudah ditutup.</div>
          @endif
        </aside>
      </div>
    </div>
  </main>

  <script>lucide.createIcons();</script>
</body>
</html>
