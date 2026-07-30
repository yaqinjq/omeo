<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pendaftaran Berhasil — {{ $event->title }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Segoe UI', system-ui, sans-serif; }
    @keyframes pop { 0%{transform:scale(.6);opacity:0} 70%{transform:scale(1.1)} 100%{transform:scale(1);opacity:1} }
    .pop { animation: pop .4s ease-out forwards; }
    .ticket-border { border: 2px dashed #6366f1; }
  </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen flex items-center justify-center px-4">

  <div class="max-w-md w-full">
    {{-- SUCCESS ICON --}}
    <div class="text-center mb-6 pop">
      <div class="w-20 h-20 rounded-full mx-auto flex items-center justify-center mb-4"
           style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <svg class="w-10 h-10 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-gray-800">Pendaftaran Berhasil!</h1>
      <p class="text-gray-500 text-sm mt-1">Terima kasih, {{ $name }}.</p>
    </div>

    {{-- TICKET --}}
    <div class="bg-white rounded-3xl shadow-lg overflow-hidden">
      {{-- Event info --}}
      <div class="px-6 py-5" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <p class="text-white/70 text-xs uppercase tracking-wide font-semibold">Event Walk-In</p>
        <p class="text-white font-bold text-lg mt-1">{{ $event->title }}</p>
        <p class="text-white/80 text-sm mt-1">
          {{ $event->event_date?->format('l, d M Y') }}
          @if($event->location) &middot; {{ $event->location }}@endif
        </p>
      </div>

      {{-- Queue number --}}
      <div class="ticket-border mx-6 my-5 rounded-2xl px-6 py-5 text-center">
        <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold mb-2">Nomor Antrian Anda</p>
        <p class="text-5xl font-black tracking-widest" style="color:#6366f1">{{ $regNumber }}</p>
        <p class="text-xs text-gray-400 mt-3">Simpan nomor ini untuk check-in pada hari pelaksanaan</p>
      </div>

      {{-- Instructions --}}
      <div class="px-6 pb-6">
        <div class="space-y-2">
          @foreach([
            'Hadir tepat waktu sesuai jadwal event',
            'Tunjukkan nomor antrian ini ke panitia saat check-in',
            'Bawa berkas yang diperlukan (CV, KTP, ijazah)',
          ] as $tip)
          <div class="flex items-start gap-2 text-sm text-gray-600">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" style="color:#6366f1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 13l4 4L19 7"/>
            </svg>
            {{ $tip }}
          </div>
          @endforeach
        </div>

        {{-- Screenshot suggestion --}}
        <div class="mt-4 p-3 rounded-xl text-xs text-center" style="background:#f5f3ff;color:#6366f1">
          📱 Screenshot halaman ini sebagai bukti pendaftaran
        </div>
      </div>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">
      Pertanyaan? Hubungi tim HRD melalui kontak yang tertera di info event.
    </p>
  </div>

</body>
</html>
