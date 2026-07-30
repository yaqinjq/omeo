@php
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Support\Facades\Route;

  $programCollection = collect($programs ?? []);
  $upcomingEventCollection = collect($upcomingEvents ?? []);
  $legacyCollection = collect($legacyItems ?? []);
  $trainerProfile = $trainerProfile ?? null;
  $trainerEventCollection = collect($trainerEvents ?? []);

  $routeExists = fn (string $name): bool => Route::has($name);
  $dashboardUrl = url('/dashboard');
  $myTrainingUrl = url('/my-training');

  $relation = function ($model, string $name) {
      if ($model instanceof Model && $model->relationLoaded($name)) {
          return collect($model->getRelation($name));
      }

      return collect();
  };

  $value = fn ($model, string $key, $default = null) => data_get($model, $key, $default);
  $dateText = fn ($date) => $date ? optional($date)->format('d/m/Y H:i') : '-';

  $completedPrograms = $programCollection->filter(fn ($program) => (float) $value($program, 'progress_percent', 0) >= 100)->count();
  $inProgressPrograms = $programCollection->filter(fn ($program) => (float) $value($program, 'progress_percent', 0) > 0 && (float) $value($program, 'progress_percent', 0) < 100)->count();
  $totalMaterials = $programCollection->sum(fn ($program) => (int) $value($program, 'total_materials_count', 0));
  $completedMaterials = $programCollection->sum(fn ($program) => (int) $value($program, 'completed_materials_count', 0));
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>My Training</title>
  <style>
    :root{--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--soft:#f8fafc;--brand:#2563eb;--ok:#059669;--warn:#b45309}
    *{box-sizing:border-box}
    body{margin:0;font-family:Arial,sans-serif;background:#f6f8fb;color:var(--ink)}
    a{color:inherit}
    .topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--line)}
    .topbar-inner{max-width:1180px;margin:0 auto;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .brand{font-weight:800;font-size:18px}
    .nav{display:flex;gap:8px;flex-wrap:wrap}
    .wrap{max-width:1180px;margin:0 auto;padding:24px 18px 48px}
    .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 12px 28px rgba(15,23,42,.06)}
    .pad{padding:22px}
    .hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}
    h1,h2,h3{margin:0}
    h1{font-size:30px;line-height:1.2}
    h2{font-size:18px}
    h3{font-size:16px}
    p{line-height:1.55}
    .muted{color:var(--muted)}
    .small{font-size:13px}
    .grid{display:grid;gap:16px}
    .stats{grid-template-columns:repeat(4,minmax(0,1fr));margin-top:18px}
    .main{grid-template-columns:minmax(0,1.7fr) minmax(280px,.85fr);margin-top:18px}
    .programs{grid-template-columns:repeat(2,minmax(0,1fr))}
    .stat{padding:16px;border:1px solid var(--line);border-radius:12px;background:var(--soft)}
    .stat .num{font-size:26px;font-weight:800;margin-top:6px}
    .btn,.btn-primary,.btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:38px;padding:9px 13px;border-radius:10px;text-decoration:none;border:1px solid var(--line);font-weight:700;font-size:14px;background:#fff;cursor:pointer}
    .btn-primary{background:var(--brand);border-color:var(--brand);color:#fff}
    .btn-outline{color:var(--brand)}
    .btn-full{width:100%}
    .badge{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:800}
    .badge-ok{background:#dcfce7;color:#166534}
    .badge-warn{background:#fef3c7;color:#92400e}
    .alert{padding:14px 16px;border-radius:12px;border:1px solid #fcd34d;background:#fffbeb;color:#92400e;margin-top:16px}
    .empty{padding:18px;border:1px dashed #cbd5e1;border-radius:12px;color:var(--muted);background:#fff}
    .program{overflow:hidden}
    .thumb{height:150px;background:#e2e8f0;overflow:hidden}
    .thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .progress{height:9px;border-radius:999px;background:#e2e8f0;overflow:hidden}
    .bar{height:100%;background:var(--ok)}
    .material{border-top:1px solid var(--line);padding:12px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .event,.legacy{border:1px solid var(--line);border-radius:12px;padding:14px;background:#fff}
    .stack{display:flex;flex-direction:column;gap:12px}
    input[type=text],input[type=file],textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 11px}
    form{margin:0}
    .camera-field{position:relative}
    .camera-field input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
    .camera-btn{display:flex;align-items:center;justify-content:center;min-height:42px;border:1px dashed #93c5fd;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:13px;font-weight:800}
    .geo-note{padding:10px 12px;border-radius:10px;background:#f8fafc;border:1px solid var(--line);color:var(--muted);font-size:12px;line-height:1.45}
    .geo-note.is-ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .geo-note.is-warn{background:#fffbeb;border-color:#fcd34d;color:#92400e}
    @media(max-width:860px){
      .hero{display:block}.main,.programs{grid-template-columns:1fr}.topbar-inner{align-items:flex-start;flex-direction:column}.nav{width:100%}.btn,.btn-primary,.btn-outline{width:100%}.thumb{height:180px}
      .pad{padding:16px}.wrap{padding:16px 14px 40px}.hero .stat{min-width:0!important;margin-top:12px;padding:12px}
      .stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}
      .stats .stat{padding:10px 12px;border-radius:10px}
      .stats .stat .small{font-size:11px;line-height:1.25}
      .stats .stat .num{font-size:20px;margin-top:3px}
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <div>
        <div class="brand">OMEO HR Suite</div>
        <div class="muted small">Learning Portal</div>
      </div>
      <nav class="nav">
        <a class="btn-outline" href="{{ $dashboardUrl }}">Dashboard</a>
        <a class="btn-primary" href="{{ $myTrainingUrl }}">My Training</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <section class="card pad">
      <div class="hero">
        <div>
          <div class="badge">Learning Portal</div>
          <h1 style="margin-top:10px">My Training</h1>
          <p class="muted">Pantau program LMS, materi, live training, pendaftaran, dan daftar hadir dari satu halaman.</p>
        </div>
        <div class="stat" style="min-width:260px">
          <strong>Fokus hari ini</strong>
          <p class="muted small" style="margin-bottom:0">Buka materi yang belum selesai, daftar event bila diundang, lalu check-in ketika sesi dibuka.</p>
        </div>
      </div>

      @isset($message)
        <div class="alert">{{ $message }}</div>
      @endisset

      @if(! empty($trainingWarning))
        <div class="alert">{{ $trainingWarning }}</div>
      @endif

      <div class="grid stats">
        <div class="stat"><div class="small muted">Program aktif</div><div class="num">{{ $programCollection->count() }}</div></div>
        <div class="stat"><div class="small muted">Sedang berjalan</div><div class="num">{{ $inProgressPrograms }}</div></div>
        <div class="stat"><div class="small muted">Materi selesai</div><div class="num">{{ $completedMaterials }}/{{ $totalMaterials }}</div></div>
        <div class="stat"><div class="small muted">Event mendatang</div><div class="num">{{ $upcomingEventCollection->count() }}</div></div>
      </div>
    </section>

    @empty($message)
      <section class="grid main">
        <div class="stack">
          <div class="card pad">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:16px">
              <div>
                <h2>Program LMS Aktif</h2>
                <div class="muted small">Materi training yang ditugaskan untuk akun karyawan Anda.</div>
              </div>
              <span class="badge badge-ok">{{ $completedPrograms }} selesai</span>
            </div>

            <div class="grid programs">
              @forelse($programCollection as $program)
                @php
                  $materials = $relation($program, 'materials');
                  $progressPercent = (float) $value($program, 'progress_percent', 0);
                  $firstOpenMaterial = $materials->first(fn ($item) => ! (bool) $value($item, 'is_locked', false));
                  $thumb = $value($program, 'program_thumbnail', asset('images/default-training.svg'));
                @endphp
                <article class="card program">
                  <div class="thumb"><img src="{{ $thumb }}" alt="Thumbnail {{ $value($program, 'name', 'Training') }}"></div>
                  <div class="pad">
                    <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                      <span class="badge">{{ ucfirst((string) $value($program, 'audience_scope', 'general')) }}</span>
                      <span class="badge {{ $progressPercent >= 100 ? 'badge-ok' : ($progressPercent > 0 ? '' : 'badge-warn') }}">
                        {{ $progressPercent >= 100 ? 'Selesai' : ($progressPercent > 0 ? 'Berjalan' : 'Belum mulai') }}
                      </span>
                    </div>
                    <h3 style="margin-top:12px">{{ $value($program, 'name', '-') }}</h3>
                    <p class="muted small">Mentor: {{ $value($program, 'mentor.name', '-') }}</p>
                    <div class="progress"><div class="bar" style="width:{{ max(0, min(100, $progressPercent)) }}%"></div></div>
                    <p class="muted small">{{ (int) $value($program, 'completed_materials_count', 0) }}/{{ (int) $value($program, 'total_materials_count', $materials->count()) }} materi selesai</p>

                    @if($firstOpenMaterial && $routeExists('my-training.materials.show'))
                      <a class="btn-primary btn-full" href="{{ route('my-training.materials.show', [$program, $firstOpenMaterial]) }}">Buka Materi</a>
                    @else
                      <div class="empty small">Belum ada materi yang bisa dibuka.</div>
                    @endif

                    @if($materials->isNotEmpty())
                      <div style="margin-top:14px">
                        @foreach($materials->take(6) as $material)
                          @php
                            $materialStatus = (string) $value($material, 'lms_progress.status', 'assigned');
                            $materialLocked = (bool) $value($material, 'is_locked', false);
                          @endphp
                          <div class="material">
                            <div>
                              <strong>{{ $value($material, 'pivot.sequence_order', $loop->iteration) }}. {{ $value($material, 'title', '-') }}</strong>
                              <div class="muted small">{{ $materialLocked ? 'Terkunci' : ucfirst(str_replace('_', ' ', $materialStatus)) }}</div>
                            </div>
                            @if(! $materialLocked && $routeExists('my-training.materials.show'))
                              <a class="btn-outline" href="{{ route('my-training.materials.show', [$program, $material]) }}">Buka</a>
                            @endif
                          </div>
                        @endforeach
                      </div>
                    @endif
                  </div>
                </article>
              @empty
                <div class="empty">Belum ada program LMS yang di-assign ke akun karyawan Anda.</div>
              @endforelse
            </div>
          </div>

          <div class="card pad">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px">
              <div>
                <h2>Riwayat Training Legacy</h2>
                <div class="muted small">Data training lama yang masih tersimpan.</div>
              </div>
              <span class="badge">{{ $legacyCollection->count() }} item</span>
            </div>
            <div class="grid programs">
              @forelse($legacyCollection as $item)
                <div class="legacy">
                  <strong>{{ $value($item, 'material.title', '-') }}</strong>
                  <div class="muted small">{{ $value($item, 'material.category', '-') }}</div>
                  <div style="margin-top:10px" class="small">
                    Status: <strong>{{ $value($item, 'status', '-') }}</strong>
                    &nbsp; Nilai: <strong>{{ $value($item, 'quiz_score', '-') }}</strong>
                  </div>
                </div>
              @empty
                <div class="empty">Belum ada history training legacy.</div>
              @endforelse
            </div>
          </div>
        </div>

        <aside class="stack">
          <div class="card pad">
            <h2>Trainer Internal</h2>
            <p class="muted small">Karyawan senior dapat mengajukan diri sebagai trainer internal.</p>
            @if($trainerProfile)
              <div class="badge {{ $value($trainerProfile, 'status') === 'active' ? 'badge-ok' : 'badge-warn' }}">Status trainer: {{ ucfirst((string) $value($trainerProfile, 'status', '-')) }}</div>
            @endif

            @if($value($trainerProfile, 'status') === 'active')
              <div class="alert">Anda aktif sebagai trainer.</div>
              @if($routeExists('trainer.events.index'))
                <a class="btn-primary btn-full" href="{{ route('trainer.events.index') }}">Buka Trainer Console</a>
              @endif
              @if($trainerEventCollection->isNotEmpty())
                <div class="stack" style="margin-top:12px">
                  @foreach($trainerEventCollection as $event)
                    <div class="event">
                      <strong>{{ $value($event, 'title', '-') }}</strong>
                      <div class="muted small">{{ $dateText($value($event, 'starts_at')) }} | {{ (int) $value($event, 'participants_count', 0) }} peserta</div>
                    </div>
                  @endforeach
                </div>
              @endif
            @elseif($routeExists('my-training.trainer-application.store'))
              <form method="POST" action="{{ route('my-training.trainer-application.store') }}" class="stack">
                @csrf
                <input type="text" name="specialty" value="{{ old('specialty', $value($trainerProfile, 'specialty')) }}" placeholder="Keahlian training">
                <textarea name="notes" rows="3" placeholder="Pengalaman atau alasan pengajuan">{{ old('notes', $value($trainerProfile, 'notes')) }}</textarea>
                <button class="btn-primary btn-full" type="submit">{{ $trainerProfile ? 'Perbarui Pengajuan' : 'Ajukan Jadi Trainer' }}</button>
              </form>
            @endif
          </div>

          <div class="card pad">
            <h2>Event Training</h2>
            <p class="muted small">Daftar dan check-in event training live online atau on-site.</p>
            <div class="stack">
              @forelse($upcomingEventCollection as $event)
                @php
                  $participants = $relation($event, 'participants');
                  $participant = $participants->first();
                  $participantStatus = (string) $value($participant, 'status', 'belum terdaftar');
                  $eventStatus = (string) $value($event, 'status', 'published');
                  $requiresRegistration = (bool) $value($event, 'requires_registration', true);
                  $canRegister = $participantStatus === 'invited' && in_array($eventStatus, ['published', 'started'], true) && $routeExists('my-training.events.register');
                  $canCheckIn = (
                      $participantStatus === 'registered'
                      || ($participantStatus === 'invited' && ! $requiresRegistration)
                    )
                    && $routeExists('my-training.events.check-in');
                  $canOpenMeeting = $value($event, 'event_type') === 'meeting'
                    && filled($value($event, 'meeting_url'))
                    && in_array($participantStatus, ['checked_in', 'attended'], true);
                @endphp
                <div class="event">
                  <strong>{{ $value($event, 'title', '-') }}</strong>
                  <div class="muted small">{{ strtoupper((string) $value($event, 'event_type', 'event')) }} | {{ $value($event, 'platform', '-') }} | {{ $dateText($value($event, 'starts_at')) }}</div>
                  <div class="small" style="margin-top:8px">Status Anda: <strong>{{ ucfirst(str_replace('_', ' ', $participantStatus)) }}</strong></div>
                  @if($value($event, 'participant_instruction'))
                    <div class="alert small">{{ $value($event, 'participant_instruction') }}</div>
                  @endif
                  <div class="stack" style="margin-top:12px">
                    @if($canOpenMeeting)
                      <a class="btn-primary btn-full" target="_blank" rel="noopener" href="{{ $value($event, 'meeting_url') }}">Buka Link {{ $value($event, 'platform', 'Meeting') }}</a>
                    @endif
                    @if($canRegister)
                      <form method="POST" action="{{ route('my-training.events.register', $event) }}">@csrf<button class="btn-primary btn-full" type="submit">Daftar Event</button></form>
                    @endif
                    @if($canCheckIn)
                      <form method="POST" action="{{ route('my-training.events.check-in', $event) }}" enctype="multipart/form-data" class="stack js-checkin-form">
                        @csrf
                        @if((bool) $value($event, 'requires_photo_validation', false))
                          <label class="camera-field">
                            <span class="camera-btn">Buka Kamera Selfie</span>
                            <input type="file" name="selfie_photo" accept="image/*" capture="user" required>
                          </label>
                          <label class="camera-field">
                            <span class="camera-btn">Buka Kamera Lokasi</span>
                            <input type="file" name="environment_photo" accept="image/*" capture="environment" required>
                          </label>
                        @endif
                        @if((bool) $value($event, 'requires_geolocation', false))
                          <input type="hidden" name="latitude" class="js-latitude" required>
                          <input type="hidden" name="longitude" class="js-longitude" required>
                          <input type="hidden" name="address" class="js-address">
                          <div class="geo-note js-geo-note">Membaca lokasi GPS otomatis...</div>
                        @else
                          <input type="hidden" name="address" class="js-address">
                        @endif
                        <button class="btn-primary btn-full" type="submit">Check-in Event</button>
                      </form>
                    @endif
                  </div>
                </div>
              @empty
                <div class="empty">Belum ada event training yang dijadwalkan untuk Anda.</div>
              @endforelse
            </div>
          </div>
        </aside>
      </section>
    @endempty
  </main>
  <script>
    (function () {
      const forms = Array.from(document.querySelectorAll('.js-checkin-form'));

      function setNote(note, text, state) {
        if (!note) return;
        note.textContent = text;
        note.classList.remove('is-ok', 'is-warn');
        if (state) note.classList.add(state);
      }

      function reverseGeocode(lat, lng, addressInput, note) {
        const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then((response) => response.ok ? response.json() : null)
          .then((data) => {
            const address = data && data.display_name ? data.display_name : ('GPS: ' + lat + ', ' + lng);
            if (addressInput) addressInput.value = address;
            setNote(note, 'Lokasi terbaca: ' + address, 'is-ok');
          })
          .catch(() => {
            const fallback = 'GPS: ' + lat + ', ' + lng;
            if (addressInput) addressInput.value = fallback;
            setNote(note, 'Lokasi GPS terbaca. Alamat detail belum tersedia.', 'is-ok');
          });
      }

      forms.forEach((form) => {
        const latInput = form.querySelector('.js-latitude');
        const lngInput = form.querySelector('.js-longitude');
        const addressInput = form.querySelector('.js-address');
        const note = form.querySelector('.js-geo-note');

        if (!latInput || !lngInput) return;

        if (!navigator.geolocation) {
          setNote(note, 'Browser belum mendukung pembacaan lokasi otomatis.', 'is-warn');
          return;
        }

        navigator.geolocation.getCurrentPosition(
          (position) => {
            const lat = position.coords.latitude.toFixed(7);
            const lng = position.coords.longitude.toFixed(7);
            latInput.value = lat;
            lngInput.value = lng;
            setNote(note, 'Lokasi GPS terbaca, mengambil alamat...', 'is-ok');
            reverseGeocode(lat, lng, addressInput, note);
          },
          () => {
            setNote(note, 'Izinkan akses lokasi agar check-in bisa dilakukan.', 'is-warn');
          },
          { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
        );

        form.addEventListener('submit', function (event) {
          if ((!latInput.value || !lngInput.value) && latInput.required) {
            event.preventDefault();
            setNote(note, 'Lokasi belum terbaca. Aktifkan GPS dan izinkan akses lokasi.', 'is-warn');
          }
        });
      });
    })();
  </script>
</body>
</html>
