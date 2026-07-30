<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Walk In Interview | {{ $landingSetting->value('website_name') }}</title>
  <meta name="description" content="Jadwal Walk In Interview aktif di {{ $landingSetting->value('website_name') }}.">
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
    <div class="glass mx-auto mt-4 flex h-16 max-w-7xl items-center justify-between rounded-3xl px-4 sm:px-6">
      <a href="{{ route('landing') }}" class="flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand font-bold text-dark-900">O</span>
        <span class="font-bold">{{ $landingSetting->value('website_name') }}</span>
      </a>
      <div class="flex items-center gap-3 text-sm">
        <a href="{{ route('careers.index') }}" class="hidden text-gray-300 hover:text-brand sm:inline">Karir</a>
        <a href="{{ route('login') }}" class="rounded-full border border-white/10 px-4 py-2 text-gray-200 hover:bg-white/10">Login</a>
      </div>
    </div>
  </nav>

  <main class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
    <section class="mb-10">
      <div class="inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand/10 px-4 py-2 text-sm font-semibold text-brand">
        <i data-lucide="calendar-days" class="h-4 w-4"></i> Walk In Interview
      </div>
      <h1 class="mt-5 text-4xl font-extrabold tracking-tight md:text-6xl">Jadwal Walk In</h1>
      <p class="mt-4 max-w-2xl text-lg text-gray-400">Pilih event aktif, ambil nomor antrian online, lalu hadir tepat waktu sesuai jadwal.</p>
    </section>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      @forelse($events as $event)
        <article class="glass-card rounded-[2rem] p-6 transition hover:-translate-y-1 hover:border-brand/50">
          @if($event->banner_url)
            <img src="{{ $event->banner_url }}" alt="{{ $event->title }}" class="mb-5 h-40 w-full rounded-3xl object-cover">
          @endif
          <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
            <span class="rounded-full bg-brand/10 px-3 py-1 font-semibold text-brand">{{ $event->event_date?->format('d M Y') }}</span>
            <span class="rounded-full bg-white/5 px-3 py-1">{{ $event->location ?: 'Lokasi menyusul' }}</span>
          </div>
          <h2 class="mt-4 text-2xl font-bold hover:text-brand"><a href="{{ route('walk-ins.show', $event) }}">{{ $event->title }}</a></h2>
          <p class="mt-3 text-sm leading-6 text-gray-400">{{ \Illuminate\Support\Str::limit(strip_tags($event->description), 150) }}</p>
          <div class="mt-4 flex flex-wrap gap-2">
            @foreach($event->activePositions->take(4) as $position)
              <span class="rounded-full bg-white/5 px-3 py-1 text-xs text-gray-300">{{ $position->name }}</span>
            @endforeach
          </div>
          <a href="{{ route('walk-ins.show', $event) }}" class="mt-5 inline-flex rounded-full bg-white/5 px-5 py-2.5 text-sm font-semibold hover:bg-brand hover:text-dark-900">Daftar Antrian</a>
        </article>
      @empty
        <div class="glass-card rounded-[2rem] p-10 text-center md:col-span-2 lg:col-span-3">
          <i data-lucide="inbox" class="mx-auto h-12 w-12 text-gray-500"></i>
          <p class="mt-4 text-gray-400">Belum ada jadwal Walk In Interview published.</p>
        </div>
      @endforelse
    </div>

    <div class="mt-8">{{ $events->links() }}</div>
  </main>

  <script>lucide.createIcons();</script>
</body>
</html>
