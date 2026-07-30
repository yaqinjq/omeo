<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingMaterial;
use App\Models\TrainingProgram;
use App\Models\TrainingTrainer;
use App\Models\User;
use App\Services\LmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MyTrainingController extends Controller
{
    public function __construct(private readonly LmsService $lmsService)
    {
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $employee = $this->employeeForTraining($user);

            if (! $employee) {
                return $this->safeIndexResponse([
                    'message' => 'Akun Anda belum terhubung ke data karyawan (employee_id kosong). Hubungi HRD.',
                ]);
            }

            $trainerProfile = null;
            try {
                $trainerProfile = Schema::hasTable('training_trainers')
                    ? TrainingTrainer::query()->where('employee_id', $employee->id)->first()
                    : null;
            } catch (\Throwable $exception) {
                report($exception);
            }

            $trainingEventsReady = Schema::hasTable('training_events') && Schema::hasTable('training_event_participants');

            $trainerEvents = collect();
            try {
                $trainerEvents = $trainingEventsReady && $user?->isApprovedTrainer()
                    ? TrainingEvent::query()
                        ->withCount('participants')
                        ->where('mentor_user_id', $user->id)
                        ->whereIn('status', [TrainingEvent::STATUS_PUBLISHED, TrainingEvent::STATUS_STARTED])
                        ->orderBy('starts_at')
                        ->limit(5)
                        ->get()
                    : collect();
            } catch (\Throwable $exception) {
                report($exception);
            }

            $trainingWarning = null;
            $programs = collect();
            $legacyItems = collect();
            $upcomingEvents = collect();

            try {
                $programs = $this->lmsService->programsForEmployee($employee);
                $legacyItems = $this->lmsService->legacyParticipationSummary($employee);
                $upcomingEvents = $this->lmsService->upcomingEventsForEmployee($employee);
            } catch (\Throwable $exception) {
                report($exception);
                $trainingWarning = 'Data training belum bisa dimuat sempurna karena struktur database LMS/event belum sinkron. Jalankan migrasi dan bersihkan cache aplikasi.';
            }

            return $this->safeIndexResponse([
                'programs' => $programs,
                'legacyItems' => $legacyItems,
                'upcomingEvents' => $upcomingEvents,
                'trainerProfile' => $trainerProfile,
                'trainerEvents' => $trainerEvents,
                'trainingWarning' => $trainingWarning,
                'employee' => $employee,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->fallbackIndexResponse('My Training belum bisa memuat modul utama. Error sudah dicatat ke log server.');
        }
    }

    public function showMaterial(Request $request, TrainingProgram $program, TrainingMaterial $material)
    {
        $employee = $this->employeeForTraining($request->user());
        abort_unless($employee, 403);

        $employeeProgram = $this->lmsService
            ->programsForEmployee($employee)
            ->firstWhere('id', $program->id);

        abort_unless($employeeProgram, 403);

        $targetMaterial = $employeeProgram->materials->firstWhere('id', $material->id);
        abort_unless($targetMaterial, 404);

        if ($targetMaterial->is_locked) {
            return redirect()
                ->route('my-training.index')
                ->with('error', $targetMaterial->start_block_reason ?: 'Materi masih terkunci.');
        }

        return view('my_training.material', [
            'program' => $employeeProgram,
            'material' => $targetMaterial,
            'progress' => $targetMaterial->lms_progress,
            'contentUrl' => $targetMaterial->content_url,
            'contentLabel' => $targetMaterial->content_label,
        ]);
    }

    public function startMaterial(Request $request, TrainingProgram $program, TrainingMaterial $material): RedirectResponse
    {
        $employee = $this->employeeForTraining($request->user());
        abort_unless($employee, 403);

        $started = $this->lmsService->markMaterialStatus($employee, $program->loadMissing('materials'), $material->id, 'in_progress');
        if (! $started) {
            return back()->with('error', 'Materi belum bisa dimulai. Pastikan urutan materi sudah terbuka dan kontennya sudah tersedia.');
        }

        return back()->with('success', 'Materi training dimulai. Lanjutkan hingga selesai.');
    }

    public function completeMaterial(Request $request, TrainingProgram $program, TrainingMaterial $material): RedirectResponse
    {
        $employee = $this->employeeForTraining($request->user());
        abort_unless($employee, 403);

        $completed = $this->lmsService->markMaterialStatus($employee, $program->loadMissing('materials'), $material->id, 'completed');
        if (! $completed) {
            return back()->with('error', 'Materi belum bisa ditandai selesai. Lengkapi pretest/posttest dan pastikan passing score sudah terpenuhi.');
        }

        return back()->with('success', 'Materi training ditandai selesai. Materi berikutnya akan terbuka sesuai urutan program.');
    }

    public function registerEvent(Request $request, TrainingEvent $event): RedirectResponse
    {
        $employee = $this->employeeForTraining($request->user());
        abort_unless($employee, 403);

        $participant = TrainingEventParticipant::query()
            ->where('training_event_id', $event->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $participant) {
            return back()->with('error', 'Anda belum masuk daftar undangan event training ini.');
        }

        if (! $event->isOpenForRegistration()) {
            return back()->with('error', 'Pendaftaran event training belum dibuka atau sudah ditutup.');
        }

        if (in_array($participant->status, [TrainingEventParticipant::STATUS_CHECKED_IN, TrainingEventParticipant::STATUS_ATTENDED], true)) {
            return back()->with('success', 'Anda sudah terdaftar dan sudah melakukan check-in event ini.');
        }

        $participant->update([
            'status' => TrainingEventParticipant::STATUS_REGISTERED,
            'registered_at' => $participant->registered_at ?: now(),
        ]);

        return back()->with('success', 'Anda berhasil terdaftar pada event training.');
    }

    public function checkInEvent(Request $request, TrainingEvent $event): RedirectResponse
    {
        $employee = $this->employeeForTraining($request->user());
        abort_unless($employee, 403);

        $validated = $request->validate([
            'selfie_photo' => [$event->requires_photo_validation ? 'required' : 'nullable', 'image', 'max:4096'],
            'environment_photo' => [$event->requires_photo_validation ? 'required' : 'nullable', 'image', 'max:4096'],
            'latitude' => [$event->requires_geolocation ? 'required' : 'nullable', 'numeric'],
            'longitude' => [$event->requires_geolocation ? 'required' : 'nullable', 'numeric'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $participant = TrainingEventParticipant::query()
            ->where('training_event_id', $event->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $participant) {
            return back()->with('error', 'Anda belum masuk daftar undangan event training ini.');
        }

        if ($event->requires_registration && $participant->status === TrainingEventParticipant::STATUS_INVITED) {
            return back()->with('error', 'Silakan daftar event terlebih dahulu sebelum check-in.');
        }

        if (! $event->isCheckInOpen()) {
            return back()->with('error', 'Check-in event training belum dibuka atau sudah ditutup.');
        }

        if ($participant->checked_in_at || in_array($participant->status, [TrainingEventParticipant::STATUS_CHECKED_IN, TrainingEventParticipant::STATUS_ATTENDED], true)) {
            return back()->with('success', 'Anda sudah check-in pada event training ini.');
        }

        $payload = [
            'status' => TrainingEventParticipant::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
            'check_in_latitude' => $validated['latitude'] ?? null,
            'check_in_longitude' => $validated['longitude'] ?? null,
            'check_in_address' => $validated['address'] ?? null,
        ];

        if ($request->hasFile('selfie_photo')) {
            $payload['selfie_photo_path'] = $request->file('selfie_photo')->store('training/events/selfie', 'public');
        }

        if ($request->hasFile('environment_photo')) {
            $payload['environment_photo_path'] = $request->file('environment_photo')->store('training/events/environment', 'public');
        }

        $participant->update($payload);

        if (
            $event->event_type === TrainingEvent::TYPE_MEETING
            && filled($event->meeting_url)
            && filter_var($event->meeting_url, FILTER_VALIDATE_URL)
        ) {
            return redirect()->away($event->meeting_url);
        }

        return back()->with('success', 'Check-in event training berhasil dicatat.');
    }

    private function employeeForTraining(?User $user): ?Employee
    {
        if (! $user || ! Schema::hasTable('employees')) {
            return null;
        }

        try {
            // Way 1: direct employee_id link
            if ($user->employee_id) {
                $emp = Employee::query()->find($user->employee_id);
                if ($emp) {
                    return $emp;
                }
            }

            // Way 2: match by email → email_private
            if ($user->email) {
                $emp = Employee::where('email_private', $user->email)->first();
                if ($emp) {
                    DB::table('users')->where('id', $user->id)->update(['employee_id' => $emp->id]);

                    return $emp;
                }
            }

            // Way 3: match by name → full_name
            if ($user->name) {
                $emp = Employee::where('full_name', $user->name)->first();
                if ($emp) {
                    DB::table('users')->where('id', $user->id)->update(['employee_id' => $emp->id]);

                    return $emp;
                }
            }

            return null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function safeIndexResponse(array $data)
    {
        try {
            return response(view('my_training.index', $data)->render());
        } catch (\Throwable $exception) {
            report($exception);

            return $this->fallbackIndexResponse('My Training berhasil dibuka, tetapi tampilan utama belum bisa dirender. Error sudah dicatat ke log server.');
        }
    }

    private function fallbackIndexResponse(string $message)
    {
        $html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My Training</title>'
            . '<style>body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}.wrap{max-width:960px;margin:48px auto;padding:0 20px}.card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 12px 32px rgba(15,23,42,.08)}h1{margin:0 0 12px;font-size:28px}.alert{margin-top:16px;padding:14px 16px;border-radius:12px;background:#fffbeb;border:1px solid #fcd34d;color:#92400e}.link{display:inline-block;margin-top:18px;color:#2563eb;font-weight:700;text-decoration:none}</style>'
            . '</head><body><main class="wrap"><section class="card"><h1>My Training</h1><p>Halaman training tersedia untuk akun Anda.</p><div class="alert">'
            . e($message)
            . '</div><a class="link" href="' . e(url('/dashboard')) . '">Kembali ke Dashboard</a></section></main></body></html>';

        return response($html);
    }
}
