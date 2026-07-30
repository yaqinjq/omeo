<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Walk-In — {{ $event->title }}</title>
  <meta name="description" content="Form pendaftaran walk-in interview {{ $event->title }} — {{ $event->event_date?->format('d M Y') }}">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Segoe UI', system-ui, sans-serif; }
    .brand-bg { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); }
    .card { background: #fff; border-radius: 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
    input, select, textarea { outline: none; transition: border-color .15s; }
    input:focus, select:focus, textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

  {{-- BRAND HEADER --}}
  <div class="brand-bg text-white py-8 px-4">
    <div class="max-w-lg mx-auto">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-bold text-lg">O</div>
        <span class="font-bold text-lg">OMEO HR Suite</span>
      </div>
      <h1 class="text-2xl font-bold leading-tight">{{ $event->title }}</h1>
      <div class="flex flex-wrap gap-3 mt-3 text-sm text-white/80">
        <span class="flex items-center gap-1">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>
          </svg>
          {{ $event->event_date?->format('d M Y') }}
        </span>
        @if($event->location)
        <span class="flex items-center gap-1">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
          {{ $event->location }}
        </span>
        @endif
      </div>
      @if($event->target_positions)
      <div class="flex flex-wrap gap-2 mt-3">
        @foreach($event->target_positions as $pos)
        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-white/20">{{ $pos }}</span>
        @endforeach
      </div>
      @endif
    </div>
  </div>

  {{-- FORM CARD --}}
  <div class="max-w-lg mx-auto px-4 py-8">
    <div class="card p-6">
      <h2 class="text-lg font-bold text-gray-800 mb-1">Form Pendaftaran</h2>
      <p class="text-sm text-gray-500 mb-6">Isi data dengan lengkap dan benar. Nomor antrian akan dikirim setelah submit.</p>

      @if($errors->any())
      <div class="mb-4 p-3 rounded-xl text-sm bg-red-50 text-red-700 border border-red-200">
        <ul class="list-disc list-inside space-y-1">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      <form method="POST" action="{{ route('walkin.register.store', $link->referral_code) }}" class="space-y-4">
        @csrf

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
          <input type="text" name="candidate_name" value="{{ old('candidate_name') }}" required
                 class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm"
                 placeholder="Nama sesuai KTP">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">No. HP / WhatsApp <span class="text-red-500">*</span></label>
          <input type="tel" name="candidate_phone" value="{{ old('candidate_phone') }}" required
                 class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm"
                 placeholder="08xxxxxxxxxx">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
          <input type="email" name="candidate_email" value="{{ old('candidate_email') }}"
                 class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm"
                 placeholder="email@contoh.com (opsional)">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Akun Instagram</label>
          <div class="flex">
            <span class="inline-flex items-center px-3 text-sm text-gray-500 border border-r-0 border-gray-200 rounded-l-xl bg-gray-50">@</span>
            <input type="text" name="ig_account" value="{{ old('ig_account') }}"
                   class="flex-1 rounded-r-xl border border-gray-200 px-4 py-2.5 text-sm"
                   placeholder="username_instagram (opsional)">
          </div>
        </div>

        @if($event->target_positions && count($event->target_positions))
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Posisi yang Dilamar</label>
          <select name="applied_position" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm bg-white">
            <option value="">-- Pilih posisi --</option>
            @foreach($event->target_positions as $pos)
            <option value="{{ $pos }}" @selected(old('applied_position') === $pos)>{{ $pos }}</option>
            @endforeach
          </select>
        </div>
        @else
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Posisi yang Dilamar</label>
          <input type="text" name="applied_position" value="{{ old('applied_position') }}"
                 class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm"
                 placeholder="Contoh: COOK HELPER (opsional)">
        </div>
        @endif

        <button type="submit"
                class="w-full py-3 rounded-xl text-sm font-bold text-white mt-2 hover:opacity-90 transition-opacity"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          Daftar Sekarang
        </button>

        <p class="text-xs text-center text-gray-400 mt-2">
          Dengan mendaftar, Anda menyetujui data digunakan untuk proses rekrutmen.
        </p>
      </form>
    </div>
  </div>

</body>
</html>
