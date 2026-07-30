<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssessmentFormRequest;
use App\Http\Requests\StoreFormOptionRequest;
use App\Http\Requests\StoreFormQuestionRequest;
use App\Http\Requests\UpdateAssessmentFormRequest;
use App\Http\Requests\UpdateFormOptionRequest;
use App\Http\Requests\UpdateFormQuestionRequest;
use App\Models\AssessmentForm;
use App\Models\FormOption;
use App\Models\FormQuestion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormBuilderController extends Controller
{
    public function index()
    {
        if (! $this->hasBuilderSchema()) {
            return view('hrd.forms.index', [
                'forms' => $this->emptyPaginator(),
                'builderTypes' => AssessmentForm::builderTypes(),
                'importableChoiceTypes' => AssessmentForm::importableChoiceTypes(),
                'moduleWarning' => 'Schema Form Dinamis belum lengkap di environment ini. Jalankan migrasi forms/form_questions/form_options agar builder bisa dipakai penuh.',
                'schemaReady' => false,
            ]);
        }

        $forms = AssessmentForm::query()
            ->withCount('questions')
            ->orderByDesc('id')
            ->paginate(12);

        return view('hrd.forms.index', [
            'forms' => $forms,
            'builderTypes' => AssessmentForm::builderTypes(),
            'importableChoiceTypes' => AssessmentForm::importableChoiceTypes(),
            'moduleWarning' => null,
            'schemaReady' => true,
        ]);
    }

    public function create()
    {
        return view('hrd.forms.create', [
            'builderTypes' => AssessmentForm::builderTypes(),
            'moduleWarning' => $this->hasBuilderSchema() ? null : 'Schema Form Dinamis belum lengkap di environment ini. Halaman ini dibuka dalam mode aman.',
            'schemaReady' => $this->hasBuilderSchema(),
        ]);
    }

    public function store(StoreAssessmentFormRequest $request)
    {
        $this->ensureBuilderSchemaReady();

        $data = $request->validated();
        $data['code'] = 'FORM-' . Str::upper(Str::random(8));
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $form = AssessmentForm::create($data);

        return redirect()->route('hrd.forms.edit', $form)->with('success', 'Form berhasil dibuat.');
    }

    public function edit(AssessmentForm $form)
    {
        $this->ensureBuilderSchemaReady();

        $form->load(['questions.options']);

        return view('hrd.forms.edit', [
            'form' => $form,
            'builderTypes' => AssessmentForm::builderTypes(),
            'discAxisOptions' => ['D', 'I', 'S', 'C'],
        ]);
    }

    public function update(UpdateAssessmentFormRequest $request, AssessmentForm $form)
    {
        $this->ensureBuilderSchemaReady();

        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $form->update($data);

        return back()->with('success', 'Form berhasil diperbarui.');
    }

    public function toggle(AssessmentForm $form)
    {
        $this->ensureBuilderSchemaReady();

        $form->update(['is_active' => ! $form->is_active]);
        return back()->with('success', 'Status form berhasil diperbarui.');
    }

    public function storeQuestion(StoreFormQuestionRequest $request, AssessmentForm $form)
    {
        $this->ensureBuilderSchemaReady();

        $data = $request->validated();
        $this->assertQuestionTypeAllowed($form, $data['question_type']);

        $settings = $this->buildSettings($data['question_type'], $data['settings'] ?? []);
        $options = collect($data['options'] ?? [])
            ->map(fn ($option) => [
                'option_text' => trim((string) ($option['option_text'] ?? '')),
                'value' => $option['value'] ?? null,
                'weight' => $option['weight'] ?? null,
                'meta' => $option['meta'] ?? [],
            ])
            ->filter(fn ($option) => $option['option_text'] !== '')
            ->values();

        if ($this->isChoiceType($data['question_type']) && $options->count() < 2) {
            throw ValidationException::withMessages([
                'options' => 'Pertanyaan pilihan wajib memiliki minimal 2 opsi.',
            ]);
        }

        $imagePath = $request->hasFile('question_image')
            ? $request->file('question_image')->store('forms/questions', 'public')
            : null;

        DB::transaction(function () use ($form, $data, $settings, $options, $imagePath): void {
            $max = (int) $form->questions()->max('position');

            $question = $form->questions()->create([
                'position' => $max + 1,
                'question_text' => trim($data['question_text']),
                'question_image_path' => $imagePath,
                'question_type' => $data['question_type'],
                'is_required' => (bool) ($data['is_required'] ?? false),
                'settings' => $settings,
            ]);

            foreach ($options as $index => $optionData) {
                $payload = $this->prepareOptionPayload($form, $question, $optionData);

                $question->options()->create([
                    'position' => $index + 1,
                    'option_text' => $payload['option_text'],
                    'value' => $payload['value'],
                    'weight' => $payload['weight'],
                    'meta' => $payload['meta'],
                ]);
            }
        });

        return back()->with('success', 'Pertanyaan berhasil ditambahkan.');
    }

    public function updateQuestion(UpdateFormQuestionRequest $request, AssessmentForm $form, FormQuestion $question)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id, 404);

        $data = $request->validated();
        $this->assertQuestionTypeAllowed($form, $data['question_type']);

        $options = collect($data['options'] ?? [])
            ->map(fn ($option) => [
                'id' => isset($option['id']) ? (int) $option['id'] : null,
                'option_text' => trim((string) ($option['option_text'] ?? '')),
                'value' => $option['value'] ?? null,
                'weight' => $option['weight'] ?? null,
                'meta' => $option['meta'] ?? [],
            ])
            ->filter(fn ($option) => $option['option_text'] !== '')
            ->values();

        if ($this->isChoiceType($data['question_type']) && $options->count() < 2) {
            throw ValidationException::withMessages([
                'options' => 'Pertanyaan pilihan wajib memiliki minimal 2 opsi.',
            ]);
        }

        $imagePath = $question->question_image_path;

        if (! empty($data['remove_question_image']) && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        if ($request->hasFile('question_image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('question_image')->store('forms/questions', 'public');
        }

        DB::transaction(function () use ($question, $data, $imagePath, $form, $options): void {
            $question->update([
                'question_text' => trim($data['question_text']),
                'question_image_path' => $imagePath,
                'question_type' => $data['question_type'],
                'is_required' => (bool) ($data['is_required'] ?? false),
                'settings' => $this->buildSettings($data['question_type'], $data['settings'] ?? []),
            ]);

            if ($this->isChoiceType($data['question_type'])) {
                $this->syncChoiceOptions($form, $question, $options);
                return;
            }

            $question->options()->delete();
        });

        return back()->with('success', 'Pertanyaan berhasil diperbarui.');
    }

    public function duplicateQuestion(AssessmentForm $form, FormQuestion $question)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id, 404);

        DB::transaction(function () use ($form, $question): void {
            $max = (int) $form->questions()->max('position');
            $copiedImagePath = null;

            if ($question->question_image_path && Storage::disk('public')->exists($question->question_image_path)) {
                $ext = pathinfo($question->question_image_path, PATHINFO_EXTENSION) ?: 'jpg';
                $copiedImagePath = 'forms/questions/' . Str::uuid() . '.' . $ext;
                Storage::disk('public')->copy($question->question_image_path, $copiedImagePath);
            }

            $duplicate = $question->replicate(['position']);
            $duplicate->form_id = $form->id;
            $duplicate->position = $max + 1;
            $duplicate->question_text = trim($question->question_text . ' (Copy)');
            $duplicate->question_image_path = $copiedImagePath;
            $duplicate->save();

            foreach ($question->options as $index => $option) {
                $duplicate->options()->create([
                    'position' => $index + 1,
                    'option_text' => $option->option_text,
                    'value' => $option->value,
                    'weight' => $option->weight,
                    'meta' => $option->meta,
                ]);
            }
        });

        return back()->with('success', 'Pertanyaan berhasil diduplikasi.');
    }

    public function moveQuestion(Request $request, AssessmentForm $form, FormQuestion $question)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id, 404);
        $direction = $request->validate(['direction' => 'required|in:up,down'])['direction'];

        DB::transaction(function () use ($form, $question, $direction): void {
            $target = $form->questions()
                ->where('position', $direction === 'up' ? '<' : '>', $question->position)
                ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
                ->first();

            if (! $target) {
                return;
            }

            $oldPosition = $question->position;
            $question->update(['position' => $target->position]);
            $target->update(['position' => $oldPosition]);
        });

        return back()->with('success', 'Urutan pertanyaan diperbarui.');
    }

    public function destroyQuestion(AssessmentForm $form, FormQuestion $question)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id, 404);

        if ($question->question_image_path) {
            $inUseByOthers = FormQuestion::query()
                ->where('question_image_path', $question->question_image_path)
                ->where('id', '<>', $question->id)
                ->exists();

            if (! $inUseByOthers) {
                Storage::disk('public')->delete($question->question_image_path);
            }
        }

        $question->delete();
        return back()->with('success', 'Pertanyaan dihapus.');
    }

    public function storeOption(StoreFormOptionRequest $request, AssessmentForm $form, FormQuestion $question)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id, 404);
        $this->assertChoiceQuestion($question);

        $payload = $this->prepareOptionPayload($form, $question, $request->validated());
        $max = (int) $question->options()->max('position');

        $question->options()->create([
            'position' => $max + 1,
            'option_text' => $payload['option_text'],
            'value' => $payload['value'],
            'weight' => $payload['weight'],
            'meta' => $payload['meta'],
        ]);

        return back()->with('success', 'Opsi berhasil ditambahkan.');
    }

    public function updateOption(UpdateFormOptionRequest $request, AssessmentForm $form, FormQuestion $question, FormOption $option)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id && $option->question_id === $question->id, 404);
        $this->assertChoiceQuestion($question);

        $payload = $this->prepareOptionPayload($form, $question, $request->validated());

        $option->update([
            'option_text' => $payload['option_text'],
            'value' => $payload['value'],
            'weight' => $payload['weight'],
            'meta' => $payload['meta'],
        ]);

        return back()->with('success', 'Opsi berhasil diperbarui.');
    }

    public function moveOption(Request $request, AssessmentForm $form, FormQuestion $question, FormOption $option)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id && $option->question_id === $question->id, 404);
        $direction = $request->validate(['direction' => 'required|in:up,down'])['direction'];

        DB::transaction(function () use ($question, $option, $direction): void {
            $target = $question->options()
                ->where('position', $direction === 'up' ? '<' : '>', $option->position)
                ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
                ->first();

            if (! $target) {
                return;
            }

            $oldPosition = $option->position;
            $option->update(['position' => $target->position]);
            $target->update(['position' => $oldPosition]);
        });

        return back()->with('success', 'Urutan opsi diperbarui.');
    }

    public function destroyOption(AssessmentForm $form, FormQuestion $question, FormOption $option)
    {
        $this->ensureBuilderSchemaReady();
        abort_unless($question->form_id === $form->id && $option->question_id === $question->id, 404);

        if ($this->isChoiceType($question->question_type) && $question->options()->count() <= 2) {
            throw ValidationException::withMessages([
                'option' => 'Pertanyaan pilihan wajib menyisakan minimal 2 opsi.',
            ]);
        }

        $option->delete();
        return back()->with('success', 'Opsi dihapus.');
    }

    private function buildSettings(string $type, array $settings): ?array
    {
        $payload = [];

        if (in_array($type, [FormQuestion::TYPE_RATING, FormQuestion::TYPE_LINEAR_SCALE], true)) {
            $min = (int) ($settings['min'] ?? 1);
            $max = (int) ($settings['max'] ?? 5);
            if ($max < $min) {
                $max = $min;
            }

            $payload = [
                'min' => $min,
                'max' => $max,
                'min_label' => $settings['min_label'] ?? null,
                'max_label' => $settings['max_label'] ?? null,
            ];
        }

        if (array_key_exists('disc_mode', $settings) && $settings['disc_mode']) {
            $payload['disc_mode'] = in_array($settings['disc_mode'], ['single_axis', 'dual_axis'], true)
                ? $settings['disc_mode']
                : 'dual_axis';
        }

        foreach (['media_title', 'media_url', 'youtube_url', 'answer_accept'] as $key) {
            $value = trim((string) ($settings[$key] ?? ''));
            if ($value !== '') {
                $payload[$key] = $value;
            }
        }

        if (isset($settings['answer_max_kb']) && $settings['answer_max_kb'] !== '') {
            $payload['answer_max_kb'] = max(64, (int) $settings['answer_max_kb']);
        }

        return $payload === [] ? null : $payload;
    }

    private function allowedQuestionTypes(AssessmentForm $form): array
    {
        if ($form->type === AssessmentForm::TYPE_DISC) {
            return [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN];
        }

        return FormQuestion::allTypes();
    }

    private function assertQuestionTypeAllowed(AssessmentForm $form, string $questionType): void
    {
        if (! in_array($questionType, $this->allowedQuestionTypes($form), true)) {
            throw ValidationException::withMessages([
                'question_type' => 'Tipe pertanyaan tidak diizinkan untuk jenis form ini.',
            ]);
        }
    }

    private function isChoiceType(string $type): bool
    {
        return in_array($type, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_CHECKBOX, FormQuestion::TYPE_DROPDOWN], true);
    }

    private function assertChoiceQuestion(FormQuestion $question): void
    {
        if (! $this->isChoiceType($question->question_type)) {
            throw ValidationException::withMessages([
                'option_text' => 'Opsi hanya bisa dikelola pada pertanyaan bertipe pilihan.',
            ]);
        }
    }

    private function prepareOptionPayload(AssessmentForm $form, FormQuestion $question, array $data): array
    {
        $optionText = trim((string) ($data['option_text'] ?? ''));
        if ($optionText === '') {
            throw ValidationException::withMessages([
                'option_text' => 'Teks opsi wajib diisi.',
            ]);
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if ($form->type === AssessmentForm::TYPE_DISC) {
            $axisMost = strtoupper(trim((string) data_get($meta, 'disc_axis_most', data_get($meta, 'disc_axis'))));
            $axisLeast = strtoupper(trim((string) data_get($meta, 'disc_axis_least', data_get($meta, 'disc_axis'))));

            if (! in_array($axisMost, ['D', 'I', 'S', 'C'], true) || ! in_array($axisLeast, ['D', 'I', 'S', 'C'], true)) {
                throw ValidationException::withMessages([
                    'meta.disc_axis_most' => 'Setiap opsi DISC wajib punya axis Most dan Least berupa D/I/S/C.',
                ]);
            }

            return [
                'option_text' => $optionText,
                'value' => $data['value'] ?? null,
                'weight' => null,
                'meta' => [
                    'disc_axis' => $axisMost,
                    'disc_axis_most' => $axisMost,
                    'disc_axis_least' => $axisLeast,
                ],
            ];
        }

        $payloadMeta = [];
        if (array_key_exists('is_correct', $meta)) {
            $payloadMeta['is_correct'] = (bool) $meta['is_correct'];
        }

        return [
            'option_text' => $optionText,
            'value' => $data['value'] ?? null,
            'weight' => isset($data['weight']) && $data['weight'] !== '' ? (int) $data['weight'] : null,
            'meta' => $payloadMeta === [] ? null : $payloadMeta,
        ];
    }

    private function syncChoiceOptions(AssessmentForm $form, FormQuestion $question, Collection $options): void
    {
        $existing = $question->options()->get()->keyBy('id');
        $keepIds = [];

        foreach ($options as $index => $optionData) {
            $payload = $this->prepareOptionPayload($form, $question, $optionData);
            $position = $index + 1;
            $optionId = (int) ($optionData['id'] ?? 0);

            if ($optionId > 0 && ! $existing->has($optionId)) {
                throw ValidationException::withMessages([
                    'options' => 'Data opsi tidak valid untuk pertanyaan ini.',
                ]);
            }

            if ($optionId > 0 && $existing->has($optionId)) {
                $option = $existing->get($optionId);
                $option->update([
                    'position' => $position,
                    'option_text' => $payload['option_text'],
                    'value' => $payload['value'],
                    'weight' => $payload['weight'],
                    'meta' => $payload['meta'],
                ]);
                $keepIds[] = $option->id;
                continue;
            }

            $created = $question->options()->create([
                'position' => $position,
                'option_text' => $payload['option_text'],
                'value' => $payload['value'],
                'weight' => $payload['weight'],
                'meta' => $payload['meta'],
            ]);
            $keepIds[] = $created->id;
        }

        $question->options()
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function hasBuilderSchema(): bool
    {
        foreach (['forms', 'form_questions', 'form_options'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function ensureBuilderSchemaReady(): void
    {
        abort_unless($this->hasBuilderSchema(), 503, 'Schema Form Dinamis belum lengkap di environment ini.');
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 12, 1, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    /**
     * @return Collection<int,Department>
     */
    private function loadDepartments(): Collection
    {
        if (! Schema::hasTable('departments')) {
            return collect();
        }

        return Department::query()->orderBy('name')->get(['id', 'name']);
    }
}
