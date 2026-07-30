<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\AssessmentForm;
use App\Models\Department;
use App\Models\Position;
use App\Models\TrainingMaterial;
use App\Models\TrainingProgram;
use App\Models\TrainingTrainer;
use App\Models\User;
use App\Services\LmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TrainingProgramController extends Controller
{
    public function __construct(private readonly LmsService $lmsService)
    {
    }

    public function index()
    {
        if (! $this->hasProgramSchema()) {
            return view('training_programs.index', [
                'programs' => $this->emptyPaginator(),
                'moduleWarning' => 'Modul Training Programs belum siap di environment ini. Jalankan migrasi LMS agar daftar program dapat dipakai penuh.',
                'schemaReady' => false,
            ]);
        }

        $programs = TrainingProgram::query()
            ->with(['mentor:id,name', 'department:id,name', 'position:id,name'])
            ->withCount(['materials', 'enrollments'])
            ->latest('id')
            ->paginate(12);

        return view('training_programs.index', [
            'programs' => $programs,
            'moduleWarning' => null,
            'schemaReady' => true,
        ]);
    }

    public function create()
    {
        if ($guard = $this->redirectWhenSchemaMissing()) {
            return $guard;
        }

        return view('training_programs.form', $this->formData(new TrainingProgram(), false));
    }

    public function store(Request $request)
    {
        if ($guard = $this->redirectWhenSchemaMissing()) {
            return $guard;
        }

        $data = $this->validateProgram($request);
        $program = TrainingProgram::create($data['program']);
        $this->syncMaterials($program, $data['materials']);
        $this->lmsService->syncProgramEnrollments($program->fresh('materials'), (int) ($request->user()?->id ?? 0));

        return redirect()->route('training-programs.show', $program)->with('success', 'Program training berhasil dibuat dan enrollment dasar sudah disiapkan.');
    }

    public function show(TrainingProgram $training_program)
    {
        if ($guard = $this->redirectWhenSchemaMissing()) {
            return $guard;
        }

        $training_program->load([
            'mentor:id,name',
            'department:id,name',
            'position:id,name',
            'materials' => fn ($query) => $query->with(['mentor:id,name', 'pretestForm:id,name', 'posttestForm:id,name']),
            'events' => fn ($query) => $query->withCount('participants')->orderByDesc('starts_at'),
        ]);

        $monitoring = $this->lmsService->monitoringSummary($training_program);

        return view('training_programs.show', [
            'program' => $training_program,
            'monitoring' => $monitoring,
        ]);
    }

    public function edit(TrainingProgram $training_program)
    {
        if ($guard = $this->redirectWhenSchemaMissing()) {
            return $guard;
        }

        $training_program->load('materials');

        return view('training_programs.form', $this->formData($training_program, true));
    }

    public function update(Request $request, TrainingProgram $training_program)
    {
        if ($guard = $this->redirectWhenSchemaMissing()) {
            return $guard;
        }

        $data = $this->validateProgram($request);
        $training_program->update($data['program']);
        $this->syncMaterials($training_program, $data['materials']);
        $this->lmsService->syncProgramEnrollments($training_program->fresh('materials'), (int) ($request->user()?->id ?? 0));

        return redirect()->route('training-programs.show', $training_program)->with('success', 'Program training berhasil diperbarui.');
    }

    public function destroy(TrainingProgram $training_program)
    {
        if ($guard = $this->redirectWhenSchemaMissing()) {
            return $guard;
        }

        $training_program->update(['is_active' => false]);

        return redirect()->route('training-programs.index')->with('success', 'Program training dinonaktifkan agar tidak mengganggu enrollment existing.');
    }

    private function formData(TrainingProgram $program, bool $isEdit): array
    {
        return [
            'program' => $program,
            'isEdit' => $isEdit,
            'materials' => TrainingMaterial::query()->where('is_active', true)->orderBy('title')->get(['id', 'title', 'category', 'duration_minutes']),
            'mentors' => $this->mentorOptions(),
            'departments' => Schema::hasTable('departments') ? Department::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'positions' => Schema::hasTable('positions') ? Position::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'linkedMaterials' => $program->relationLoaded('materials') ? $program->materials : collect(),
        ];
    }

    private function validateProgram(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'audience_scope' => ['required', Rule::in(['general', 'department', 'position'])],
            'department_id' => ['nullable', 'integer'],
            'position_id' => ['nullable', 'integer'],
            'mentor_user_id' => ['nullable', 'integer'],
            'is_sequential' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'materials' => ['nullable', 'array'],
            'materials.*.training_material_id' => ['nullable', 'integer'],
            'materials.*.sequence_order' => ['nullable', 'integer', 'min:1'],
            'materials.*.is_required' => ['nullable', 'boolean'],
            'materials.*.unlock_after_previous_completed' => ['nullable', 'boolean'],
        ]);

        return [
            'program' => [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'audience_scope' => $validated['audience_scope'],
                'department_id' => $validated['department_id'] ?? null,
                'position_id' => $validated['position_id'] ?? null,
                'mentor_user_id' => $validated['mentor_user_id'] ?? null,
                'is_sequential' => (bool) ($validated['is_sequential'] ?? true),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ],
            'materials' => collect($validated['materials'] ?? [])
                ->filter(fn ($row) => ! empty($row['training_material_id']))
                ->map(fn ($row, $index) => [
                    'training_material_id' => (int) $row['training_material_id'],
                    'sequence_order' => (int) ($row['sequence_order'] ?? ($index + 1)),
                    'is_required' => (bool) ($row['is_required'] ?? true),
                    'unlock_after_previous_completed' => (bool) ($row['unlock_after_previous_completed'] ?? true),
                ])
                ->sortBy('sequence_order')
                ->values()
                ->all(),
        ];
    }

    private function syncMaterials(TrainingProgram $program, array $materials): void
    {
        $sync = [];
        foreach ($materials as $row) {
            $material = TrainingMaterial::query()->find($row['training_material_id']);
            if (! $material) {
                continue;
            }

            $sync[$material->id] = [
                'sequence_order' => $row['sequence_order'],
                'is_required' => $row['is_required'],
                'unlock_after_previous_completed' => $row['unlock_after_previous_completed'],
                'estimated_minutes' => $material->duration_minutes,
                'metadata' => json_encode(['source' => 'program_builder']),
            ];
        }

        $program->materials()->sync($sync);
    }

    private function hasProgramSchema(): bool
    {
        foreach (['training_programs', 'training_program_material', 'training_program_enrollments'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function redirectWhenSchemaMissing(): ?RedirectResponse
    {
        if ($this->hasProgramSchema()) {
            return null;
        }

        return redirect()
            ->route('training-programs.index')
            ->with('error', 'Modul Training Programs belum siap di environment ini. Jalankan migrasi LMS terlebih dahulu.');
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 12, 1, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    private function mentorOptions()
    {
        $trainerUserIds = Schema::hasTable('training_trainers')
            ? TrainingTrainer::query()->active()->whereNotNull('user_id')->pluck('user_id')
            : collect();

        return User::query()
            ->where(function ($query) use ($trainerUserIds): void {
                $query->whereIn('role', [User::ROLE_HRD, User::ROLE_MANAGER, User::ROLE_ADMIN]);

                if ($trainerUserIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trainerUserIds->all());
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
