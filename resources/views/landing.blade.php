@php
    $setting = $landingSetting ?? \App\Models\LandingPageSetting::current();
    $websiteName = $setting->value('website_name');
    $safeUrl = function (?string $url, string $fallback = '#') {
        $url = trim((string) $url);
        if ($url === '') {
            return $fallback;
        }
        if (str_starts_with($url, '#') || str_starts_with($url, '/') || preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    };
    $fallbackTeam = [
        ['name' => 'Recruitment Team', 'position' => 'Talent Acquisition', 'company_email' => $setting->value('office_email'), 'photo_url' => null],
        ['name' => 'HR Operations', 'position' => 'People Operations', 'company_email' => $setting->value('office_email'), 'photo_url' => null],
        ['name' => 'Training Team', 'position' => 'Learning & Development', 'company_email' => $setting->value('office_email'), 'photo_url' => null],
    ];
    $teamMembers = ($hrTeamMembers ?? collect())->isNotEmpty() ? $hrTeamMembers : collect($fallbackTeam);
    $featuredCareers = $featuredCareers ?? collect();
    $featuredWalkIns = $featuredWalkIns ?? collect();
    $careerDepartments = $careerDepartments ?? collect();
    $areaLocations = $areaLocations ?? [];
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Aplikasi HRIS & Management Karyawan FnB Terbaik | {{ $websiteName }}</title>
    <meta name="description" content="{{ $setting->value('hero_subheadline') }}">
    <meta name="keywords" content="HRIS, Aplikasi HRIS, Management Karyawan, Recruitment, Appraisal, Walk In Interview, Career Portal">
    <meta name="author" content="{{ $websiteName }}">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="{{ $websiteName }}">
    <meta property="og:description" content="{{ $setting->value('hero_subheadline') }}">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        brand: { DEFAULT: '#E4A853', dark: '#C88A3A', light: '#F2C98A' },
                        dark: { 900: '#120F0D', 800: '#1E1915', 700: '#2A241E' }
                    }
                }
            }
        }
    </script>
    <style>
        .glass{background:rgba(30,25,21,.64);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08)}
        .glass-card{background:linear-gradient(145deg,rgba(255,255,255,.06),rgba(255,255,255,.015));backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.1);box-shadow:0 8px 32px rgba(0,0,0,.3)}
        .text-gradient{background:linear-gradient(to right,#E4A853,#F2C98A);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        .animate-float{animation:float 6s ease-in-out infinite}
        .no-scrollbar::-webkit-scrollbar{display:none}.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
        .map-pin:hover .map-tooltip{opacity:1;transform:translate(-50%,-115%) scale(1)}
    </style>
</head>
<body class="bg-dark-900 text-gray-100 font-sans antialiased selection:bg-brand selection:text-dark-900 overflow-x-hidden">
    <div class="fixed inset-0 z-[-1] bg-cover bg-center opacity-20 mix-blend-luminosity" style="background-image:url('{{ $setting->hero_background_url ?: 'https://images.unsplash.com/photo-1559925393-8be0ec4767c8?q=80&w=2070&auto=format&fit=crop' }}')"></div>
    <div class="fixed inset-0 z-[-1] bg-gradient-to-b from-dark-900/80 via-dark-900/95 to-dark-900"></div>

    <nav class="fixed w-full z-50 transition-all duration-300" id="navbar">
        <div class="glass mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4 rounded-3xl">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <a href="{{ route('landing') }}" class="flex-shrink-0 flex items-center gap-2">
                    @if($setting->logo_url)
                        <img src="{{ $setting->logo_url }}" alt="{{ $websiteName }}" class="h-9 w-auto max-w-[150px] object-contain">
                    @else
                        <div class="w-8 h-8 rounded-full bg-brand flex items-center justify-center text-dark-900 font-bold">O</div>
                    @endif
                    <span class="font-bold text-xl tracking-tight hidden sm:block">{{ $websiteName }}</span>
                </a>

                <div class="hidden lg:flex flex-1 justify-center items-center space-x-6">
                    <a href="#fitur" class="text-sm font-medium text-gray-300 hover:text-brand transition-colors">Fitur</a>
                    <a href="#tim" class="text-sm font-medium text-gray-300 hover:text-brand transition-colors">Tim HR</a>
                    <a href="{{ route('careers.index') }}" class="text-sm font-medium text-gray-300 hover:text-brand transition-colors">Karir</a>
                    <a href="{{ route('walk-ins.index') }}" class="text-sm font-medium text-gray-300 hover:text-brand transition-colors">Walk In</a>
                    <a href="#seo-area" class="text-sm font-medium text-gray-300 hover:text-brand transition-colors">Area</a>
                </div>

                <div class="hidden md:flex items-center space-x-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 rounded-full bg-brand hover:bg-brand-dark text-dark-900 text-sm font-bold transition-all shadow-[0_0_15px_rgba(228,168,83,0.4)]">Masuk Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/20 text-sm font-medium transition-all border border-white/5">Login</a>
                        <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-full bg-brand hover:bg-brand-dark text-dark-900 text-sm font-bold transition-all shadow-[0_0_15px_rgba(228,168,83,0.4)]">Daftar Sekarang</a>
                    @endauth
                </div>

                <button id="mobileMenuButton" class="lg:hidden text-gray-300 hover:text-white focus:outline-none p-2" type="button" aria-expanded="false" aria-controls="mobileMenu">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
            <div id="mobileMenu" class="hidden border-t border-white/10 py-4 lg:hidden">
                <div class="grid gap-3 text-sm">
                    <a href="#fitur" class="text-gray-300 hover:text-brand">Fitur</a>
                    <a href="#tim" class="text-gray-300 hover:text-brand">Tim HR</a>
                    <a href="{{ route('careers.index') }}" class="text-gray-300 hover:text-brand">Karir</a>
                    <a href="{{ route('walk-ins.index') }}" class="text-gray-300 hover:text-brand">Walk In</a>
                    <a href="#seo-area" class="text-gray-300 hover:text-brand">Area</a>
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-full bg-brand px-4 py-2 text-center font-bold text-dark-900">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full border border-white/10 px-4 py-2 text-center">Login</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-brand px-4 py-2 text-center font-bold text-dark-900">Daftar Sekarang</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-brand/20 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-0 right-1/4 w-[500px] h-[500px] bg-emerald-900/20 rounded-full blur-[150px] pointer-events-none"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8 items-center">
                <div class="col-span-12 lg:col-span-6 lg:pr-8">
                    <div class="inline-flex flex-wrap items-center gap-3 rounded-full border border-brand/30 bg-brand/10 px-4 py-2 text-xs md:text-sm text-brand font-medium backdrop-blur-md mb-6 w-fit">
                        <span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-brand shadow-[0_0_10px_#E4A853]"></span></span>
                        <span>{{ $setting->value('hero_badge') }}</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold leading-[1.1] tracking-tight mb-6">
                        {{ $setting->value('hero_headline') }}<br>
                        <span class="text-gradient">{{ $setting->value('hero_highlight') }}</span>
                    </h1>
                    <p class="text-gray-400 text-base sm:text-lg md:text-xl leading-relaxed mb-10 max-w-xl">{{ $setting->value('hero_subheadline') }}</p>
                    <div class="flex flex-col sm:flex-row flex-wrap items-center gap-4">
                        <a href="{{ $safeUrl($setting->value('primary_button_url'), '/dashboard') }}" class="w-full sm:w-auto px-8 py-4 rounded-full bg-brand hover:bg-brand-dark text-dark-900 font-bold text-lg transition-all flex items-center justify-center gap-2 shadow-[0_10px_30px_rgba(228,168,83,0.3)] group text-center">
                            {{ $setting->value('primary_button_label') }}
                            <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        <a href="{{ $safeUrl($setting->value('secondary_button_url'), route('careers.index')) }}" class="w-full sm:w-auto px-8 py-4 rounded-full glass hover:bg-white/10 text-white font-medium text-lg transition-all flex items-center justify-center border border-white/10 text-center">
                            {{ $setting->value('secondary_button_label') }}
                        </a>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-6 mt-12 lg:mt-0 relative">
                    <div class="glass-card rounded-[2rem] p-5 sm:p-6 md:p-8 relative z-10 border-t border-l border-white/20 animate-float overflow-hidden">
                        @if($setting->hero_image_url)
                            <img src="{{ $setting->hero_image_url }}" alt="Preview {{ $websiteName }}" class="mb-6 h-48 w-full rounded-3xl object-cover border border-white/10">
                        @endif
                        <div class="flex items-center justify-between mb-6 pb-6 border-b border-white/10 gap-2">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand to-brand-dark flex items-center justify-center shadow-lg">
                                    <i data-lucide="coffee" class="text-dark-900 w-6 h-6"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm text-brand font-medium truncate">{{ $setting->value('short_tagline') }}</div>
                                    <div class="text-xl font-semibold truncate">HR Dashboard UI</div>
                                </div>
                            </div>
                            <div class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-medium flex items-center gap-1.5">
                                <div class="w-2 h-2 rounded-full bg-green-500"></div> Live
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 md:gap-6 mb-6">
                            <div class="bg-dark-800/50 rounded-2xl p-5 border border-white/5 hover:border-brand/50 transition-colors">
                                <div class="text-sm text-gray-400 mb-2 flex items-center justify-between"><span>Total Karyawan</span><i data-lucide="users" class="w-4 h-4 text-gray-500"></i></div>
                                <div class="text-3xl sm:text-4xl font-bold text-white">458</div>
                                <div class="text-xs text-green-400 mt-2">Tersebar di 16 Kota</div>
                            </div>
                            <div class="bg-dark-800/50 rounded-2xl p-5 border border-white/5 hover:border-brand/50 transition-colors">
                                <div class="text-sm text-gray-400 mb-2 flex items-center justify-between"><span>Lowongan Aktif</span><i data-lucide="briefcase" class="w-4 h-4 text-gray-500"></i></div>
                                <div class="text-3xl sm:text-4xl font-bold text-white">{{ $featuredCareers->count() }}</div>
                                <div class="text-xs text-brand mt-2">Career Portal Live</div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-dark-800/80 to-dark-800/40 rounded-2xl p-5 border border-white/5">
                            <div class="flex justify-between items-center mb-4">
                                <div class="text-sm font-medium text-gray-300">Pipeline HR Terpadu</div>
                                <a href="#fitur" class="text-xs text-brand hover:underline">Lihat Modul</a>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-xs text-gray-300">
                                <div class="rounded-xl bg-white/5 px-3 py-3">Recruitment</div>
                                <div class="rounded-xl bg-white/5 px-3 py-3">Walk In</div>
                                <div class="rounded-xl bg-white/5 px-3 py-3">Appraisal</div>
                                <div class="rounded-xl bg-white/5 px-3 py-3">LMS</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <section id="walkin" class="py-20 relative bg-dark-800 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-6">
                <div class="max-w-2xl">
                    <h2 class="text-brand text-sm font-bold tracking-widest uppercase mb-2">Jadwal Terbuka</h2>
                    <h3 class="text-3xl md:text-4xl font-bold">Walk In Interview</h3>
                    <p class="mt-4 text-gray-400">Bergabunglah di event rekrutmen walk-in kami. Dapatkan link pendaftaran dari Tim HR atau scan QR code di lokasi event.</p>
                </div>
                <a href="{{ route('walk-ins.index') }}" class="inline-flex items-center gap-2 text-brand hover:text-white transition-colors font-medium">
                    Lihat Semua Event <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </a>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                @forelse($featuredWalkIns as $event)
                    @php
                        $start = $event->event_start_datetime
                            ? $event->event_start_datetime->setTimezone('Asia/Jakarta')
                            : null;
                        $end = $event->event_end_datetime
                            ? $event->event_end_datetime->setTimezone('Asia/Jakarta')
                            : null;
                    @endphp
                    <div class="glass-card rounded-[2rem] p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div class="w-12 h-12 rounded-xl bg-brand/10 text-brand flex items-center justify-center">
                                <i data-lucide="calendar-days" class="w-6 h-6"></i>
                            </div>
                            <span style="background-color:{{ $event->status_color }};color:#fff;font-size:11px;padding:2px 10px;border-radius:999px;font-weight:700;letter-spacing:.02em;">
                                {{ $event->status_label }}
                            </span>
                        </div>
                        <h4 class="text-xl font-bold mb-2">{{ $event->title }}</h4>
                        @if($start)
                            <p class="text-sm text-gray-400 mb-1">
                                {{ $start->translatedFormat('l, d F Y') }}
                            </p>
                            <p class="text-sm text-gray-400 mb-2">
                                {{ $start->format('H:i') }} WIB@if($end) — {{ $end->format('H:i') }} WIB@endif
                            </p>
                        @endif
                        <p class="text-sm text-gray-400 mb-5">📍 {{ $event->location ?: 'Lokasi akan diinformasikan' }}</p>
                        <a href="{{ route('walkin.public.show', $event->id) }}" class="inline-flex rounded-full bg-white/5 px-5 py-2.5 text-sm font-semibold hover:bg-brand hover:text-dark-900">Lihat Detail</a>
                    </div>
                @empty
                    @foreach(['Surabaya Walk In', 'Jakarta Area Hiring', 'National Outlet Crew'] as $eventName)
                        <div class="glass-card rounded-[2rem] p-6">
                            <div class="w-12 h-12 rounded-xl bg-brand/10 text-brand flex items-center justify-center mb-5"><i data-lucide="calendar-days" class="w-6 h-6"></i></div>
                            <h4 class="text-xl font-bold mb-2">{{ $eventName }}</h4>
                            <p class="text-sm text-gray-400 mb-5">Jadwal dan nomor antrian online akan tersedia setelah Tim HR publish event Walk In.</p>
                            <a href="{{ route('landing') }}#walkin" class="inline-flex rounded-full bg-white/5 px-5 py-2.5 text-sm font-semibold hover:bg-brand hover:text-dark-900">Cek Jadwal</a>
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    <section id="karir" class="py-20 relative bg-dark-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-brand text-sm font-bold tracking-widest uppercase mb-2">Career Portal</h2>
                    <h3 class="text-3xl md:text-4xl font-bold">Peluang Karir Departemen</h3>
                </div>
                <a href="{{ route('careers.index') }}" class="inline-flex items-center gap-2 text-brand hover:text-white transition-colors font-medium">Buka Halaman Karir <i data-lucide="arrow-right" class="w-5 h-5"></i></a>
            </div>

            @if($careerDepartments->isNotEmpty())
                <div class="flex overflow-x-auto no-scrollbar gap-2 pb-4 mb-8 border-b border-white/10">
                    @foreach($careerDepartments as $department)
                        <a href="{{ route('careers.index', ['department' => $department->slug]) }}" class="whitespace-nowrap px-6 py-3 rounded-full text-sm font-medium border border-white/10 bg-white/5 text-gray-300 hover:bg-brand hover:text-dark-900 hover:border-brand transition-all">{{ $department->name }}</a>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($featuredCareers as $career)
                    <article class="glass-card p-6 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:border-brand/50 transition-colors group">
                        <div>
                            <h4 class="text-xl font-bold mb-1 group-hover:text-brand transition-colors">{{ $career->title }}</h4>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-400">
                                <span class="flex items-center gap-1"><i data-lucide="building-2" class="w-3 h-3"></i>{{ $career->department?->name ?? 'General' }}</span>
                                <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3"></i>{{ $career->location ?: 'Banyak Lokasi' }}</span>
                                <span class="flex items-center gap-1"><i data-lucide="briefcase" class="w-3 h-3"></i>{{ $career->employment_type }}</span>
                            </div>
                        </div>
                        <a href="{{ route('careers.show', $career) }}" class="px-5 py-2.5 rounded-full bg-white/5 hover:bg-brand hover:text-dark-900 border border-white/10 text-sm font-semibold transition-all whitespace-nowrap">Lamar Posisi</a>
                    </article>
                @empty
                    <div class="md:col-span-2 text-center py-12 glass rounded-[2rem] border border-white/10">
                        <i data-lucide="inbox" class="w-12 h-12 text-gray-500 mx-auto mb-4"></i>
                        <p class="text-gray-400">Belum ada lowongan published. Tim HR bisa menambahkan lowongan dari dashboard Career Portal.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="tim" class="py-20 relative bg-dark-800 border-t border-b border-white/5 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-brand text-sm font-bold tracking-widest uppercase mb-2">People Behind The System</h2>
                <h3 class="text-3xl md:text-4xl font-bold">Tim Human Resource</h3>
                <p class="mt-4 text-gray-400">Hubungi Tim HR melalui email company resmi untuk informasi rekrutmen dan career portal.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($teamMembers as $member)
                    <div class="glass-card rounded-[2rem] p-6 text-center">
                        @if(data_get($member, 'photo_url'))
                            <img src="{{ data_get($member, 'photo_url') }}" alt="{{ data_get($member, 'name') }}" class="mx-auto mb-5 h-24 w-24 rounded-full object-cover border border-brand/30">
                        @else
                            <div class="mx-auto mb-5 grid h-24 w-24 place-items-center rounded-full bg-brand/10 text-3xl font-bold text-brand border border-brand/30">{{ substr((string) data_get($member, 'name'), 0, 1) }}</div>
                        @endif
                        <h4 class="text-xl font-bold">{{ data_get($member, 'name') }}</h4>
                        <p class="mt-1 text-sm text-brand">{{ data_get($member, 'position') }}</p>
                        @if(data_get($member, 'company_email'))
                            <a href="mailto:{{ data_get($member, 'company_email') }}" class="mt-4 inline-flex items-center justify-center gap-2 rounded-full bg-white/5 px-4 py-2 text-sm text-gray-300 hover:bg-brand hover:text-dark-900">
                                <i data-lucide="mail" class="w-4 h-4"></i>{{ data_get($member, 'company_email') }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="fitur" class="py-20 relative bg-dark-900 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-brand text-sm font-bold tracking-widest uppercase mb-2">Modul Aplikasi</h2>
                <h3 class="text-3xl md:text-5xl font-bold">Satu Platform untuk Alur HRD</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach([
                    ['Recruitment', 'user-plus', 'Kelola kandidat, application form, test, dan governance recruitment.'],
                    ['Walk In Interview', 'clipboard-list', 'Antrian prioritas dan screening event akan tersedia pada tahap modul berikutnya.'],
                    ['Contract & Offering', 'file-signature', 'Dokumen offering, kontrak daily worker, stamp, dan review digital.'],
                    ['Presensi & Attendance', 'calendar-clock', 'Tracking kehadiran berbasis GPS/selfie dan rekap HRD.'],
                    ['Appraisal (KPI)', 'star', 'Penilaian performa, probation, reviewer bertingkat, dan monitoring.'],
                    ['Learning Management', 'graduation-cap', 'Materi SOP, pre/post test, KKM, dan training event.'],
                ] as $feature)
                    <div class="glass-card rounded-[2rem] p-6 group hover:-translate-y-2 transition-all duration-300">
                        <div class="w-12 h-12 rounded-xl bg-brand/10 text-brand flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                            <i data-lucide="{{ $feature[1] }}" class="w-6 h-6"></i>
                        </div>
                        <h4 class="text-lg font-bold mb-2">{{ $feature[0] }}</h4>
                        <p class="text-gray-400 text-sm leading-relaxed">{{ $feature[2] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="seo-area" class="py-24 relative overflow-hidden bg-dark-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white/5 border border-white/10 text-sm text-gray-300 mb-6">
                        <i data-lucide="map-pin" class="w-4 h-4 text-brand"></i> Jangkauan Area Nasional
                    </div>
                    <h2 class="text-3xl md:text-5xl font-bold mb-6 leading-tight">Sistem HRD Pilihan Pemilik Franchise Nasional</h2>
                    <p class="text-lg text-gray-400 mb-8 leading-relaxed">Map interaktif sederhana ini menjaga landing tetap ringan tanpa dependency map berat. Klik atau hover marker untuk melihat area.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                        @foreach($areaLocations as $area)
                            <div class="flex items-center gap-2"><i data-lucide="check-circle" class="text-brand w-4 h-4"></i>{{ $area['name'] }}</div>
                        @endforeach
                    </div>
                </div>
                <div class="glass-card relative min-h-[360px] overflow-hidden rounded-[2rem] p-6">
                    <div class="absolute inset-6 rounded-[2rem] border border-brand/20 bg-[radial-gradient(circle_at_40%_60%,rgba(228,168,83,.28),transparent_18%),radial-gradient(circle_at_65%_45%,rgba(228,168,83,.18),transparent_14%),linear-gradient(135deg,rgba(255,255,255,.08),rgba(255,255,255,.02))]"></div>
                    <svg viewBox="0 0 900 420" class="relative z-10 h-full min-h-[320px] w-full text-brand/20" aria-label="Map Indonesia sederhana">
                        <path d="M60 205 C140 150,220 155,300 210 C250 230,140 235,60 205Z" fill="currentColor"/>
                        <path d="M315 245 C420 220,520 230,600 270 C510 292,405 285,315 245Z" fill="currentColor"/>
                        <path d="M455 135 C560 80,675 92,725 150 C640 185,535 185,455 135Z" fill="currentColor"/>
                        <path d="M640 210 C705 170,790 180,840 225 C770 260,695 255,640 210Z" fill="currentColor"/>
                        <path d="M555 315 C630 292,705 305,760 342 C680 365,610 355,555 315Z" fill="currentColor"/>
                    </svg>
                    @foreach($areaLocations as $area)
                        <button type="button" class="map-pin absolute z-20" style="left: {{ $area['x'] }}%; top: {{ $area['y'] }}%;" aria-label="{{ $area['name'] }}">
                            <span class="absolute left-1/2 top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand/30 animate-ping"></span>
                            <span class="relative block h-3 w-3 rounded-full bg-brand ring-4 ring-dark-900"></span>
                            <span class="map-tooltip pointer-events-none absolute left-1/2 top-0 min-w-max -translate-x-1/2 -translate-y-full scale-95 rounded-xl bg-dark-900 px-3 py-2 text-xs font-semibold text-white opacity-0 shadow-xl transition">{{ $area['name'] }}<span class="block text-[10px] font-normal text-gray-400">{{ $area['province'] }}</span></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 relative bg-brand">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle,#000_2px,transparent_2px)] [background-size:20px_20px]"></div>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="bg-dark-900 rounded-[3rem] p-8 md:p-16 flex flex-col md:flex-row items-center justify-between gap-10 shadow-2xl">
                <div class="text-center md:text-left">
                    <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">{{ $setting->value('cta_title') }}</h2>
                    <p class="text-gray-400 text-lg">{{ $setting->value('cta_description') }}</p>
                </div>
                <a href="{{ $safeUrl($setting->value('cta_button_url'), route('register')) }}" class="block w-full md:w-auto text-center px-10 py-5 rounded-full bg-brand hover:bg-white text-dark-900 font-bold text-xl transition-colors shadow-xl">{{ $setting->value('cta_button_label') }}</a>
            </div>
        </div>
    </section>

    <footer class="bg-dark-900 pt-16 pb-8 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2 mb-6">
                        @if($setting->logo_url)
                            <img src="{{ $setting->logo_url }}" alt="{{ $websiteName }}" class="h-9 w-auto object-contain">
                        @else
                            <div class="w-8 h-8 rounded-full bg-brand flex items-center justify-center text-dark-900 font-bold">O</div>
                        @endif
                        <span class="font-bold text-xl tracking-tight text-white">{{ $websiteName }}</span>
                    </div>
                    <p class="text-gray-400 text-sm max-w-sm mb-6">{{ $setting->value('footer_description') }}</p>
                    <div class="text-xs text-gray-500">{{ $setting->value('short_tagline') }}</div>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Akses Cepat</h4>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li><a href="#fitur" class="hover:text-brand transition-colors">Modul Aplikasi</a></li>
                        <li><a href="{{ route('careers.index') }}" class="hover:text-brand transition-colors">Lowongan Karir</a></li>
                        <li><a href="{{ route('walk-ins.index') }}" class="hover:text-brand transition-colors">Walk In Interview</a></li>
                        <li><a href="#tim" class="hover:text-brand transition-colors">Tim HR Kami</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Kantor Pusat</h4>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-start gap-2"><i data-lucide="map-pin" class="w-4 h-4 mt-0.5"></i><span>{{ $setting->value('office_address') }}</span></li>
                        <li class="flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4"></i><a href="mailto:{{ $setting->value('office_email') }}" class="hover:text-brand">{{ $setting->value('office_email') }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-white/10 text-center text-sm text-gray-500 flex flex-col md:flex-row justify-between items-center">
                <p>{{ $setting->value('copyright_text') }}</p>
                <div class="flex gap-4 mt-4 md:mt-0">
                    <a href="#fitur" class="hover:text-white">Privacy Policy</a>
                    <a href="#fitur" class="hover:text-white">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenuButton?.addEventListener('click', () => {
            const hidden = mobileMenu.classList.toggle('hidden');
            mobileMenuButton.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        });
        mobileMenu?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => mobileMenu.classList.add('hidden'));
        });
    </script>
</body>
</html>
