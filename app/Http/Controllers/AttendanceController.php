<?php

namespace App\Http\Controllers;

use App\Models\AttendanceScan;
use App\Models\AttendanceSession;
use App\Models\Outlet;
use App\Services\AttendanceService;
use App\Services\EmployeeProfileCompletenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly EmployeeProfileCompletenessService $profileCompletenessService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $employee = $user?->employee;

        if (! $employee) {
            abort(403, 'Akun ini belum terhubung ke data karyawan.');
        }

        if (! in_array((string) $employee->status_employment, ['probation', 'contract', 'permanent'], true)) {
            abort(403, 'Status karyawan tidak diizinkan untuk presensi.');
        }

        $attendanceEligibility = $this->resolveAttendanceEligibility($user, $employee);
        $assignedOutlet = $employee->outlet;
        $selectedOutlet = $assignedOutlet;

        if (! $selectedOutlet && $request->filled('outlet_id')) {
            $selectedOutlet = Outlet::query()->find((int) $request->integer('outlet_id'));
        }

        $fallbackTimezone = 'Asia/Jakarta';
        $timezone = $this->attendanceService->outletTimezone($selectedOutlet);
        $nowUtc = $this->attendanceService->nowUtc();
        $today = $this->attendanceService->localDate($nowUtc, $selectedOutlet ? $timezone : $fallbackTimezone);

        $todaySession = AttendanceSession::query()
            ->with(['outlet:id,name,timezone', 'scans'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        $recentSessions = AttendanceSession::query()
            ->with(['outlet:id,name,timezone'])
            ->where('user_id', $user->id)
            ->orderByDesc('work_date')
            ->limit(14)
            ->get();

        $attendanceState = 'before_check_in';
        if ($todaySession?->first_in_at_utc && ! $todaySession?->last_out_at_utc) {
            $attendanceState = 'before_check_out';
        } elseif ($todaySession?->last_out_at_utc) {
            $attendanceState = 'completed';
        }

        return view('attendance.index', [
            'employee' => $employee,
            'assignedOutlet' => $assignedOutlet,
            'selectedOutlet' => $selectedOutlet,
            'availableOutlets' => Outlet::query()->operational()->orderBy('name')->get(['id', 'name', 'timezone']),
            'timezone' => $timezone,
            'today' => $today,
            'todaySession' => $todaySession,
            'recentSessions' => $recentSessions,
            'nowLocal' => $nowUtc->copy()->setTimezone($timezone),
            'attendanceState' => $attendanceState,
            'maxAccuracyMeters' => $this->attendanceService->maxAcceptedAccuracyMeters($selectedOutlet),
            'attendanceEligibility' => $attendanceEligibility,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        return $this->storeScan($request, 'in');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        return $this->storeScan($request, 'out');
    }

    private function storeScan(Request $request, string $scanType): RedirectResponse
    {
        $user = $request->user();
        $employee = $user?->employee;

        if (! $employee) {
            return $this->rejectAttendance($request, $scanType, 'employee_missing', 'Akun belum terhubung ke data karyawan. Hubungi HRD.');
        }

        if (! in_array((string) $employee->status_employment, ['probation', 'contract', 'permanent'], true)) {
            return $this->rejectAttendance($request, $scanType, 'employment_status_invalid', 'Status karyawan tidak diizinkan untuk presensi.');
        }

        $attendanceEligibility = $this->resolveAttendanceEligibility($user, $employee);
        if (! $attendanceEligibility['allowed']) {
            return $this->rejectAttendance(
                $request,
                $scanType,
                'attendance_locked_until_profile_complete',
                (string) $attendanceEligibility['message'],
                (array) ($attendanceEligibility['context'] ?? [])
            );
        }

        if (! $this->requestIsSecure($request)) {
            return $this->rejectAttendance($request, $scanType, 'insecure_request', 'Presensi GPS hanya diizinkan melalui HTTPS. Buka aplikasi melalui domain HTTPS resmi.');
        }

        $validated = $request->validate([
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'location_samples_json' => ['nullable', 'string', 'max:20000'],
            'selected_sample_index' => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'capture_mode' => ['required', 'string', 'in:live_camera'],
            'selfie_photo_data' => ['required', 'string'],
            'environment_photo_data' => ['required', 'string'],
        ], [
            'latitude.required' => 'Lokasi GPS wajib diizinkan untuk presensi.',
            'longitude.required' => 'Lokasi GPS wajib diizinkan untuk presensi.',
            'outlet_id.exists' => 'Outlet tidak valid.',
            'capture_mode.required' => 'Presensi wajib menggunakan live camera.',
            'capture_mode.in' => 'Presensi wajib menggunakan live camera, bukan upload file.',
            'selfie_photo_data.required' => 'Selfie wajib diambil langsung dari kamera.',
            'environment_photo_data.required' => 'Foto lingkungan wajib diambil langsung dari kamera.',
        ]);

        $assignedOutlet = $employee->outlet;
        $outlet = $assignedOutlet;
        if (! $outlet) {
            $outlet = Outlet::query()->find((int) ($validated['outlet_id'] ?? 0));
        }

        if (! $outlet) {
            return $this->rejectAttendance($request, $scanType, 'outlet_missing', 'Outlet belum ditentukan. Hubungi HRD atau pilih outlet terlebih dahulu.', ['validated_outlet_id' => $validated['outlet_id'] ?? null]);
        }

        $accuracy = isset($validated['accuracy']) ? (int) round((float) $validated['accuracy']) : null;
        $locationSamples = $this->sanitizeLocationSamples(
            (string) ($validated['location_samples_json'] ?? ''),
            isset($validated['selected_sample_index']) ? (int) $validated['selected_sample_index'] : null
        );
        $maxAccuracyMeters = $this->attendanceService->maxAcceptedAccuracyMeters($outlet);
        if ($accuracy !== null && $accuracy > $maxAccuracyMeters) {
            return $this->rejectAttendance(
                $request,
                $scanType,
                'gps_accuracy_low',
                sprintf('Akurasi lokasi terlalu rendah (%d meter). Maksimal %d meter, coba di tempat terbuka dan aktifkan GPS.', $accuracy, $maxAccuracyMeters),
                ['accuracy' => $accuracy, 'max_accuracy_meters' => $maxAccuracyMeters]
            );
        }

        if ($outlet->latitude === null || $outlet->longitude === null) {
            return $this->rejectAttendance($request, $scanType, 'outlet_coordinates_missing', 'Koordinat outlet belum diatur. Hubungi HRD untuk melengkapi latitude/longitude outlet.', ['outlet_id' => $outlet->id]);
        }

        $distance = $this->attendanceService->distanceMeters(
            (float) $outlet->latitude,
            (float) $outlet->longitude,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        $maxRadius = $this->attendanceService->effectiveRadiusMeters($outlet);
        if ($distance > $maxRadius) {
            return $this->rejectAttendance(
                $request,
                $scanType,
                'outside_geofence',
                sprintf('Presensi ditolak. Anda berada %.2f m dari outlet (maks %d m).', $distance, $maxRadius),
                [
                    'distance_meters' => round($distance, 2),
                    'max_radius_meters' => $maxRadius,
                    'outlet_id' => $outlet->id,
                ]
            );
        }

        $timezone = $this->attendanceService->outletTimezone($outlet);
        $nowUtc = $this->attendanceService->nowUtc();
        $nowLocal = $nowUtc->copy()->setTimezone($timezone);
        $workDate = $this->attendanceService->localDate($nowUtc, $timezone);

        $session = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $session) {
            $schedule = $this->attendanceService->resolveSchedule($user, $outlet, $workDate, $nowLocal);
            $session = new AttendanceSession([
                'user_id' => $user->id,
                'work_date' => $workDate,
                'outlet_id' => $outlet->id,
                'shift_code' => $schedule['shift_code'],
                'scheduled_in_local' => $schedule['scheduled_in_local'],
                'scheduled_out_local' => $schedule['scheduled_out_local'],
                'status' => $schedule['outlet_mismatch'] ? 'outlet_mismatch' : ($schedule['schedule_mismatch'] ? 'schedule_mismatch' : 'incomplete'),
            ]);
        }

        if ($scanType === 'in' && $session->first_in_at_utc) {
            return $this->rejectAttendance($request, $scanType, 'duplicate_check_in', 'Presensi datang hari ini sudah tercatat. Tombol hadir dinonaktifkan.', ['session_id' => $session->id]);
        }

        if ($scanType === 'out' && ! $session->first_in_at_utc) {
            return $this->rejectAttendance($request, $scanType, 'check_out_before_check_in', 'Anda belum presensi datang hari ini. Silakan hadir terlebih dahulu.', ['session_id' => $session->id]);
        }

        if ($scanType === 'out' && $session->last_out_at_utc) {
            return $this->rejectAttendance($request, $scanType, 'duplicate_check_out', 'Presensi pulang hari ini sudah tercatat.', ['session_id' => $session->id]);
        }

        if ((int) ($session->outlet_id ?? 0) !== (int) $outlet->id) {
            $session->status = 'outlet_mismatch';
        }

        if ($this->attendanceService->isOnApprovedLeave($user->id, $workDate)) {
            $session->status = 'leave';
        }

        if (! empty($validated['notes'])) {
            $session->notes = $validated['notes'];
        }

        if ($scanType === 'in') {
            $session->first_in_at_utc = $nowUtc;
        }

        if ($scanType === 'out') {
            $session->last_out_at_utc = $nowUtc;
        }

        $storedPaths = [];

        try {
            DB::beginTransaction();

            $session->save();

            $selfiePath = $this->storeBase64EvidencePhoto((string) $validated['selfie_photo_data'], 'selfie', $scanType, $user->id);
            $storedPaths[] = $selfiePath;

            $environmentPath = $this->storeBase64EvidencePhoto((string) $validated['environment_photo_data'], 'environment', $scanType, $user->id);
            $storedPaths[] = $environmentPath;

            AttendanceScan::create([
                'attendance_session_id' => $session->id,
                'scan_type' => $scanType,
                'scanned_at_utc' => $nowUtc,
                'scanned_at_local' => $nowLocal,
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'accuracy_meters' => $accuracy,
                'distance_meters' => (int) round($distance),
                'is_within_geofence' => true,
                'selfie_photo_path' => $selfiePath,
                'environment_photo_path' => $environmentPath,
                'device_json' => array_filter([
                    'user_agent' => (string) $request->userAgent(),
                    'ip' => (string) $request->ip(),
                    'platform' => (string) $request->header('Sec-CH-UA-Platform', ''),
                    'capture_mode' => 'live_camera',
                    'location_samples' => $locationSamples['samples'],
                    'selected_location_sample' => $locationSamples['selected'],
                ], fn ($value) => $value !== null && $value !== []),
                'source' => 'web_gps',
            ]);

            $session = $this->attendanceService->recomputeSession($session, $timezone);
            $session->save();

            DB::commit();
        } catch (\InvalidArgumentException $exception) {
            DB::rollBack();
            $this->cleanupStoredEvidence($storedPaths);

            return $this->rejectAttendance($request, $scanType, 'camera_payload_invalid', 'Capture kamera tidak valid. Ambil ulang selfie dan foto lingkungan langsung dari kamera.', ['exception' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->cleanupStoredEvidence($storedPaths);

            Log::error('Attendance submission failed', [
                'scan_type' => $scanType,
                'user_id' => $user?->id,
                'employee_id' => $employee?->id,
                'exception' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ]);

            return back()->with('error', 'Presensi belum berhasil disimpan. Coba ulang sekali lagi. Jika masih gagal, hubungi HRD dan informasikan waktu kejadian.');
        }

        return back()->with('success', $scanType === 'in' ? 'Berhasil presensi datang.' : 'Berhasil presensi pulang.');
    }

    private function sanitizeLocationSamples(string $payload, ?int $selectedIndex = null): array
    {
        if ($payload === '') {
            return ['samples' => [], 'selected' => null];
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return ['samples' => [], 'selected' => null];
        }

        $samples = [];
        foreach (array_slice($decoded, 0, 10) as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $lat = isset($sample['lat']) ? (float) $sample['lat'] : null;
            $lng = isset($sample['lng']) ? (float) $sample['lng'] : null;
            $accuracy = isset($sample['accuracy']) ? (float) $sample['accuracy'] : null;
            $distance = isset($sample['distance']) ? (float) $sample['distance'] : null;
            $sampleIndex = isset($sample['sample_index']) ? (int) $sample['sample_index'] : null;

            if ($lat === null || $lng === null) {
                continue;
            }

            $samples[] = array_filter([
                'sample_index' => $sampleIndex,
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
                'accuracy' => $accuracy !== null ? round($accuracy, 2) : null,
                'distance' => $distance !== null ? round($distance, 2) : null,
            ], fn ($value) => $value !== null);
        }

        $selected = null;
        if ($selectedIndex !== null) {
            foreach ($samples as $sample) {
                if ((int) ($sample['sample_index'] ?? 0) === $selectedIndex) {
                    $selected = $sample;
                    break;
                }
            }
        }

        return ['samples' => $samples, 'selected' => $selected];
    }

    private function rejectAttendance(Request $request, string $scanType, string $reason, string $message, array $context = []): RedirectResponse
    {
        Log::warning('Attendance submission rejected', array_merge([
            'reason' => $reason,
            'scan_type' => $scanType,
            'user_id' => $request->user()?->id,
            'employee_id' => $request->user()?->employee?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ], $context));

        return back()->with('error', $message);
    }

    private function cleanupStoredEvidence(array $paths): void
    {
        foreach ($paths as $path) {
            if (! $path) {
                continue;
            }

            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $exception) {
                Log::warning('Failed to cleanup attendance evidence', [
                    'path' => $path,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function storeBase64EvidencePhoto(string $dataUri, string $kind, string $scanType, int $userId): string
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/', $dataUri, $matches)) {
            throw new \InvalidArgumentException('Format data kamera tidak valid.');
        }

        $extension = strtolower($matches[1] === 'jpeg' ? 'jpg' : $matches[1]);
        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            throw new \InvalidArgumentException('File kamera tidak dapat diproses.');
        }

        if (strlen($binary) === 0) {
            throw new \InvalidArgumentException('File kamera kosong.');
        }

        if (strlen($binary) > (4 * 1024 * 1024)) {
            throw new \InvalidArgumentException('Ukuran file kamera terlalu besar.');
        }

        $filename = now()->format('Ymd_His') . '_' . $scanType . '_' . $kind . '_' . Str::uuid() . '.' . $extension;
        $path = 'attendance/' . $userId . '/' . $scanType . '/' . $kind . '/' . $filename;

        if (! Storage::disk('public')->put($path, $binary)) {
            throw new \RuntimeException('Gagal menyimpan file kamera ke storage publik.');
        }

        return $path;
    }

    private function requestIsSecure(Request $request): bool
    {
        if ($request->isSecure() || app()->environment(['local', 'testing'])) {
            return true;
        }

        return strcasecmp((string) $request->header('X-Forwarded-Proto', ''), 'https') === 0;
    }

    /**
     * @return array{allowed: bool, message: ?string, profile_complete: bool, payroll_complete: bool, requires_payroll: bool, context: array<string,mixed>}
     */
    private function resolveAttendanceEligibility($user, $employee): array
    {
        $profileComplete = true;
        $profile = null;
        $missingSections = [];

        if (Schema::hasTable('applicant_profiles')) {
            $user->loadMissing('applicantProfile');
            $profile = $user->applicantProfile;
            $missingSections = $profile?->getMissingFields() ?? [];
            $profileComplete = $profile?->isProfileComplete() ?? false;
        }

        $requiresPayroll = $this->isProbationAttendanceGate($user, $employee);
        $payrollComplete = ! $requiresPayroll || $this->profileCompletenessService->isPayrollCompleteAndVerified($employee);

        $reasons = [];
        if (! $profileComplete) {
            $reasons[] = $profile
                ? 'Lengkapi profil karyawan terlebih dahulu melalui menu Profil Saya.'
                : 'Profil karyawan belum terbentuk lengkap. Buka Profil Saya dan lengkapi data applicant profile terlebih dahulu.';
        }

        if ($requiresPayroll && ! $payrollComplete) {
            $reasons[] = 'Kelengkapan payroll dan rekening untuk masa probation harus lengkap serta sudah diverifikasi HRD sebelum presensi aktif.';
        }

        return [
            'allowed' => $reasons === [],
            'message' => $reasons === [] ? null : 'Presensi dikunci sementara. ' . implode(' ', $reasons),
            'profile_complete' => $profileComplete,
            'payroll_complete' => $payrollComplete,
            'requires_payroll' => $requiresPayroll,
            'context' => [
                'profile_complete' => $profileComplete,
                'payroll_complete' => $payrollComplete,
                'requires_payroll' => $requiresPayroll,
                'missing_profile_sections' => array_keys($missingSections),
            ],
        ];
    }

    private function isProbationAttendanceGate($user, $employee): bool
    {
        if ((string) ($employee->status_employment ?? '') === 'probation') {
            return true;
        }

        return in_array((string) ($user->role ?? ''), ['probation'], true)
            || (Schema::hasColumn('users', 'employee_status') && (string) ($user->employee_status ?? '') === 'probation');
    }
}

