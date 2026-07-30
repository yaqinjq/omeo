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

  <title>{{ $globalSettings->app_name ?? $globalSettings->app_name ?? config('app.name', 'OMEO HR Suite') }}</title>

  @if(isset($globalSettings) && $globalSettings->meta_description)
    <meta name="description" content="{{ $globalSettings->meta_description }}">
  @endif

  @if(isset($globalSettings) && $globalSettings->app_favicon_path)
    <link rel="icon" href="{{ $globalSettings->favicon_url }}">
  @else
    <link rel="icon" href="{{ asset('favicon.ico') }}">
  @endif

  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
  @include('partials.vite-assets')
  <style>
    @media (min-width: 768px) {
      #appLayout {
        padding-left: 20rem;
      }

      #sidebar {
        width: 16rem;
        transform: translateX(0);
      }

      #mainContent {
        margin-left: 0;
        width: 100%;
        max-width: 100%;
      }

      #appLayout.sidebar-mini {
        padding-left: 9.25rem;
      }

      #appLayout.sidebar-mini #sidebar {
        width: 5.75rem;
      }

      #appLayout.sidebar-hidden {
        padding-left: 2rem;
      }

      #appLayout.sidebar-hidden #sidebar {
        display: none;
      }

      #appLayout.sidebar-mini #sidebar .menu-item {
        justify-content: center;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
      }

      #appLayout.sidebar-mini #sidebar .menu-label,
      #appLayout.sidebar-mini #sidebar .sidebar-section-label,
      #appLayout.sidebar-mini #sidebar .badge,
      #appLayout.sidebar-mini #sidebar .menu-chevron,
      #appLayout.sidebar-mini #sidebar .sidebar-account-label {
        display: none;
      }

      #appLayout.sidebar-mini #sidebar .menu-ico {
        margin-right: 0;
      }

      #appLayout.sidebar-mini #sidebar .sidebar-brand-copy {
        display: none;
      }
    }
  </style>
</head>

<body class="h-full overflow-x-hidden bg-base text-baseText antialiased">
  @php
    $activePageTitle = trim($__env->yieldContent('page_title'));
    if ($activePageTitle === '') {
      $routeName = request()->route()?->getName();
      $activePageTitle = $routeName
        ? ucwords(str_replace(['.', '-'], ' ', preg_replace('/\.index$/', '', $routeName)))
        : 'Dashboard';
    }

    $bellUnreadCount = 0;
    $bellNotifs      = collect();
    $bellTypeLabels  = [
        'daily_worker_contract'        => 'Kontrak',
        'appraisal_invitation'         => 'Appraisal',
        'appraisal_reminder'           => 'Reminder',
        'appraisal_probation_reminder' => 'Reminder',
        'profile_change_request'       => 'Profil',
        'probation_reminder'           => 'Probation',
        'probation_official_profile'   => 'Profil',
        'candidate_status_accepted'    => 'Kandidat',
        'candidate_status_shortlisted' => 'Kandidat',
    ];
    $bellTypeIcons = [
        'daily_worker_contract'        => '📄',
        'appraisal_invitation'         => '⭐',
        'appraisal_reminder'           => '⏰',
        'appraisal_probation_reminder' => '⏰',
        'profile_change_request'       => '👤',
        'probation_reminder'           => '📋',
        'probation_official_profile'   => '👤',
        'candidate_status_accepted'    => '🎉',
        'candidate_status_shortlisted' => '✅',
    ];
    if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('hr_notifications')) {
      $bellUserRole = auth()->user()->role ?? 'employee';
      $bellIsHrd    = in_array($bellUserRole, ['admin', 'finance'], true);
      $bellBase = \App\Models\HrNotification::query()
        ->where(function ($b) use ($bellIsHrd) {
          $b->where('user_id', auth()->id());
          if ($bellIsHrd) { $b->orWhereNull('user_id'); }
        })
        ->where('is_read', false);
      $bellUnreadCount = $bellBase->count();
      $bellNotifs      = (clone $bellBase)->latest('created_at')->limit(5)->get();
    }
  @endphp

  <div class="fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-white/45 via-transparent to-slate-200/35 dark:from-slate-950/90 dark:to-slate-950/75"></div>
    <div class="absolute -top-40 -left-40 h-80 w-80 rounded-full bg-brand/15 blur-3xl"></div>
    <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-brand/10 blur-3xl"></div>
  </div>

  <div class="min-h-screen overflow-x-clip">
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-topbar/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/80">
      <div class="mx-auto flex w-full max-w-[1600px] items-center justify-between gap-3 px-4 py-3 md:px-8">
        <div class="flex min-w-0 items-center gap-3">
          <button id="btnSidebar" class="md:hidden btn-ghost h-10 w-10 p-0" type="button" aria-label="Buka menu" aria-expanded="false">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
              <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
            </svg>
          </button>

          <button id="btnSidebarDesktop" class="hidden md:inline-flex btn-ghost h-10 w-10 p-0" type="button" aria-label="Toggle sidebar mode">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="16" rx="2"></rect>
              <path d="M9 4v16"></path>
            </svg>
          </button>

          <div class="flex min-w-0 items-center gap-3">
            @if(isset($globalSettings) && $globalSettings->app_logo_path)
              <img src="{{ $globalSettings->logo_url }}" class="h-10 w-auto shrink-0 object-contain" alt="App Logo">
            @else
              <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand/20 ring-1 ring-white/10 shadow-3d">
                <span class="font-bold text-brand">{{ substr($globalSettings->app_name ?? 'O', 0, 1) }}</span>
              </div>
            @endif

            <div class="min-w-0 leading-tight">
              <div class="truncate text-sm font-semibold tracking-wide sm:text-base">
                {{ $globalSettings->app_name ?? 'OMEO HR Suite' }}
              </div>
              <div class="hidden text-xs text-muted sm:block">{{ $activePageTitle }}</div>
              <div class="block truncate text-[11px] text-muted sm:hidden">{{ $activePageTitle }}</div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button id="btnInstallApp" class="btn-ghost hidden" type="button" aria-label="Install app">Install App</button>

          @auth
            @if(Route::has('profile.edit'))
              <a href="{{ route('profile.edit') }}" class="topbar-icon-btn" aria-label="Profil Akun">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                  <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"></path>
                  <path d="M4 20a8 8 0 0 1 16 0"></path>
                </svg>
              </a>
            @endif
            <div style="position:relative;" x-data="{ openBell: false }" @click.outside="openBell = false">
              <button @click="openBell = !openBell" type="button"
                      class="topbar-icon-btn"
                      style="position:relative;" aria-label="Notifikasi">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                  <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/>
                  <path d="M9 17a3 3 0 0 0 6 0"/>
                </svg>
                @if($bellUnreadCount > 0)
                  <span class="topbar-badge">{{ $bellUnreadCount > 99 ? '99+' : $bellUnreadCount }}</span>
                @endif
              </button>

              <div x-show="openBell" x-transition @click.stop
                   style="position:absolute; right:0; top:calc(100% + 8px); width:340px;
                          max-width:calc(100vw - 2rem); background:white; border-radius:12px;
                          border:1px solid #E2E8F0; box-shadow:0 8px 24px rgba(0,0,0,0.12);
                          z-index:9999; overflow:hidden;">

                {{-- Header --}}
                <div style="padding:12px 16px; border-bottom:1px solid #F1F5F9;
                            display:flex; justify-content:space-between; align-items:center;">
                  <span style="font-weight:700; font-size:14px; color:#1e293b;">
                    Notifikasi
                    @if($bellUnreadCount > 0)
                    <span style="background:#EF4444; color:white; padding:1px 8px;
                                 border-radius:99px; font-size:11px; margin-left:4px;">
                      {{ $bellUnreadCount }}
                    </span>
                    @endif
                  </span>
                  @if(Route::has('hr-notifications.index'))
                  <a href="{{ route('hr-notifications.index') }}"
                     style="font-size:12px; color:#1D4ED8; text-decoration:none; font-weight:600;">
                    Lihat Semua →
                  </a>
                  @endif
                </div>

                {{-- List preview --}}
                <div style="max-height:360px; overflow-y:auto;">
                  @forelse($bellNotifs as $bn)
                  @php($bnRoute = $bn->meta['route'] ?? (Route::has('hr-notifications.index') ? route('hr-notifications.index') : '#'))
                  <a href="{{ $bnRoute }}"
                     style="display:flex; gap:10px; padding:12px 16px;
                            border-bottom:1px solid #F8FAFC; text-decoration:none;
                            color:inherit; background:#F0F7FF; transition:background 0.1s;"
                     onmouseover="this.style.background='#E8F0FE'"
                     onmouseout="this.style.background='#F0F7FF'">
                    <span style="font-size:18px; flex-shrink:0; padding-top:2px;">
                      {{ $bellTypeIcons[$bn->type] ?? '🔔' }}
                    </span>
                    <div style="flex:1; min-width:0;">
                      <div style="font-size:11px; color:#64748B; margin-bottom:2px;">
                        {{ $bellTypeLabels[$bn->type] ?? $bn->type }}
                        · {{ $bn->created_at->diffForHumans() }}
                      </div>
                      <div style="font-size:13px; font-weight:600; color:#1e293b;
                                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ $bn->title }}
                      </div>
                    </div>
                    <span style="width:8px; height:8px; background:#1D4ED8; border-radius:50%;
                                 flex-shrink:0; margin-top:6px;"></span>
                  </a>
                  @empty
                  <div style="padding:24px; text-align:center; color:#94A3B8; font-size:13px;">
                    Semua notifikasi sudah dibaca 🎉
                  </div>
                  @endforelse
                </div>

                {{-- Footer --}}
                @if($bellUnreadCount > 5)
                <div style="padding:10px 16px; text-align:center; border-top:1px solid #F1F5F9;">
                  <a href="{{ route('hr-notifications.index', ['unread'=>1]) }}"
                     style="font-size:12px; color:#1D4ED8; text-decoration:none; font-weight:600;">
                    +{{ $bellUnreadCount - 5 }} notifikasi belum dibaca lainnya →
                  </a>
                </div>
                @endif

                {{-- Mark all read --}}
                @if($bellUnreadCount > 0 && Route::has('hr-notifications.readAll'))
                <div style="padding:10px 16px; border-top:1px solid #F1F5F9;">
                  <form method="POST" action="{{ route('hr-notifications.readAll') }}">
                    @csrf
                    <button type="submit"
                            style="width:100%; background:#F1F5F9; color:#475569; border:none;
                                   padding:7px; border-radius:8px; font-size:12px;
                                   font-weight:600; cursor:pointer;">
                      ✓ Tandai Semua Dibaca
                    </button>
                  </form>
                </div>
                @endif
              </div>
            </div>
          @endauth

          <button id="themeToggle" class="topbar-icon-btn" type="button" aria-label="Toggle theme">
            <svg class="hidden dark:block h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
              <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
            </svg>
            <svg class="dark:hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
              <circle cx="12" cy="12" r="4"></circle>
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
            </svg>
          </button>

          @auth
            <span class="hidden rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 lg:inline-flex">Role: {{ auth()->user()->role ?? '-' }}</span>
            <form class="hidden xl:block" method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="btn-danger" type="submit">Logout</button>
            </form>
          @else
            @if (Route::has('login'))
              <a class="btn" href="{{ route('login') }}">Login</a>
            @endif
            @if (Route::has('register'))
              <a class="btn-ghost" href="{{ route('register') }}">Register</a>
            @endif
          @endauth
        </div>
      </div>
    </header>

    <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-black/40 md:hidden"></div>

    @auth
      <div id="appLayout" class="relative mx-auto w-full max-w-[1600px] overflow-x-clip px-4 py-6 md:px-8">
        <aside id="sidebar" class="fixed bottom-4 left-4 top-[76px] z-50 w-[84vw] max-w-[320px] -translate-x-[120%] transition-all duration-300 md:bottom-6 md:left-8 md:top-[88px] md:max-w-none md:translate-x-0">
          <div class="card h-full overflow-hidden p-0">
            <div class="flex h-full flex-col overflow-hidden">
              <div class="border-b border-slate-200/80 px-4 py-4 dark:border-slate-800">
                <div class="mb-3 flex items-center justify-between md:hidden">
                  <div class="text-xs uppercase tracking-wider text-muted">Navigasi</div>
                  <button id="btnSidebarClose" class="btn-ghost h-9 w-9 p-0" type="button" aria-label="Tutup menu">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                      <path d="M6 6l12 12M18 6l-12 12" stroke-linecap="round"></path>
                    </svg>
                  </button>
                </div>
                <div class="flex items-center gap-3">
                  <div class="menu-ico !h-10 !w-10">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                      <path d="M3 12h18M12 3v18"></path>
                    </svg>
                  </div>
                  <div class="sidebar-brand-copy min-w-0">
                    <div class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">Workspace Karyawan</div>
                    <div class="text-xs text-muted">Navigasi utama tanpa scroll horizontal</div>
                  </div>
                </div>
              </div>
              <div class="flex-1 overflow-y-auto px-3 py-4">
                @include('partials.hr_suite_menu')
              </div>
            </div>
          </div>
        </aside>

        <main id="mainContent" class="min-w-0 w-full max-w-full space-y-4 overflow-x-clip transition-all duration-300">
          @if (session('success'))
            <div class="alert-success">{{ session('success') }}</div>
          @endif

          @if (session('error'))
            <div class="alert-danger">{{ session('error') }}</div>
          @endif

          @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200">{{ session('warning') }}</div>
          @endif

          @if ($errors->any())
            <div class="alert-danger">
              <div class="mb-2 font-semibold">Validasi gagal:</div>
              <ul class="ml-5 list-disc space-y-1">
                @foreach ($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          @yield('content')
        </main>
      </div>
    @else
      <main class="mx-auto max-w-3xl px-4 py-10">
        @yield('content')
      </main>
    @endauth
  </div>

  @stack('scripts')
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>



