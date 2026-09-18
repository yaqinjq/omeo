<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyTaskController extends Controller
{
    public function index(): View
    {
        [$employeeId, $positionId] = $this->currentIdentity();

        $tasks = Task::with(['project:id,name'])
            ->withCount('subtasks')
            ->whereNull('parent_task_id')
            ->where(function ($q) use ($employeeId, $positionId) {
                $q->where('employee_id', $employeeId);
                if ($positionId) {
                    $q->orWhere(function ($qq) use ($positionId) {
                        $qq->where('assignment_type', 'position')->where('position_id', $positionId);
                    });
                }
            })
            ->orderByRaw('due_date IS NULL, due_date asc')
            ->get();

        $board = [
            'open'        => $tasks->where('status', Task::STATUS_OPEN)->values(),
            'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->values(),
            'done'        => $tasks->where('status', Task::STATUS_DONE)->values(),
        ];

        return view('tasks.my', ['board' => $board]);
    }

    /**
     * Detail 1 tugas + breakdown-nya, buat modal di sisi karyawan — sama
     * bentuknya dengan TaskController::detail(), tapi cuma boleh diakses
     * kalau tugas itu (atau salah satu leluhurnya) memang milik karyawan
     * ini, baik langsung maupun lewat posisi.
     */
    public function detail(Task $task): JsonResponse
    {
        [$employeeId, $positionId] = $this->currentIdentity();
        abort_unless($this->ownsChain($task, $employeeId, $positionId), 403);

        $task->loadCount('subtasks');

        $parent = $task->parent_task_id
            ? Task::select('id', 'title')->find($task->parent_task_id)
            : null;

        $subtasks = $task->subtasks()
            ->withCount('subtasks')
            ->with(['employee:id,full_name', 'position:id,name'])
            ->orderByRaw('due_date IS NULL, due_date asc')
            ->get()
            ->map(fn (Task $t) => [
                'id'             => $t->id,
                'title'          => $t->title,
                'status'         => $t->status,
                'subtasks_count' => $t->subtasks_count,
                'assignee'       => $t->assignment_type === 'position'
                    ? ($t->position?->name ? $t->position->name . ' (semua)' : '—')
                    : ($t->employee?->full_name ?? '—'),
            ]);

        return response()->json([
            'task' => [
                'id'          => $task->id,
                'title'       => $task->title,
                'description' => $task->description,
                'project_id'  => $task->project_id,
                'project_name'=> $task->project?->name,
                'parent_task_id' => $task->parent_task_id,
                'due_date'    => $task->due_date?->format('Y-m-d'),
                'priority'    => $task->priority,
                'status'      => $task->status,
                'is_root'     => $task->parent_task_id === null,
            ],
            'parent'   => $parent,
            'subtasks' => $subtasks,
        ]);
    }

    /**
     * Karyawan cuma boleh bikin SUB-TUGAS (breakdown pribadi) di bawah tugas
     * yang memang miliknya — tidak boleh bikin tugas root baru (itu tetap
     * wewenang admin/hrd/manager lewat papan utama). Sub-tugas otomatis
     * ditugaskan ke diri sendiri, tanpa perlu pilih assignee.
     */
    public function store(Request $request): JsonResponse
    {
        [$employeeId, $positionId] = $this->currentIdentity();

        $data = $request->validate([
            'parent_task_id' => 'required|exists:tasks,id',
            'title'          => 'required|string|max:200',
            'description'    => 'nullable|string|max:2000',
            'due_date'       => 'nullable|date',
            'priority'       => 'nullable|in:low,normal,high',
        ]);

        $parent = Task::findOrFail($data['parent_task_id']);
        abort_unless($this->ownsChain($parent, $employeeId, $positionId), 403);

        $task = Task::create([
            'parent_task_id'  => $parent->id,
            'project_id'      => $parent->project_id,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'assignment_type' => 'employee',
            'employee_id'     => $employeeId,
            'position_id'     => null,
            'due_date'        => $data['due_date'] ?? null,
            'priority'        => $data['priority'] ?? 'normal',
            'created_by'      => auth()->id(),
        ]);

        return response()->json(['message' => 'Sub-tugas berhasil dibuat.', 'id' => $task->id, 'parent_task_id' => $task->parent_task_id]);
    }

    /**
     * Karyawan cuma boleh edit sub-tugas (bukan tugas root) yang memang
     * bagian dari breakdown miliknya sendiri.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        [$employeeId, $positionId] = $this->currentIdentity();
        abort_unless($task->parent_task_id !== null && $this->ownsChain($task, $employeeId, $positionId), 403);

        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'due_date'    => 'nullable|date',
            'priority'    => 'nullable|in:low,normal,high',
        ]);

        $task->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date'    => $data['due_date'] ?? null,
            'priority'    => $data['priority'] ?? $task->priority,
        ]);

        return response()->json(['message' => 'Sub-tugas berhasil diupdate.', 'id' => $task->id, 'parent_task_id' => $task->parent_task_id]);
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        [$employeeId, $positionId] = $this->currentIdentity();
        abort_unless($this->ownsChain($task, $employeeId, $positionId), 403);

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

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function currentIdentity(): array
    {
        $employeeId = auth()->user()->employee_id;
        $positionId = auth()->user()->employee?->position_id;

        return [$employeeId, $positionId];
    }

    /**
     * Telusuri ke atas (tugas ini -> parent -> parent-nya parent, dst) —
     * kalau salah satu leluhurnya (atau tugas itu sendiri) ditugaskan
     * langsung ke karyawan ini atau ke posisinya, berarti karyawan ini
     * berhak lihat/kelola breakdown di cabang itu.
     */
    private function ownsChain(Task $task, ?int $employeeId, ?int $positionId): bool
    {
        $current = $task;

        while ($current) {
            if ($employeeId !== null && $current->employee_id === $employeeId) {
                return true;
            }
            if ($positionId !== null && $current->assignment_type === 'position' && $current->position_id === $positionId) {
                return true;
            }
            $current = $current->parent_task_id ? $current->parentTask : null;
        }

        return false;
    }
}
