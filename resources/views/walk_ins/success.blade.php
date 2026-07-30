<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pendaftaran Berhasil | {{ $landingSetting->value('website_name') }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { colors: { brand: '#E4A853', dark: { 900: '#120F0D', 800: '#1E1915' } } } } }</script>
</head>
<body class="bg-dark-900 text-gray-100 antialiased">
  <main class="mx-auto grid min-h-screen max-w-3xl place-items-center px-4 py-12">
    <section class="w-full rounded-[2rem] border border-white/10 bg-white/[.04] p-8 shadow-2xl">
      <div class="text-sm font-bold uppercase tracking-widest text-brand">Pendaftaran Berhasil</div>
      <h1 class="mt-3 text-3xl font-extrabold">{{ $registration->event->title }}</h1>
      <div class="mt-6 grid gap-3 text-sm text-gray-300">
        <div>Kode Registrasi: <strong class="text-white">{{ $registration->registration_code }}</strong></div>
        <div>Nama: <strong class="text-white">{{ $registration->full_name }}</strong></div>
        <div>Posisi: <strong class="text-white">{{ $registration->position?->name ?? '-' }}</strong></div>
        <div>Tanggal/Jam: <strong class="text-white">{{ $registration->event->event_date?->format('d M Y') }} {{ $registration->event->start_time ? substr($registration->event->start_time, 0, 5) : '' }}</strong></div>
        <div>Lokasi: <strong class="text-white">{{ $registration->event->location ?: '-' }}</strong></div>
      </div>
      <p class="mt-6 rounded-2xl border border-brand/20 bg-brand/10 p-4 text-sm text-brand">Simpan kode registrasi ini dan hadir tepat waktu. Saat di lokasi, scan QR check-in yang ditampilkan HRD.</p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('walk-ins.show', $registration->event) }}" class="rounded-full border border-white/10 px-5 py-3 text-sm font-semibold hover:bg-white/10">Kembali ke Event</a>
        <a href="{{ route('landing') }}" class="rounded-full bg-brand px-5 py-3 text-sm font-bold text-dark-900 hover:bg-white">Landing Page</a>
      </div>
    </section>
  </main>
</body>
</html>
