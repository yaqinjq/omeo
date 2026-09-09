<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $query = Task::with(['project:id,name', 'position:id,name', 'employee:id,full_name', 'creator:id,name']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('position_id')) {
            $query->where('position_id', $request->integer('position_id'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        $tasks = $query->orderByRaw("due_date IS NULL, due_date asc")->get();

        $board = [
            'open'        => $tasks->where('status', Task::STATUS_OPEN)->values(),
            'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->values(),
            'done'        => $tasks->where('status', Task::STATUS_DONE)->values(),
        ];

        return view('tasks.index', [
            'board'             => $board,
            'projects'          => Project::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'allProjects'       => Project::withCount('tasks')->orderByDesc('created_at')->get(),
            'positionOptions'   => Position::orderBy('name')->get(['id', 'name']),
            'employeeOptions'   => Employee::whereNotIn('status_employment', ['resigned', 'terminated'])->orderBy('full_name')->get(['id', 'full_name']),
            'filters'           => $request->only(['project_id', 'position_id', 'employee_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Task::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Tugas berhasil dibuat.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $this->validated($request);

        $task->update($data);

        return back()->with('success', 'Tugas berhasil diupdate.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', 'Tugas berhasil dihapus.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,done',
        ]);

        $task->update([
            'status'       => $data['status'],
            'completed_at' => $data['status'] === Task::STATUS_DONE ? now() : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Status tugas diperbarui.']);
        }

        return back()->with('success', 'Status tugas diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id'      => 'nullable|exists:projects,id',
            'title'           => 'required|string|max:200',
            'description'     => 'nullable|string|max:2000',
            'assignment_type' => 'required|in:position,employee',
            'position_id'     => 'nullable|required_if:assignment_type,position|exists:positions,id',
            'employee_id'     => 'nullable|required_if:assignment_type,employee|exists:employees,id',
            'due_date'        => 'nullable|date',
            'priority'        => 'nullable|in:low,normal,high',
        ]);

        $data['position_id'] = $data['assignment_type'] === 'position' ? $data['position_id'] : null;
        $data['employee_id'] = $data['assignment_type'] === 'employee' ? $data['employee_id'] : null;
        $data['priority']    = $data['priority'] ?? 'normal';

        return $data;
    }
}
