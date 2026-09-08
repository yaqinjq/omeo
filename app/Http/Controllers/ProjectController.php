<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::with(['department', 'creator'])
            ->withCount('tasks')
            ->orderByDesc('created_at')
            ->get();

        $departments = Department::orderBy('name')->get(['id', 'name']);

        return view('projects.index', compact('projects', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Project::create([
            ...$data,
            'created_by' => auth()->id(),
            'status'     => 'active',
        ]);

        return back()->with('success', 'Project berhasil dibuat.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = $request->input('status', $project->status);

        $project->update($data);

        return back()->with('success', 'Project berhasil diupdate.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->tasks()->count() > 0) {
            return back()->with('error', 'Project ini masih punya tugas — pindahkan atau hapus tugasnya dulu.');
        }

        $project->delete();

        return back()->with('success', 'Project berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string|max:2000',
            'department_id' => 'nullable|exists:departments,id',
        ]);
    }
}
