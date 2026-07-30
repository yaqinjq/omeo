@extends('layouts.app')

@section('content')
  <div class="max-w-md mx-auto space-y-5">
    <div class="rounded-3xl border border-amber-200/80 bg-amber-50/90 px-4 py-4 text-sm text-amber-900 shadow-sm">
      <div class="flex items-start gap-3">
        <button type="button" class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-300 bg-white/80 text-amber-700" aria-label="Info login">
          i
        </button>
        <div>
          <div class="font-semibold">Panduan login</div>
          <p class="mt-1 leading-6">Masukkan email login dan password Anda. Gunakan tombol lihat password bila ingin memastikan karakter yang diketik sudah benar.</p>
        </div>
      </div>
    </div>

    <div class="card p-6 sm:p-7">
      <h1 class="text-2xl font-semibold">Login</h1>
      <p class="mt-1 text-sm text-muted">Masuk ke HR Suite untuk melanjutkan application form atau melihat progres rekrutmen.</p>

      @if (session('status'))
        <div class="mt-4 alert-success text-sm">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="mt-4 alert-danger text-sm">
          <ul class="list-disc ml-5 space-y-1">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form class="mt-6 space-y-4" method="POST" action="{{ route('login') }}">
        @csrf

        <div>
          <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Email</label>
          <input class="input mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@email.com">
        </div>

        <div>
          <div class="flex items-center justify-between gap-3">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
            <button type="button" class="text-xs font-semibold text-amber-700 underline underline-offset-2" data-toggle-password data-target="loginPassword">Lihat password</button>
          </div>
          <div class="relative mt-1">
            <input id="loginPassword" class="input w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 pr-12 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password Anda">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </span>
          </div>
          <p class="mt-1 text-xs text-muted">Gunakan tombol lihat password jika ingin mengecek ulang sebelum login.</p>
        </div>

        <div class="flex items-center justify-between gap-4">
          <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" class="rounded border-slate-300 bg-white">
            Tetap login
          </label>

          @if (Route::has('password.request'))
            <a class="text-sm font-semibold text-amber-700 underline underline-offset-2" href="{{ route('password.request') }}">Lupa password?</a>
          @endif
        </div>

        <button class="btn-primary w-full justify-center" type="submit">Login</button>

        @if (Route::has('register'))
          <div class="text-sm text-muted text-center">
            Belum punya akun? <a class="font-semibold text-amber-700 underline underline-offset-2" href="{{ route('register') }}">Register kandidat</a>
          </div>
        @endif
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
