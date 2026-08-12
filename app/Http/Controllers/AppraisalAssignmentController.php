<?php

namespace App\Http\Controllers;

use App\Models\Appraisal;
use App\Models\AppraisalCriteriaTemplate;
use App\Models\AppraisalInvitationLog;
use App\Models\AppraisalPeriod;
use App\Models\Employee;
use App\Models\HrNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppraisalAssignmentController extends Controller
{
    public function __construct(
        private readonly \App\Services\Notifications\UnifiedNotificationService $unifiedNotificationService,
    ) {
    }

    /**
     * Sama seperti AppraisalController::sendAppraisalEmail() — $user=null
     * supaya hanya kanal email yang jalan, notifikasi internal tetap lewat
     * kode HrNotification yang sudah ada di notifyAppraiser() di bawah.
     */
    private function sendAppraisalEmail(string $eventKey, ?int $userId, array $variables): void
    {
        if (! $userId) {
            return;
        }

        $user = User::find($userId);
        $email = trim((string) ($user?->email ?: $user?->employee?->email_private ?: ''));
        if ($email === '') {
            return;
        }

        $variables['name'] ??= $user->name;

        $this->unifiedNotificationService->dispatch(
            eventKey: $eventKey,
            user: null,
            email: $email,
            whatsappNumber: '',
            payload: ['variables' => $variables]
        );
    }

    public function index(Request $request)
    {
        $mode       = in_array($request->get('mode'), ['probation', 'manual'], true) ? $request->get('mode') : 'probation';
        $windowDays = max(1, min(90, (int) $request->get('days', 30)));

        if (! Schema::hasTable('employees') || ! Schema::hasTable('users') || ! Schema::hasTable('appraisal_periods')) {
            return view('appraisals.assignment.index', [
                'mode'               => $mode,
                'windowDays'         => $windowDays,
                'periods'            => collect(),
                'candidateEmployees' => collect(),
                'defaultPeriodId'    => 0,
                'moduleWarning'      => 'Modul assignment appraisal belum siap sepenuhnya di environment ini.',
            ]);
        }

        $periods = AppraisalPeriod::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        $activePeriod = $periods->first(function (AppraisalPeriod $p): bool {
            return optional($p->start_date)?->lte(Carbon::today())
                && optional($p->end_date)?->gte(Carbon::today());
        });

        $defaultPeriodId = (int) ($activePeriod?->id ?? $periods->first()?->id ?? 0);

        $candidateEmployees = collect();
        $grouped = [
            'safe'    => collect(),
            'urgent'  => collect(),
            'overdue' => collect(),
        ];

        if ($mode === 'probation') {
            $cutoff = Carbon::today()->addDays($windowDays);
            $floor  = Carbon::today()->subDays(7);

            $candidateEmployees = Employee::query()
                ->whereNotIn('status_employment', ['resigned', 'terminated'])
                ->where(function ($sub) use ($cutoff, $floor) {
                    // Kasus A: HRD sudah set probation_end_date manual
                    $sub->where(function ($q2) use ($cutoff, $floor) {
                        $q2->whereNotNull('probation_end_date')
                           ->whereDate('probation_end_date', '<=', $cutoff)
                           ->whereDate('probation_end_date', '>=', $floor);
                    })
                    // Kasus B: tidak ada probation_end_date → hitung dari join_date + 3 bulan
                    ->orWhere(function ($q2) use ($cutoff, $floor) {
                        $q2->whereNull('probation_end_date')
                           ->whereNotNull('join_date')
                           ->whereRaw('DATE_ADD(join_date, INTERVAL 3 MONTH) <= ?', [$cutoff->toDateString()])
                           ->whereRaw('DATE_ADD(join_date, INTERVAL 3 MONTH) >= ?', [$floor->toDateString()]);
                    });
                })
                ->orderByRaw('COALESCE(probation_end_date, DATE_ADD(join_date, INTERVAL 3 MONTH))')
                ->get(['id', 'full_name', 'employee_number', 'probation_end_date', 'join_date', 'status_employment']);

            $candidateEmployees->each(function ($emp) {
                $emp->display_probation_end = $emp->computed_probation_end_date?->format('Y-m-d');
            });

            $now = now();
            foreach ($candidateEmployees as $emp) {
                $probEnd  = $emp->computed_probation_end_date;
                $daysLeft = $probEnd ? (int) $now->diffInDays($probEnd, false) : null;

                if ($daysLeft === null || $daysLeft > 7) {
                    $grouped['safe']->push($emp);
                } elseif ($daysLeft < 0) {
                    $grouped['overdue']->push($emp);
                } else {
                    $grouped['urgent']->push($emp);
                }
            }

            $grouped['safe']    = $grouped['safe']->sortBy(fn ($e) => $e->computed_probation_end_date?->timestamp ?? PHP_INT_MAX);
            $grouped['urgent']  = $grouped['urgent']->sortBy(fn ($e) => $e->computed_probation_end_date?->timestamp ?? PHP_INT_MAX);
            $grouped['overdue'] = $grouped['overdue']->sortBy(fn ($e) => $e->computed_probation_end_date?->timestamp ?? PHP_INT_MAX);
        }

        return view('appraisals.assignment.index', compact(
            'mode', 'windowDays', 'periods', 'candidateEmployees', 'defaultPeriodId', 'grouped'
        ));
    }

    public function searchEmployees(Request $request): JsonResponse
    {
        $q    = (string) $request->get('q', '');
        $mode = $request->get('mode', 'manual');
        $days = max(1, min(90, (int) $request->get('days', 30)));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $query = Employee::query()
            ->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('employee_number', 'like', "%{$q}%");
            })
            ->whereNotIn('status_employment', ['resigned', 'terminated']);

        if ($mode === 'probation') {
            $cutoff = Carbon::today()->addDays($days);
            $floor  = Carbon::today()->subDays(7);

            $query->where(function ($sub) use ($cutoff, $floor) {
                // Kasus A: probation_end_date sudah diisi manual oleh HRD
                $sub->where(function ($q2) use ($cutoff, $floor) {
                    $q2->whereNotNull('probation_end_date')
                       ->whereDate('probation_end_date', '<=', $cutoff)
                       ->whereDate('probation_end_date', '>=', $floor);
                })
                // Kasus B: tidak ada probation_end_date → hitung dari join_date + 3 bulan
                ->orWhere(function ($q2) use ($cutoff, $floor) {
                    $q2->whereNull('probation_end_date')
                       ->whereNotNull('join_date')
                       ->whereRaw('DATE_ADD(join_date, INTERVAL 3 MONTH) <= ?', [$cutoff->toDateString()])
                       ->whereRaw('DATE_ADD(join_date, INTERVAL 3 MONTH) >= ?', [$floor->toDateString()]);
                });
            });
        }

        $results = $query->orderBy('full_name')
            ->limit(15)
            ->get(['id', 'full_name', 'employee_number', 'probation_end_date', 'join_date', 'status_employment']);

        $results->each(function ($emp) {
            $emp->display_probation_end = $emp->computed_probation_end_date?->format('Y-m-d');
        });

        return response()->json($results);
    }

    public function searchEvaluators(Request $request): JsonResponse
    {
        $q = (string) $request->get('q', '');

        $users = User::query()
            ->when(strlen($q) >= 1, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->whereNotNull('name')
            ->where(function ($q) {
                $q->whereNotNull('employee_id')
                  ->orWhereIn('role', ['admin', 'manager', 'hrd', 'superadmin']);
            })
            ->orderByRaw("CASE role WHEN 'manager' THEN 1 WHEN 'hrd' THEN 2 WHEN 'admin' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'role']);

        return response()->json($users);
    }

    public function generateBatch(Request $request)
    {
        if (! Schema::hasTable('appraisals') || ! Schema::hasTable('employees') || ! Schema::hasTable('users') || ! Schema::hasTable('appraisal_periods')) {
            return back()->with('error', 'Modul assignment appraisal belum siap di environment ini.');
        }

        $data = $request->validate([
            'mode'                      => ['required', 'in:probation,manual'],
            'period_id'                 => ['required', 'integer', 'exists:appraisal_periods,id'],
            'date_appraised'            => ['required', 'date'],
            'due_date'                  => ['nullable', 'date'],
            'invitation_note'           => ['nullable', 'string', 'max:500'],
            'enable_kpi_component'      => ['nullable', 'boolean'],
            'enable_skill_component'    => ['nullable', 'boolean'],
            'enable_position_component' => ['nullable', 'boolean'],
            'batch'                     => ['required', 'array', 'min:1'],
            'batch.*.employee_id'       => ['required', 'integer', 'exists:employees,id'],
            'batch.*.evaluator_ids'     => ['required', 'array', 'min:1'],
            'batch.*.evaluator_ids.*'   => ['integer', 'exists:users,id'],
        ]);

        $triggerSource    = $data['mode'] === 'probation' ? 'probation_timeline' : 'manual_acceleration';
        $appraisalColumns = Schema::getColumnListing('appraisals');
        $actorId          = (int) ($request->user()?->id ?? 0);
        $created          = 0;
        $skipped          = 0;

        DB::beginTransaction();
        try {
            foreach ($data['batch'] as $row) {
                $employeeId = (int) $row['employee_id'];
                $employee   = Employee::find($employeeId);

                foreach ((array) $row['evaluator_ids'] as $evaluatorId) {
                    $evaluatorId = (int) $evaluatorId;

                    $exists = Appraisal::query()
                        ->where('appraisal_period_id', $data['period_id'])
                        ->where('employee_id', $employeeId)
                        ->where('appraiser_id', $evaluatorId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $payload = [
                        'appraisal_period_id' => $data['period_id'],
                        'employee_id'         => $employeeId,
                        'appraiser_id'        => $evaluatorId,
                        'date_appraised'      => $data['date_appraised'],
                        'status'              => 'draft',
                    ];

                    if (in_array('trigger_source', $appraisalColumns, true)) {
                        $payload['trigger_source'] = $triggerSource;
                    }
                    if (in_array('trigger_reason', $appraisalColumns, true)) {
                        $payload['trigger_reason'] = $triggerSource === 'manual_acceleration'
                            ? 'Percepatan manual oleh HRD (batch generate).'
                            : 'Otomatis dari timeline probation karyawan (batch generate).';
                    }
                    if (in_array('invited_by_user_id', $appraisalColumns, true)) {
                        $payload['invited_by_user_id'] = $actorId > 0 ? $actorId : null;
                    }
                    if (in_array('due_date', $appraisalColumns, true)) {
                        $payload['due_date'] = ($data['due_date'] ?? '') !== ''
                            ? $data['due_date']
                            : optional($employee?->probation_end_date)->toDateString();
                    }
                    if (in_array('probation_end_snapshot', $appraisalColumns, true)) {
                        $payload['probation_end_snapshot'] = optional($employee?->probation_end_date)->toDateString();
                    }
                    if (in_array('invited_at', $appraisalColumns, true)) {
                        $payload['invited_at'] = now();
                    }
                    if (in_array('is_feedback_private', $appraisalColumns, true)) {
                        $payload['is_feedback_private'] = true;
                    }
                    if (in_array('invitation_note', $appraisalColumns, true)) {
                        $payload['invitation_note'] = $data['invitation_note'] ?? null;
                    }
                    if (in_array('enable_kpi_component', $appraisalColumns, true)) {
                        $payload['enable_kpi_component'] = (bool) ($data['enable_kpi_component'] ?? true);
                    }
                    if (in_array('enable_skill_component', $appraisalColumns, true)) {
                        $payload['enable_skill_component'] = (bool) ($data['enable_skill_component'] ?? false);
                    }
                    if (in_array('enable_position_component', $appraisalColumns, true)) {
                        $payload['enable_position_component'] = (bool) ($data['enable_position_component'] ?? false);
                    }
                    if (in_array('criteria_template_id', $appraisalColumns, true) && Schema::hasTable('appraisal_criteria_templates')) {
                        $payload['criteria_template_id'] = AppraisalCriteriaTemplate::resolveFor($employee?->lokasi_kerja)?->id;
                    }

                    $appraisal = Appraisal::create($payload);
                    $this->notifyAppraiser($appraisal, $actorId);
                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal generate batch: ' . $e->getMessage());
        }

        $msg = "Berhasil membuat {$created} draft appraisal.";
        if ($skipped > 0) {
            $msg .= " {$skipped} dilewati karena sudah ada pada periode ini.";
        }

        return redirect()->route('appraisals.assignment')->with('success', $msg);
    }

    private function notifyAppraiser(Appraisal $appraisal, ?int $actorId = null): void
    {
        if (! Schema::hasTable('hr_notifications')) {
            return;
        }

        $columns = Schema::getColumnListing('hr_notifications');
        foreach (['type', 'title', 'unique_key'] as $col) {
            if (! in_array($col, $columns, true)) {
                return;
            }
        }

        $appraisal->loadMissing(['employee', 'period']);

        $payload = [
            'type'       => 'appraisal_invitation',
            'title'      => 'Invitation evaluator appraisal',
            'unique_key' => 'appraisal-invitation-' . $appraisal->id . '-' . now()->format('YmdHis') . '-' . uniqid(),
        ];

        if (in_array('user_id', $columns, true)) {
            $payload['user_id'] = $appraisal->appraiser_id;
        }
        if (in_array('body', $columns, true)) {
            $payload['body'] = 'Anda mendapat assignment appraisal untuk ' . ($appraisal->employee?->full_name ?? 'karyawan') . '.';
        }
        if (in_array('due_date', $columns, true) && Schema::hasColumn('appraisals', 'due_date')) {
            $payload['due_date'] = $appraisal->due_date?->toDateString();
        }
        if (in_array('is_read', $columns, true)) {
            $payload['is_read'] = false;
        }
        if (in_array('meta', $columns, true)) {
            $payload['meta'] = [
                'appraisal_id'   => $appraisal->id,
                'route'          => route('appraisals.show', $appraisal),
                'period'         => $appraisal->period?->name,
                'trigger_source' => $appraisal->trigger_source,
            ];
        }

        HrNotification::query()->create($payload);

        $this->sendAppraisalEmail(
            'appraisal_invitation',
            $appraisal->appraiser_id,
            [
                'employee_name' => $appraisal->employee?->full_name ?? 'karyawan',
                'due_date'      => $appraisal->due_date?->format('d-m-Y') ?? '-',
            ]
        );

        if (Schema::hasTable('appraisal_invitation_logs')) {
            AppraisalInvitationLog::query()->create([
                'appraisal_id'   => $appraisal->id,
                'actor_user_id'  => $actorId,
                'target_user_id' => $appraisal->appraiser_id,
                'action'         => 'invited',
                'notes'          => $appraisal->invitation_note,
                'payload'        => [
                    'due_date'               => $appraisal->due_date?->toDateString(),
                    'period'                 => $appraisal->period?->name,
                    'trigger_source'         => $appraisal->trigger_source,
                    'probation_end_snapshot' => $appraisal->probation_end_snapshot?->toDateString(),
                ],
            ]);
        }
    }
}
