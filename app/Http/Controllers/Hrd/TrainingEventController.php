<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingMaterial;
use App\Models\TrainingProgram;
use App\Models\TrainingTrainer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrainingEventController extends Controller
{
    public function index(): View
    {
        $events = TrainingEvent::query()
            ->with(['program:id,name', 'material:id,title', 'mentor:id,name'])
            ->withCount([
                'participants',
                'participants as registered_count' => fn ($query) => $query->whereIn('status', [
                    TrainingEventParticipant::STATUS_REGISTERED,
                    TrainingEventParticipant::STATUS_CHECKED_IN,
                    TrainingEventParticipant::STATUS_ATTENDED,
                ]),
                'participants as checked_in_count' => fn ($query) => $query->whereIn('status', [
                    TrainingEventParticipant::STATUS_CHECKED_IN,
                    TrainingEventParticipant::STATUS_ATTENDED,
                ]),
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(12);

        return view('training_events.index', compact('events'));
    }

    public function create(): View
    {
        return view('training_events.form', $this->formData(new TrainingEvent(), false));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $event = TrainingEvent::query()->create($this->eventPayload($validated));

        $this->inviteEmployees(
            $event,
            $this->employeeIdsForInvitation($validated),
            (int) ($request->user()?->id ?? 0)
        );

        return redirect()->route('training-events.show', $event)->with('success', 'Event training berhasil dibuat.');
    }

    public function show(TrainingEvent $training_event): View
    {
        $training_event->load([
            'program:id,name',
            'material:id,title',
            'mentor:id,name,email',
            'participants.employee:id,full_name,department_id,position_id',
            'participants.employee.department:id,name',
            'participants.employee.position:id,name',
            'participants.invitedBy:id,name',
            'participants.attendanceMarkedBy:id,name',
        ]);

        return view('training_events.show', [
            'event' => $training_event,
            'employees' => $this->employeeOptions(),
            'participantStatuses' => TrainingEventParticipant::STATUSES,
        ]);
    }

    public function edit(TrainingEvent $training_event): View
    {
        $training_event->load('participants');

        return view('training_events.form', $this->formData($training_event, true));
    }

    public function update(Request $request, TrainingEvent $training_event): RedirectResponse
    {
        $validated = $this->validated($request, $training_event);
        $training_event->update($this->eventPayload($validated));

        $this->inviteEmployees(
            $training_event,
            $this->employeeIdsForInvitation($validated),
            (int) ($request->user()?->id ?? 0)
        );

        return redirect()->route('training-events.show', $training_event)->with('success', 'Event training berhasil diperbarui.');
    }

    public function inviteParticipants(Request $request, TrainingEvent $training_event): RedirectResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $this->inviteEmployees(
            $training_event,
            collect($validated['employee_ids'])->map(fn ($id) => (int) $id),
            (int) ($request->user()?->id ?? 0)
        );

        return back()->with('success', 'Undangan peserta training berhasil ditambahkan.');
    }

    public function updateParticipant(Request $request, TrainingEvent $training_event, TrainingEventParticipant $participant): RedirectResponse
    {
        abort_unless((int) $participant->training_event_id === (int) $training_event->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(TrainingEventParticipant::STATUSES)],
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

        if ($validated['status'] === TrainingEventParticipant::STATUS_REGISTERED && ! $participant->registered_at) {
            $payload['registered_at'] = now();
        }

        $participant->update($payload);

        return back()->with('success', 'Status peserta berhasil diperbarui.');
    }

    private function formData(TrainingEvent $event, bool $isEdit): array
    {
        return [
            'event' => $event,
            'isEdit' => $isEdit,
            'programs' => TrainingProgram::query()->orderBy('name')->get(['id', 'name']),
            'materials' => TrainingMaterial::query()->orderBy('title')->get(['id', 'title']),
            'mentors' => $this->mentorOptions(),
            'employees' => $this->employeeOptions(),
            'invitedEmployeeIds' => $event->relationLoaded('participants')
                ? $event->participants->pluck('employee_id')->map(fn ($id) => (int) $id)->all()
                : [],
            'eventTypes' => TrainingEvent::TYPES,
            'eventStatuses' => TrainingEvent::STATUSES,
        ];
    }

    private function validated(Request $request, ?TrainingEvent $event = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'training_program_id' => ['nullable', 'exists:training_programs,id'],
            'training_material_id' => ['nullable', 'exists:training_materials,id'],
            'event_type' => ['required', Rule::in(TrainingEvent::TYPES)],
            'platform' => ['nullable', 'string', 'max:50'],
            'meeting_url' => ['nullable', 'required_if:event_type,meeting', 'string', 'max:2000'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'participant_instruction' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_deadline_at' => ['nullable', 'date'],
            'check_in_opens_at' => ['nullable', 'date'],
            'check_in_closes_at' => ['nullable', 'date', 'after_or_equal:check_in_opens_at'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mentor_user_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::in(TrainingEvent::STATUSES)],
            'requires_registration' => ['nullable', 'boolean'],
            'requires_photo_validation' => ['nullable', 'boolean'],
            'requires_geolocation' => ['nullable', 'boolean'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'auto_invite_program_enrollments' => ['nullable', 'boolean'],
        ]);
    }

    private function eventPayload(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'training_program_id' => $validated['training_program_id'] ?? null,
            'training_material_id' => $validated['training_material_id'] ?? null,
            'event_type' => $validated['event_type'],
            'platform' => $validated['platform'] ?? null,
            'meeting_url' => $validated['meeting_url'] ?? null,
            'location_name' => $validated['location_name'] ?? null,
            'location_address' => $validated['location_address'] ?? null,
            'participant_instruction' => $validated['participant_instruction'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'registration_deadline_at' => $validated['registration_deadline_at'] ?? null,
            'check_in_opens_at' => $validated['check_in_opens_at'] ?? null,
            'check_in_closes_at' => $validated['check_in_closes_at'] ?? null,
            'max_participants' => $validated['max_participants'] ?? null,
            'mentor_user_id' => $validated['mentor_user_id'] ?? null,
            'requires_registration' => (bool) ($validated['requires_registration'] ?? true),
            'requires_photo_validation' => (bool) ($validated['requires_photo_validation'] ?? false),
            'requires_geolocation' => (bool) ($validated['requires_geolocation'] ?? false),
            'status' => $validated['status'],
        ];
    }

    private function employeeIdsForInvitation(array $validated): Collection
    {
        $employeeIds = collect($validated['employee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();

        if (($validated['auto_invite_program_enrollments'] ?? false) && ! empty($validated['training_program_id'])) {
            $program = TrainingProgram::query()->with('enrollments:id,training_program_id,employee_id')->find($validated['training_program_id']);
            if ($program) {
                $employeeIds = $employeeIds->merge($program->enrollments->pluck('employee_id'));
            }
        }

        return $employeeIds->filter()->unique()->values();
    }

    private function inviteEmployees(TrainingEvent $event, Collection $employeeIds, int $actorId): void
    {
        foreach ($employeeIds as $employeeId) {
            $participant = TrainingEventParticipant::query()->firstOrNew([
                'training_event_id' => $event->id,
                'employee_id' => (int) $employeeId,
            ]);

            if (! $participant->exists) {
                $participant->status = TrainingEventParticipant::STATUS_INVITED;
            }

            $participant->fill([
                'invited_at' => $participant->invited_at ?: now(),
                'invited_by' => $actorId ?: null,
            ]);
            $participant->save();
        }
    }

    private function mentorOptions(): Collection
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
            ->get(['id', 'name', 'email', 'role']);
    }

    private function employeeOptions(): Collection
    {
        if (! Schema::hasTable('employees')) {
            return collect();
        }

        return Employee::query()
            ->with(['department:id,name', 'position:id,name'])
            ->whereIn('status_employment', ['probation', 'contract', 'permanent'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number', 'department_id', 'position_id', 'status_employment']);
    }
}
