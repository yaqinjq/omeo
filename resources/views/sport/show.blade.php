@extends('layouts.app')
@section('title', 'Detail Event Sport')
@section('content')
<div class="p-4" style="max-width:640px;">
    <a href="{{ route('sport.index') }}" style="font-size:12.5px; color:#7C3AED; text-decoration:none; font-weight:600;">← Kembali ke daftar event</a>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin:12px 0;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin:12px 0;">✗ {{ session('error') }}</div>
    @endif

    <div style="background:white; border:1.5px solid #E2E8F0; border-radius:14px; padding:20px; margin-top:12px;">
        <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">{{ $event->title ?: ucfirst($event->sport) }}</h1>
        <div style="font-size:12.5px; color:#7C3AED; font-weight:600; margin-bottom:12px;">{{ ucfirst($event->sport) }}</div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; font-size:13px;">
            <div><div style="color:#94A3B8; font-size:10.5px; font-weight:600;">LOKASI</div><div style="color:#1e293b; font-weight:600;">{{ $event->location }}</div></div>
            <div><div style="color:#94A3B8; font-size:10.5px; font-weight:600;">TANGGAL</div><div style="color:#1e293b; font-weight:600;">{{ $event->event_date->format('d M Y') }}</div></div>
            <div><div style="color:#94A3B8; font-size:10.5px; font-weight:600;">JAM (WIB)</div><div style="color:#1e293b; font-weight:600;">{{ $event->start_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('H:i') }} – {{ $event->end_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('H:i') }}</div></div>
            @if($event->has_coach)
            <div><div style="color:#94A3B8; font-size:10.5px; font-weight:600;">COACH / KUOTA</div><div style="color:#1e293b; font-weight:600;">{{ $event->coach_name ?: '-' }} · {{ $event->registrations()->whereIn('status',['registered','cancellation_requested'])->count() }}/{{ $event->capacity }}</div></div>
            @else
            <div><div style="color:#94A3B8; font-size:10.5px; font-weight:600;">COACH</div><div style="color:#1e293b; font-weight:600;">Tanpa coach (tanpa kuota umum)</div></div>
            @endif
        </div>

        <div style="border-top:1.5px solid #F1F5F9; padding-top:16px;">
            @if(!$myRegistration)
                @if($event->isRegistrationOpen())
                    @if($event->isFull())
                        <div style="font-size:13px; color:#991B1B; font-weight:600;">Kuota event ini sudah penuh.</div>
                    @else
                        <form method="POST" action="{{ route('sport.register', $event) }}">
                            @csrf
                            <button type="submit" style="background:#7C3AED; color:white; border:none; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">Daftar Event Ini</button>
                        </form>
                    @endif
                @else
                    <div style="font-size:13px; color:#94A3B8;">Pendaftaran untuk event ini sudah ditutup atau belum dipublish.</div>
                @endif
            @else
                <div style="font-size:13px; color:#1e293b; font-weight:600; margin-bottom:10px;">
                    Status Anda:
                    <span style="padding:3px 10px; border-radius:99px; background:#EFF6FF; color:#1D4ED8; font-size:11.5px;">
                        {{ ['registered' => 'Terdaftar', 'cancellation_requested' => 'Menunggu Keputusan Pembatalan', 'cancelled' => 'Dibatalkan'][$myRegistration->status] ?? $myRegistration->status }}
                    </span>
                </div>

                @if($myRegistration->status === 'registered')
                    <form method="POST" action="{{ route('sport.registrations.request-cancellation', $myRegistration) }}" style="margin-bottom:10px;" onsubmit="return confirm('Ajukan pembatalan keikutsertaan di event ini?')">
                        @csrf
                        <button type="submit" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer;">Ajukan Pembatalan</button>
                    </form>
                @endif

                @if($myRegistration->attendance)
                    <div style="font-size:12.5px; color:#166534; font-weight:600;">✓ Kehadiran sudah dikonfirmasi pada {{ $myRegistration->attendance->confirmed_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('d M Y H:i') }} WIB.</div>
                @elseif($event->isAttendanceWindowOpen() && $myRegistration->status !== 'cancelled')
                    <div x-data="sportSelfieCapture('{{ route('sport.registrations.confirm-attendance', $myRegistration) }}')" x-cloak>
                        <template x-if="!photoDataUrl">
                            <div>
                                <button type="button" @click="startCamera()" x-show="!streamActive"
                                        style="background:#166534; color:white; border:none; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">
                                    📷 Buka Kamera & Konfirmasi Hadir
                                </button>
                                <div x-show="streamActive" style="margin-top:8px;">
                                    <video x-ref="video" autoplay playsinline muted style="width:100%; max-width:280px; border-radius:10px; background:#000;"></video>
                                    <div style="margin-top:8px;">
                                        <button type="button" @click="capture()" style="background:#166534; color:white; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:700; cursor:pointer;">Ambil Foto</button>
                                    </div>
                                </div>
                                <canvas x-ref="canvas" style="display:none;"></canvas>
                            </div>
                        </template>
                        <template x-if="photoDataUrl">
                            <div>
                                <img :src="photoDataUrl" style="width:140px; border-radius:10px; display:block; margin-bottom:8px;">
                                <button type="button" @click="retake()" :disabled="submitting" style="background:#F1F5F9; color:#475569; border:none; padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; margin-right:6px;">Ambil Ulang</button>
                                <form method="POST" :action="confirmUrl" @submit="submitting = true" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="capture_mode" value="live_camera">
                                    <input type="hidden" name="selfie_photo_data" :value="photoDataUrl">
                                    <button type="submit" :disabled="submitting" style="background:#166534; color:white; border:none; padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:700; cursor:pointer;">Kirim Konfirmasi</button>
                                </form>
                            </div>
                        </template>
                        <p x-show="error" x-text="error" style="color:#991B1B; font-size:11px; margin-top:6px;"></p>
                    </div>
                    <p style="font-size:11px; color:#94A3B8; margin-top:6px;">Jendela konfirmasi dibuka sejak event selesai sampai sekitar 1 jam kemudian. Selfie wajib diambil langsung dari kamera (bukan upload file).</p>
                @elseif($myRegistration->status !== 'cancelled')
                    <div style="font-size:12px; color:#94A3B8;">Konfirmasi kehadiran belum dibuka (buka setelah event selesai) atau sudah tutup.</div>
                @endif
            @endif
        </div>
    </div>
</div>

<script>
    function sportSelfieCapture(confirmUrl) {
        return {
            confirmUrl: confirmUrl,
            streamActive: false,
            photoDataUrl: null,
            error: null,
            submitting: false,
            stream: null,
            async startCamera() {
                this.error = null;
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.error = 'Browser ini tidak mendukung akses kamera.';
                    return;
                }
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    });
                    this.streamActive = true;
                    this.$nextTick(() => { this.$refs.video.srcObject = this.stream; });
                } catch (e) {
                    this.error = 'Tidak bisa mengakses kamera. Pastikan izin kamera diaktifkan di browser.';
                }
            },
            capture() {
                const video = this.$refs.video;
                const canvas = this.$refs.canvas;
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                this.photoDataUrl = canvas.toDataURL('image/jpeg', 0.85);
                this.stopCamera();
            },
            retake() {
                this.photoDataUrl = null;
                this.startCamera();
            },
            stopCamera() {
                if (this.stream) {
                    this.stream.getTracks().forEach((t) => t.stop());
                    this.stream = null;
                }
                this.streamActive = false;
            },
        };
    }
</script>
@endsection
