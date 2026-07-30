<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check-in Walk In | {{ $event->title }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { theme: { extend: { colors: { brand: '#E4A853', dark: { 900: '#120F0D', 800: '#1E1915' } } } } }</script>
</head>
<body class="bg-dark-900 text-gray-100 antialiased">
  <main class="mx-auto grid min-h-screen max-w-xl place-items-center px-4 py-12">
    <section class="w-full rounded-[2rem] border border-white/10 bg-white/[.04] p-8 shadow-2xl">
      <div class="text-sm font-bold uppercase tracking-widest text-brand">Check-in Lokasi</div>
      <h1 class="mt-3 text-3xl font-extrabold">{{ $event->title }}</h1>
      <p class="mt-3 text-sm text-gray-400">Masukkan kode registrasi atau nomor WhatsApp yang dipakai saat daftar.</p>

      @if(session('success'))
        <div class="mt-5 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
      @endif
      @if(session('warning'))
        <div class="mt-5 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">{{ session('warning') }}</div>
      @endif

      <form method="POST" action="{{ route('walk-ins.checkin.submit', $token) }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-semibold">Kode Registrasi / WhatsApp
          <input name="identifier" value="{{ old('identifier') }}" class="mt-1 w-full rounded-2xl border-white/10 bg-dark-800 px-4 py-3" required autofocus>
          @error('identifier')<span class="mt-1 block text-xs text-red-300">{{ $message }}</span>@enderror
        </label>
        <button class="w-full rounded-full bg-brand px-5 py-3 font-bold text-dark-900 hover:bg-white" type="submit">Check-in</button>
      </form>
    </section>
  </main>
</body>
</html>
