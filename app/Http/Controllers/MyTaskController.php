<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyTaskController extends Controller
{
    public function index(): View
    {
        $employeeId = auth()->user()->employee_id;
        $positionId = auth()->user()->employee?->position_id;

        $tasks = Task::with(['project:id,name'])
            ->where(function ($q) use ($employeeId, $positionId) {
                $q->where('employee_id', $employeeId);
                if ($positionId) {
                    $q->orWhere(function ($qq) use ($positionId) {
                        $qq->where('assignment_type', 'position')->where('position_id', $positionId);
                    });
                }
            })
            ->orderByRaw("due_date IS NULL, due_date asc")
            ->get();

        return view('tasks.my', [
            'myTasks'       => $tasks->where('assignment_type', 'employee')->values(),
            'standingTasks' => $tasks->where('assignment_type', 'position')->values(),
        ]);
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $employeeId = auth()->user()->employee_id;
        $positionId = auth()->user()->employee?->position_id;

        $isMine = $task->employee_id === $employeeId
            || ($task->assignment_type === 'position' && $task->position_id === $positionId);

        abort_unless($isMine, 403);

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,done',
        ]);

        $task->update([
            'status'       => $data['status'],
            'completed_at' => $data['status'] === Task::STATUS_DONE ? now() : null,
        ]);

        return back()->with('success', 'Status tugas diperbarui.');
    }
}
