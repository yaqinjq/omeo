@extends('layouts.app')

@section('content')
  <div class="max-w-md mx-auto space-y-5">
    <div class="rounded-3xl border border-amber-200/80 bg-amber-50/90 px-4 py-4 text-sm text-amber-900 shadow-sm">
      <div class="flex items-start gap-3">
        <button type="button" class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-300 bg-white/80 text-amber-700" aria-label="Info registrasi">
          i
        </button>
        <div>
          <div class="font-semibold">Panduan registrasi kandidat</div>
          <p class="mt-1 leading-6">Gunakan nama sesuai KTP, email aktif, dan password yang mudah Anda ingat. Setelah akun dibuat, Anda akan langsung diarahkan ke application form.</p>
        </div>
      </div>
    </div>

    <div class="card p-6 sm:p-7">
      <h1 class="text-2xl font-semibold">Register Kandidat</h1>
      <p class="mt-1 text-sm text-muted">Buat akun kandidat untuk mengisi application form secara mandiri.</p>

      @if ($errors->any())
        <div class="mt-4 alert-danger">
          <ul class="list-disc ml-5 space-y-1 text-sm">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('register') }}" class="mt-5 space-y-4">
        @csrf

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Nama lengkap sesuai KTP</label>
          <input class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Contoh: Budi Santoso">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">NIK</label>
          <input class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="text" name="nik" value="{{ old('nik') }}" required inputmode="numeric" placeholder="16 digit NIK">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Email aktif</label>
          <input class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="email" name="email" value="{{ old('email') }}" required placeholder="nama@email.com">
        </div>

        <div>
          <div class="flex items-center justify-between gap-3 mb-1">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
            <button type="button" class="text-xs font-semibold text-amber-700 underline underline-offset-2" data-toggle-password data-target="registerPassword">Lihat password</button>
          </div>
          <input id="registerPassword" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="password" name="password" required autocomplete="new-password" placeholder="Minimal 8 karakter">
        </div>

        <div>
          <div class="flex items-center justify-between gap-3 mb-1">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Ulangi Password</label>
            <button type="button" class="text-xs font-semibold text-amber-700 underline underline-offset-2" data-toggle-password data-target="registerPasswordConfirmation">Lihat password</button>
          </div>
          <input id="registerPasswordConfirmation" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ketik ulang password">
        </div>

        <button class="btn-primary w-full justify-center" type="submit">Buat Akun</button>

        <div class="text-sm text-muted text-center">
          Sudah punya akun?
          <a class="font-semibold text-amber-700 underline underline-offset-2" href="{{ route('login') }}">Login</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
      button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.target);
        if (!target) return;

        const nextType = target.type === 'password' ? 'text' : 'password';
        target.type = nextType;
        button.textContent = nextType === 'password' ? 'Lihat password' : 'Sembunyikan password';
      });
    });
  </script>
@endsection
