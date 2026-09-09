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
        $query = Task::with(['project:id,name', 'position:id,name', 'employee:id,full_name', 'creator:id,name'])
            ->withCount('subtasks')
            ->whereNull('parent_task_id');

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

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $this->validated($request);

        $task = Task::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tugas berhasil dibuat.', 'id' => $task->id, 'parent_task_id' => $task->parent_task_id]);
        }

        return back()->with('success', 'Tugas berhasil dibuat.');
    }

    public function update(Request $request, Task $task): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $this->validated($request);

        $task->update($data);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tugas berhasil diupdate.', 'id' => $task->id, 'parent_task_id' => $task->parent_task_id]);
        }

        return back()->with('success', 'Tugas berhasil diupdate.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $descendantIds = $task->allDescendantIds();
        if ($descendantIds) {
            Task::whereIn('id', $descendantIds)->delete();
        }

        $task->delete();

        $message = $descendantIds
            ? 'Tugas beserta ' . count($descendantIds) . ' sub-tugasnya berhasil dihapus.'
            : 'Tugas berhasil dihapus.';

        return back()->with('success', $message);
    }

    /**
     * Detail lengkap 1 tugas (buat isi form edit) + breakdown langsung
     * (anak-anaknya) + info parent-nya (buat breadcrumb "← Kembali") —
     * dipanggil via fetch setiap kali modal edit dibuka, TERMASUK saat
     * "masuk" ke sub-tugas (panggil ulang endpoint ini untuk sub-tugas itu)
     * — jadi kedalaman breakdown tidak dibatasi sama sekali.
     */
    public function detail(Task $task): \Illuminate\Http\JsonResponse
    {
        $task->loadCount('subtasks');

        $parent = $task->parent_task_id
            ? Task::select('id', 'title')->find($task->parent_task_id)
            : null;

        $subtasks = $task->subtasks()
            ->withCount('subtasks')
            ->with(['employee:id,full_name', 'position:id,name'])
            ->orderByRaw("due_date IS NULL, due_date asc")
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'subtasks_count' => $t->subtasks_count,
                'assignee' => $t->assignment_type === 'position'
                    ? ($t->position?->name ? $t->position->name . ' (semua)' : '—')
                    : ($t->employee?->full_name ?? '—'),
            ]);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'project_id' => $task->project_id,
                'parent_task_id' => $task->parent_task_id,
                'assignment_type' => $task->assignment_type,
                'position_id' => $task->position_id,
                'employee_id' => $task->employee_id,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'priority' => $task->priority,
                'status' => $task->status,
            ],
            'parent' => $parent,
            'subtasks' => $subtasks,
        ]);
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
            'parent_task_id'  => 'nullable|exists:tasks,id',
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
