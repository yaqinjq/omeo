<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TrainingEvent;
use App\Models\TrainingFormAttempt;
use App\Models\TrainingParticipation;
use App\Models\TrainingProgram;
use App\Models\TrainingProgramEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LmsService
{
    private static array $tableCache = [];

    private function tableExists(string $table): bool
    {
        if (! isset(self::$tableCache[$table])) {
            self::$tableCache[$table] = Schema::hasTable($table);
        }

        return self::$tableCache[$table];
    }

    public function syncProgramEnrollments(TrainingProgram $program, ?int $actorId = null): void
    {
        if (
            ! $this->tableExists('training_program_enrollments')
            || ! $this->tableExists('training_material_progress')
            || ! $this->tableExists('training_program_material')
            || ! $this->tableExists('training_materials')
            || ! $this->tableExists('employees')
        ) {
            return;
        }

        $program->loadMissing('materials');
        $eligibleEmployees = $this->eligibleEmployeesForProgram($program);
        $materials = $program->materials->sortBy('pivot.sequence_order')->values();

        DB::transaction(function () use ($program, $eligibleEmployees, $materials, $actorId): void {
            foreach ($eligibleEmployees as $employee) {
                $enrollment = TrainingProgramEnrollment::firstOrCreate(
                    [
                        'training_program_id' => $program->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'status' => 'assigned',
                        'progress_percent' => 0,
                        'assigned_by' => $actorId,
                        'metadata' => ['auto_assigned' => true],
                    ]
                );

                foreach ($materials as $index => $material) {
                    $status = $program->is_sequential
                        ? ($index === 0 ? 'assigned' : 'locked')
                        : 'assigned';

                    DB::table('training_material_progress')->updateOrInsert(
                        [
                            'training_program_enrollment_id' => $enrollment->id,
                            'training_material_id' => $material->id,
                        ],
                        [
                            'employee_id' => $employee->id,
                            'training_program_id' => $program->id,
                            'status' => $status,
                            'progress_percent' => 0,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    public function programsForEmployee(Employee $employee): Collection
    {
        $this->ensureEmployeeEnrollments($employee);

        if (
            ! $this->tableExists('training_programs')
            || ! $this->tableExists('training_program_enrollments')
            || ! $this->tableExists('training_program_material')
            || ! $this->tableExists('training_materials')
            || ! $this->tableExists('training_material_progress')
        ) {
            return collect();
        }

        $departmentId = $employee->department_id;
        $positionId = $employee->position_id;
        $formsTableReady = $this->tableExists('forms');
        $materialRelations = ['mentor:id,name'];

        if ($formsTableReady && Schema::hasColumn('training_materials', 'pretest_form_id')) {
            $materialRelations[] = 'pretestForm:id,name,type';
        }

        if ($formsTableReady && Schema::hasColumn('training_materials', 'posttest_form_id')) {
            $materialRelations[] = 'posttestForm:id,name,type';
        }

        $relations = [
            'mentor:id,name',
            'materials' => fn ($query) => $query->with($materialRelations),
            'enrollments' => fn ($query) => $query
                ->where('employee_id', $employee->id)
                ->with(['progressItems.material:id,title'])
                ->latest('id'),
        ];

        if ($this->tableExists('training_events') && $this->tableExists('training_event_participants')) {
            $relations['events'] = fn ($query) => $query
                ->with(['mentor:id,name', 'participants' => fn ($nested) => $nested->where('employee_id', $employee->id)])
                ->whereIn('status', ['published', 'started'])
                ->orderBy('starts_at');
        }

        $programs = TrainingProgram::query()
            ->with($relations)
            ->where('is_active', true)
            ->where(function ($query) use ($departmentId, $positionId) {
                $query->where('audience_scope', 'general');

                if ($departmentId) {
                    $query->orWhere(function ($nested) use ($departmentId) {
                        $nested->where('audience_scope', 'department')->where('department_id', $departmentId);
                    });
                }

                if ($positionId) {
                    $query->orWhere(function ($nested) use ($positionId) {
                        $nested->where('audience_scope', 'position')->where('position_id', $positionId);
                    });
                }
            })
            ->orderByRaw("CASE audience_scope WHEN 'general' THEN 1 WHEN 'department' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get();

        return $programs->map(function (TrainingProgram $program) use ($employee, $formsTableReady) {
            /** @var TrainingProgramEnrollment|null $enrollment */
            $enrollment = $program->enrollments->firstWhere('employee_id', $employee->id);
            $progressItems = $enrollment?->progressItems ?? collect();
            $materials = $program->materials->map(function ($material) use ($progressItems, $program, $formsTableReady) {
                if (! $formsTableReady) {
                    $material->setRelation('pretestForm', null);
                    $material->setRelation('posttestForm', null);
                }

                $progress = $progressItems->firstWhere('training_material_id', $material->id);
                $isLocked = $this->isMaterialLocked($program, $material->id, $progressItems);
                $material->setAttribute('lms_progress', $progress);
                $material->setAttribute('is_locked', $isLocked);
                $material->setAttribute('content_url', $this->materialContentUrl($material));
                $material->setAttribute('content_label', $this->materialContentLabel($material));
                $material->setAttribute('start_block_reason', $this->startBlockReason($program, $material, $progressItems));
                $material->setAttribute('complete_block_reason', $this->completeBlockReason($program, $material, $progressItems));
                $material->setAttribute('status_summary', $this->materialStatusSummary($progress));

                return $material;
            });

            $program->setRelation('materials', $materials);
            $program->setAttribute('current_enrollment', $enrollment);
            $program->setAttribute('progress_percent', (float) ($enrollment?->progress_percent ?? 0));
            $program->setAttribute('completed_materials_count', $progressItems->where('status', 'completed')->count());
            $program->setAttribute('total_materials_count', $materials->count());
            $program->setAttribute('program_thumbnail', $this->getProgramThumbnail($program));

            return $program;
        });
    }

    public function markMaterialStatus(Employee $employee, TrainingProgram $program, int $materialId, string $targetStatus): bool
    {
        $this->ensureEmployeeEnrollments($employee);

        $enrollment = TrainingProgramEnrollment::query()
            ->where('training_program_id', $program->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $progress = DB::table('training_material_progress')
            ->where('training_program_enrollment_id', $enrollment->id)
            ->where('training_material_id', $materialId)
            ->first();

        if (! $progress) {
            return false;
        }

        $program->loadMissing('materials');
        $progressItems = collect(DB::table('training_material_progress')->where('training_program_enrollment_id', $enrollment->id)->get());

        if ($targetStatus === 'in_progress' && $this->isMaterialLocked($program, $materialId, $progressItems)) {
            return false;
        }

        if ($targetStatus === 'completed') {
            if ($this->isMaterialLocked($program, $materialId, $progressItems)) {
                return false;
            }

            $material = $program->materials->firstWhere('id', $materialId);
            if (! $material || ! $this->canCompleteMaterial($material, (object) $progress)) {
                return false;
            }
        }

        $payload = [
            'status' => $targetStatus,
            'last_activity_at' => now(),
            'updated_at' => now(),
        ];

        if ($targetStatus === 'in_progress') {
            $payload['started_at'] = $progress->started_at ?: now();
            $payload['progress_percent'] = max((float) $progress->progress_percent, 15);
        }

        if ($targetStatus === 'completed') {
            $payload['completed_at'] = now();
            $payload['progress_percent'] = 100;
        }

        DB::table('training_material_progress')
            ->where('id', $progress->id)
            ->update($payload);

        $this->unlockNextMaterial($enrollment->id, $program, $materialId);
        $this->refreshEnrollmentSummary($enrollment->id);

        return true;
    }

    public function materialContentUrl($material): ?string
    {
        $contentUrl = trim((string) ($material->content_source_url ?? ''));
        if ($contentUrl !== '') {
            return $contentUrl;
        }

        $youtubeUrl = trim((string) ($material->youtube_url ?? ''));

        return $youtubeUrl !== '' ? $youtubeUrl : null;
    }

    public function materialContentLabel($material): string
    {
        $type = strtolower(trim((string) ($material->content_source_type ?? '')));

        return match ($type) {
            'youtube', 'video' => 'Buka Video Materi',
            'document' => 'Buka Dokumen Materi',
            'link' => 'Buka Link Materi',
            default => trim((string) ($material->youtube_url ?? '')) !== '' ? 'Buka Video Materi' : 'Buka Materi',
        };
    }

    public function storeAssessmentResult(Employee $employee, TrainingProgram $program, $material, string $purpose, TrainingFormAttempt $attempt): void
    {
        if (! $this->tableExists('training_material_progress')) {
            return;
        }

        $enrollment = TrainingProgramEnrollment::query()
            ->where('training_program_id', $program->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $enrollment) {
            return;
        }

        $score = $this->extractAttemptScore($attempt);
        $columnPrefix = $purpose === 'pretest' ? 'pretest' : 'posttest';

        DB::table('training_material_progress')
            ->where('training_program_enrollment_id', $enrollment->id)
            ->where('training_material_id', $material->id)
            ->update([
                $columnPrefix . '_score' => $score,
                $columnPrefix . '_attempt_id' => $attempt->id,
                'status' => DB::raw("CASE WHEN status = 'assigned' THEN 'in_progress' ELSE status END"),
                'started_at' => DB::raw('COALESCE(started_at, CURRENT_TIMESTAMP)'),
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);

        $this->refreshEnrollmentSummary($enrollment->id);
    }

    public function legacyParticipationSummary(Employee $employee): Collection
    {
        if (! $this->tableExists('training_participations')) {
            return collect();
        }

        return TrainingParticipation::query()
            ->with('material:id,title,category,duration_minutes')
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->get();
    }

    public function monitoringSummary(TrainingProgram $program): array
    {
        $program->loadMissing([
            'materials' => fn ($query) => $query->with(['mentor:id,name', 'pretestForm:id,name', 'posttestForm:id,name']),
        ]);

        $enrollments = $this->tableExists('training_program_enrollments')
            ? TrainingProgramEnrollment::query()
                ->with(['employee:id,full_name,department_id,position_id', 'employee.department:id,name', 'employee.position:id,name', 'lastMaterial:id,title', 'progressItems.material:id,title,pass_score'])
                ->where('training_program_id', $program->id)
                ->get()
            : collect();

        $participantRows = $enrollments->map(function (TrainingProgramEnrollment $enrollment) use ($program) {
            $progressItems = $enrollment->progressItems->keyBy('training_material_id');
            $orderedMaterials = $program->materials->sortBy('pivot.sequence_order')->values();
            $currentProgress = $orderedMaterials
                ->map(fn ($material) => $progressItems->get($material->id))
                ->first(fn ($progress) => in_array($progress?->status, ['assigned', 'in_progress', 'failed'], true));

            $enrollment->setAttribute('currentMaterial', $currentProgress?->material);
            $enrollment->setAttribute('completed_materials_count', $enrollment->progressItems->where('status', 'completed')->count());
            $enrollment->setAttribute('avg_pretest_score', round((float) $enrollment->progressItems->whereNotNull('pretest_score')->avg('pretest_score'), 2));
            $enrollment->setAttribute('avg_posttest_score', round((float) $enrollment->progressItems->whereNotNull('posttest_score')->avg('posttest_score'), 2));

            return $enrollment;
        });

        $materialRows = $program->materials
            ->sortBy('pivot.sequence_order')
            ->values()
            ->map(function ($material) use ($enrollments) {
                $progressRows = $enrollments
                    ->flatMap(function (TrainingProgramEnrollment $enrollment) use ($material) {
                        return $enrollment->progressItems
                            ->where('training_material_id', $material->id)
                            ->map(function ($progress) use ($enrollment) {
                                $progress->setRelation('employee', $enrollment->employee);

                                return $progress;
                            });
                    })
                    ->values();

                return [
                    'material' => $material,
                    'assigned' => $progressRows->whereIn('status', ['assigned', 'in_progress', 'completed', 'failed'])->count(),
                    'locked' => $progressRows->where('status', 'locked')->count(),
                    'in_progress' => $progressRows->where('status', 'in_progress')->count(),
                    'completed' => $progressRows->where('status', 'completed')->count(),
                    'failed' => $progressRows->where('status', 'failed')->count(),
                    'avg_pretest_score' => round((float) $progressRows->whereNotNull('pretest_score')->avg('pretest_score'), 2),
                    'avg_posttest_score' => round((float) $progressRows->whereNotNull('posttest_score')->avg('posttest_score'), 2),
                    'participants' => $progressRows,
                ];
            });

        return [
            'participants' => $participantRows,
            'materials' => $materialRows,
            'total_participants' => $participantRows->count(),
            'completed' => $participantRows->where('status', 'completed')->count(),
            'in_progress' => $participantRows->where('status', 'in_progress')->count(),
            'assigned' => $participantRows->where('status', 'assigned')->count(),
            'avg_pretest_score' => round((float) $participantRows->pluck('avg_pretest_score')->filter(fn ($score) => $score > 0)->avg(), 2),
            'avg_posttest_score' => round((float) $participantRows->pluck('avg_posttest_score')->filter(fn ($score) => $score > 0)->avg(), 2),
        ];
    }

    public function upcomingEventsForEmployee(Employee $employee): Collection
    {
        if (! $this->tableExists('training_event_participants') || ! $this->tableExists('training_events')) {
            return collect();
        }

        return TrainingEvent::query()
            ->with(['program:id,name', 'material:id,title', 'mentor:id,name', 'participants' => fn ($query) => $query->where('employee_id', $employee->id)])
            ->whereHas('participants', fn ($query) => $query->where('employee_id', $employee->id))
            ->whereIn('status', [TrainingEvent::STATUS_PUBLISHED, TrainingEvent::STATUS_STARTED])
            ->orderBy('starts_at')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ensure the given employee has enrollment rows for all programs they're
     * eligible for. Only syncs programs the employee is NOT yet enrolled in —
     * no bulk re-sync of all employees on every page load.
     */
    private function ensureEmployeeEnrollments(Employee $employee): void
    {
        if (
            ! $this->tableExists('training_programs')
            || ! $this->tableExists('training_program_enrollments')
            || ! $this->tableExists('training_program_material')
            || ! $this->tableExists('training_materials')
            || ! $this->tableExists('training_material_progress')
        ) {
            return;
        }

        $programs = TrainingProgram::query()->where('is_active', true)->get();

        foreach ($programs as $program) {
            if (! $this->employeeMatchesAudience($employee, $program)) {
                continue;
            }

            // Skip if already enrolled — avoids N+1 sync on every request
            $alreadyEnrolled = DB::table('training_program_enrollments')
                ->where('training_program_id', $program->id)
                ->where('employee_id', $employee->id)
                ->exists();

            if ($alreadyEnrolled) {
                continue;
            }

            $this->syncEnrollmentForEmployee($employee, $program);
        }
    }

    /**
     * Create enrollment + material progress rows for a single employee.
     * Used by ensureEmployeeEnrollments(); does NOT touch other employees.
     */
    private function syncEnrollmentForEmployee(Employee $employee, TrainingProgram $program): void
    {
        $program->loadMissing('materials');
        $materials = $program->materials->sortBy('pivot.sequence_order')->values();

        if ($materials->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($program, $employee, $materials): void {
            $existing = DB::table('training_program_enrollments')
                ->where('training_program_id', $program->id)
                ->where('employee_id', $employee->id)
                ->first();

            if (! $existing) {
                $enrollmentId = DB::table('training_program_enrollments')->insertGetId([
                    'training_program_id' => $program->id,
                    'employee_id'         => $employee->id,
                    'status'              => 'assigned',
                    'progress_percent'    => 0,
                    'assigned_by'         => auth()->user()?->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            } else {
                $enrollmentId = $existing->id;
            }

            foreach ($materials as $index => $material) {
                $status = $program->is_sequential
                    ? ($index === 0 ? 'assigned' : 'locked')
                    : 'assigned';

                DB::table('training_material_progress')->updateOrInsert(
                    [
                        'training_program_enrollment_id' => $enrollmentId,
                        'training_material_id'           => $material->id,
                    ],
                    [
                        'employee_id'         => $employee->id,
                        'training_program_id' => $program->id,
                        'status'              => $status,
                        'progress_percent'    => 0,
                        'updated_at'          => now(),
                        'created_at'          => now(),
                    ]
                );
            }
        });
    }

    private function eligibleEmployeesForProgram(TrainingProgram $program): Collection
    {
        if (! $this->tableExists('employees')) {
            return collect();
        }

        $query = Employee::query();

        // General programs are open to all statuses; targeted programs filter to active employees only
        if ($program->audience_scope !== 'general') {
            $query->whereIn('status_employment', ['probation', 'contract', 'permanent']);
        }

        if ($program->audience_scope === 'department' && $program->department_id) {
            $query->where('department_id', $program->department_id);
        }

        if ($program->audience_scope === 'position' && $program->position_id) {
            $query->where('position_id', $program->position_id);
        }

        return $query->get(['id', 'department_id', 'position_id', 'status_employment']);
    }

    private function employeeMatchesAudience(Employee $employee, TrainingProgram $program): bool
    {
        if ($program->audience_scope === 'general') {
            return true;
        }

        if ($program->audience_scope === 'department') {
            return (int) $employee->department_id === (int) $program->department_id;
        }

        if ($program->audience_scope === 'position') {
            return (int) $employee->position_id === (int) $program->position_id;
        }

        return false;
    }

    private function unlockNextMaterial(int $enrollmentId, TrainingProgram $program, int $completedMaterialId): void
    {
        if (! $program->is_sequential) {
            return;
        }

        $program->loadMissing('materials');
        $orderedIds = $program->materials->sortBy('pivot.sequence_order')->pluck('id')->values();
        $index = $orderedIds->search($completedMaterialId);
        if ($index === false) {
            return;
        }

        $nextId = $orderedIds->get($index + 1);
        if (! $nextId) {
            return;
        }

        DB::table('training_material_progress')
            ->where('training_program_enrollment_id', $enrollmentId)
            ->where('training_material_id', $nextId)
            ->where('status', 'locked')
            ->update([
                'status' => 'assigned',
                'updated_at' => now(),
            ]);
    }

    private function refreshEnrollmentSummary(int $enrollmentId): void
    {
        $enrollment = TrainingProgramEnrollment::query()->with('progressItems')->find($enrollmentId);
        if (! $enrollment) {
            return;
        }

        $progressItems = $enrollment->progressItems;
        $total = max(1, $progressItems->count());
        $completed = $progressItems->where('status', 'completed')->count();
        $started = $progressItems->whereIn('status', ['in_progress', 'completed'])->count();
        $percent = round(($completed / $total) * 100, 2);
        $status = $completed === $total ? 'completed' : ($started > 0 ? 'in_progress' : 'assigned');
        $lastMaterialId = optional($progressItems->sortByDesc('updated_at')->first())->training_material_id;

        $enrollment->update([
            'status' => $status,
            'progress_percent' => $percent,
            'last_training_material_id' => $lastMaterialId,
            'started_at' => $enrollment->started_at ?: ($started > 0 ? now() : null),
            'completed_at' => $status === 'completed' ? now() : null,
            'last_activity_at' => now(),
        ]);
    }

    private function isMaterialLocked(TrainingProgram $program, int $materialId, Collection $progressItems): bool
    {
        if (! $program->is_sequential) {
            return false;
        }

        $ordered = $program->materials->sortBy('pivot.sequence_order')->pluck('id')->values();
        $index = $ordered->search($materialId);
        if ($index === false || $index === 0) {
            return false;
        }

        $previousMaterialId = $ordered->get($index - 1);
        $previousProgress = $progressItems->firstWhere('training_material_id', $previousMaterialId);

        return ($previousProgress?->status ?? null) !== 'completed';
    }

    private function startBlockReason(TrainingProgram $program, $material, Collection $progressItems): ?string
    {
        if ($this->isMaterialLocked($program, $material->id, $progressItems)) {
            return 'Selesaikan materi sebelumnya terlebih dahulu agar materi ini terbuka.';
        }

        if ($this->materialContentUrl($material) === null && blank($material->description)) {
            return 'Materi ini belum memiliki konten/link yang bisa dibuka. Hubungi HRD untuk melengkapi materi.';
        }

        return null;
    }

    private function completeBlockReason(TrainingProgram $program, $material, Collection $progressItems): ?string
    {
        if ($this->isMaterialLocked($program, $material->id, $progressItems)) {
            return 'Materi masih terkunci karena urutan program belum terpenuhi.';
        }

        $progress = $progressItems->firstWhere('training_material_id', $material->id);
        if (! $progress) {
            return 'Progress materi belum siap. Muat ulang halaman lalu coba lagi.';
        }

        if ($material->pretest_form_id && ! $progress->pretest_attempt_id) {
            return 'Pretest wajib diselesaikan sebelum materi bisa ditandai selesai.';
        }

        if ($material->posttest_form_id && ! $progress->posttest_attempt_id) {
            return 'Posttest wajib diselesaikan sebelum materi bisa ditandai selesai.';
        }

        if ($material->posttest_form_id && ! $this->passesMaterialScore($material, (object) $progress)) {
            return 'Nilai posttest belum mencapai passing score materi.';
        }

        return null;
    }

    private function materialStatusSummary($progress): string
    {
        $status = (string) ($progress?->status ?? 'assigned');

        return match ($status) {
            'completed' => 'Materi, test, dan progres sudah lengkap.',
            'in_progress' => 'Materi sudah dibuka. Lanjutkan konten atau lengkapi test yang masih tersisa.',
            'locked' => 'Materi masih terkunci sampai urutan sebelumnya selesai.',
            default => 'Materi siap dibuka dan dimulai sesuai urutan program.',
        };
    }

    private function canCompleteMaterial($material, object $progress): bool
    {
        if ($material->pretest_form_id && ! $progress->pretest_attempt_id) {
            return false;
        }

        if ($material->posttest_form_id && ! $progress->posttest_attempt_id) {
            return false;
        }

        if ($material->posttest_form_id && ! $this->passesMaterialScore($material, $progress)) {
            return false;
        }

        return true;
    }

    private function passesMaterialScore($material, object $progress): bool
    {
        $passScore = (float) ($material->pass_score ?? 0);
        if ($passScore <= 0) {
            return true;
        }

        return (float) ($progress->posttest_score ?? 0) >= $passScore;
    }

    private function extractAttemptScore(TrainingFormAttempt $attempt): ?float
    {
        $computed = is_array($attempt->computed_result) ? $attempt->computed_result : [];
        $score = data_get($computed, 'score', data_get($computed, 'iq_score'));

        return $score !== null ? round((float) $score, 2) : null;
    }

    private function getProgramThumbnail(TrainingProgram $program): string
    {
        $firstMaterial = $program->materials->sortBy('pivot.sequence_order')->first();

        if (! $firstMaterial) {
            return asset('images/default-training.svg');
        }

        if (! empty($firstMaterial->thumbnail_path) && Storage::disk('public')->exists($firstMaterial->thumbnail_path)) {
            return asset('storage/' . $firstMaterial->thumbnail_path);
        }

        if (! empty($firstMaterial->youtube_url)) {
            $videoId = null;
            if (str_contains($firstMaterial->youtube_url, 'youtube.com/watch?v=')) {
                $videoId = Str::of($firstMaterial->youtube_url)->after('youtube.com/watch?v=')->before('&')->value();
            } elseif (str_contains($firstMaterial->youtube_url, 'youtu.be/')) {
                $videoId = Str::of($firstMaterial->youtube_url)->after('youtu.be/')->before('?')->value();
            }

            if ($videoId) {
                return "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
            }
        }

        return asset('images/default-training.svg');
    }
}
