<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingTrainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingEventController extends Controller
{
    public function index(Request $request): View
    {
        $trainer = $this->trainerFor($request);

        $events = TrainingEvent::query()
            ->with(['program:id,name', 'material:id,title'])
            ->withCount('participants')
            ->where('mentor_user_id', $trainer->user_id)
            ->orderByDesc('starts_at')
            ->paginate(12);

        return view('trainer.training_events.index', [
            'trainer' => $trainer,
            'events' => $events,
        ]);
    }

    public function show(Request $request, TrainingEvent $event): View
    {
        $trainer = $this->trainerFor($request);
        abort_unless((int) $event->mentor_user_id === (int) $trainer->user_id, 403);

        $event->load([
            'program:id,name',
            'material:id,title',
            'participants.employee:id,full_name,department_id,position_id',
            'participants.employee.department:id,name',
            'participants.employee.position:id,name',
        ]);

        return view('trainer.training_events.show', [
            'trainer' => $trainer,
            'event' => $event,
            'statuses' => TrainingEventParticipant::TRAINER_MANAGED_STATUSES,
        ]);
    }

    public function updateParticipant(Request $request, TrainingEvent $event, TrainingEventParticipant $participant): RedirectResponse
    {
        $trainer = $this->trainerFor($request);
        abort_unless((int) $event->mentor_user_id === (int) $trainer->user_id, 403);
        abort_unless((int) $participant->training_event_id === (int) $event->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', TrainingEventParticipant::TRAINER_MANAGED_STATUSES)],
            'attendance_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'status' => $validated['status'],
            'attendance_note' => $validated['attendance_note'] ?? null,
            'attendance_marked_by' => $request->user()?->id,
            'attendance_marked_at' => now(),
        ];

        if (in_array($validated['status'], [TrainingEventParticipant::STATUS_CHECKED_IN, TrainingEventParticipant::STATUS_ATTENDED], true)) {
            $payload['checked_in_at'] = $participant->checked_in_at ?: now();
        }

        $participant->update($payload);

        return back()->with('success', 'Absensi peserta berhasil diperbarui.');
    }

    private function trainerFor(Request $request): TrainingTrainer
    {
        $trainer = TrainingTrainer::query()
            ->with(['employee:id,full_name'])
            ->active()
            ->where('user_id', $request->user()?->id)
            ->first();

        abort_unless($trainer, 403);

        return $trainer;
    }
}
