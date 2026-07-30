<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use App\Models\Department;
use App\Models\Position;
use App\Models\TrainingMaterial;
use App\Models\TrainingTrainer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TrainingMaterialController extends Controller
{
    public function index()
    {
        $materials = TrainingMaterial::query()
            ->with(['mentor:id,name', 'pretestForm:id,name', 'posttestForm:id,name'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('training_materials.index', compact('materials'));
    }

    public function create()
    {
        return view('training_materials.create', $this->formData(new TrainingMaterial()));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        TrainingMaterial::create($data);

        return redirect()->route('training-materials.index')->with('success', 'Materi dibuat.');
    }

    public function edit(TrainingMaterial $training_material)
    {
        return view('training_materials.edit', $this->formData($training_material));
    }

    public function update(Request $request, TrainingMaterial $training_material)
    {
        $training_material->update($this->validatedData($request));

        return redirect()->route('training-materials.index')->with('success', 'Materi diupdate.');
    }

    public function destroy(TrainingMaterial $training_material)
    {
        $training_material->delete();
        return redirect()->route('training-materials.index')->with('success', 'Materi dihapus.');
    }

    public function show(TrainingMaterial $training_material)
    {
        $training_material->load(['mentor:id,name', 'department:id,name', 'position:id,name', 'pretestForm:id,name,type', 'posttestForm:id,name,type', 'programs:id,name']);

        return view('training_materials.show', ['material' => $training_material]);
    }

    private function formData(TrainingMaterial $material): array
    {
        return [
            'material' => $material,
            'mentors' => $this->mentorOptions(),
            'forms' => AssessmentForm::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'departments' => Schema::hasTable('departments') ? Department::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'positions' => Schema::hasTable('positions') ? Position::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'audience_scope' => ['required', Rule::in(['general', 'department', 'position'])],
            'department_id' => ['nullable', 'integer'],
            'position_id' => ['nullable', 'integer'],
            'mentor_user_id' => ['nullable', 'integer'],
            'pretest_form_id' => ['nullable', 'integer'],
            'posttest_form_id' => ['nullable', 'integer'],
            'content_source_type' => ['nullable', 'string', 'max:30'],
            'content_source_url' => ['nullable', 'string', 'max:2000'],
            'pass_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'youtube_url' => $validated['youtube_url'] ?? null,
            'category' => $validated['category'],
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'audience_scope' => $validated['audience_scope'],
            'department_id' => $validated['department_id'] ?? null,
            'position_id' => $validated['position_id'] ?? null,
            'mentor_user_id' => $validated['mentor_user_id'] ?? null,
            'pretest_form_id' => $validated['pretest_form_id'] ?? null,
            'posttest_form_id' => $validated['posttest_form_id'] ?? null,
            'content_source_type' => $validated['content_source_type'] ?? null,
            'content_source_url' => $validated['content_source_url'] ?? null,
            'pass_score' => $validated['pass_score'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
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
