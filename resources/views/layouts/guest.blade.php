<!doctype html>
<html lang="id" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f172a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'OMEO HR Suite') }}</title>

  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
  @include('partials.vite-assets')
</head>

<body class="h-full bg-base text-baseText antialiased">
  {{-- background texture --}}
  <div class="fixed inset-0 -z-10">
    <div class="absolute inset-0 bg-gradient-to-br from-white/45 via-transparent to-slate-200/35 dark:from-slate-950/90 dark:to-slate-950/75"></div>
    <div class="absolute -top-40 -left-40 h-80 w-80 rounded-full bg-brand/15 blur-3xl"></div>
    <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-brand/10 blur-3xl"></div>
  </div>

  {{-- topbar sederhana (tanpa sidebar) --}}
  <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-topbar/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/80">
    <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
        <div class="h-9 w-9 rounded-xl bg-brand/20 ring-1 ring-white/10 grid place-items-center shadow-3d shrink-0">
          <span class="font-bold text-brand">O</span>
        </div>
        <div class="leading-tight min-w-0">
          <div class="font-semibold tracking-wide text-sm sm:text-base truncate">OMEO HR Suite</div>
          <div class="text-xs text-muted">HRD Console</div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button id="btnInstallApp" class="btn-ghost hidden" type="button" aria-label="Install app">Install App</button>
        <button id="themeToggle" class="btn-ghost" type="button" aria-label="Toggle theme">
          <svg class="hidden dark:block h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
          </svg>
          <svg class="dark:hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:py-10">
    <div class="mx-auto w-full max-w-md card p-5 sm:p-6">
      {{ $slot }}
    </div>
  </main>
</body>
</html>


