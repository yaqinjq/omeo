<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\CareerDepartment;
use App\Models\CareerPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CareerPostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = CareerPost::query()
            ->with('department')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('hrd.careers.index', [
            'posts' => $posts,
            'statuses' => CareerPost::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('hrd.careers.form', [
            'post' => new CareerPost([
                'status' => CareerPost::STATUS_DRAFT,
                'employment_type' => 'full-time',
                'apply_button_label' => 'Lamar Posisi',
            ]),
            'departments' => CareerDepartment::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($this->slugSource($data));
        $data['career_department_id'] = $this->resolveDepartmentId($data);
        unset($data['department_name']);

        CareerPost::query()->create($data);

        return redirect()->route('dashboard.careers.index')->with('success', 'Lowongan berhasil dibuat.');
    }

    public function edit(CareerPost $career): View
    {
        return view('hrd.careers.form', [
            'post' => $career,
            'departments' => CareerDepartment::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CareerPost $career): RedirectResponse
    {
        $data = $this->validated($request, $career);
        $data['slug'] = $this->uniqueSlug($this->slugSource($data), $career->id);
        $data['career_department_id'] = $this->resolveDepartmentId($data);
        unset($data['department_name']);

        $career->update($data);

        return redirect()->route('dashboard.careers.index')->with('success', 'Lowongan berhasil diperbarui.');
    }

    public function destroy(CareerPost $career): RedirectResponse
    {
        $career->delete();

        return back()->with('success', 'Lowongan berhasil dihapus.');
    }

    private function validated(Request $request, ?CareerPost $post = null): array
    {
        return $request->validate([
            'department_name' => ['required_without:career_department_id', 'nullable', 'string', 'max:120'],
            'career_department_id' => ['nullable', 'exists:career_departments,id'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('career_posts', 'slug')->ignore($post?->id)],
            'location' => ['nullable', 'string', 'max:160'],
            'employment_type' => ['required', Rule::in(CareerPost::EMPLOYMENT_TYPES)],
            'description' => ['nullable', 'string'],
            'qualifications' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'status' => ['required', Rule::in(CareerPost::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'closing_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'apply_button_label' => ['nullable', 'string', 'max:80'],
            'apply_url' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function resolveDepartmentId(array $data): ?int
    {
        if (! empty($data['career_department_id'])) {
            return (int) $data['career_department_id'];
        }

        $name = trim((string) ($data['department_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $department = CareerDepartment::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true]
        );

        return $department->id;
    }

    private function slugSource(array $data): string
    {
        return filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $data['title'];
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'lowongan';
        $slug = $base;
        $counter = 2;

        while (CareerPost::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
