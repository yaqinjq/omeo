<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appraisal extends Model
{
    protected $table = 'appraisals';

    protected $fillable = [
        'appraisal_period_id',
        'employee_id',
        'appraiser_id',
        'invited_by_user_id',
        'date_appraised',
        'due_date',
        'invited_at',
        'last_reminded_at',
        'submitted_at',
        'is_feedback_private',
        'invitation_note',
        'feedback_strengths',
        'feedback_improvements',
        'feedback_notes',
        'final_score',
        'final_result',
        'notes_hrd',
        'generated_letter_number',
        'trigger_source',
        'trigger_reason',
        'probation_end_snapshot',
        'accelerated_by_user_id',
        'status',
        'proposed_status',
        'proposed_position',
        'proposed_contract_duration',
        'contract_extension_effective_date',
        'feedback_achievements',
        'feedback_training_recommendations',
        'enable_skill_component',
        'enable_position_component',
        'enable_kpi_component',
        'migration_source',
        'migration_legacy_id',
        'migrated_at',
        'included_in_score',
    ];

    protected $casts = [
        'date_appraised' => 'date',
        'due_date' => 'date',
        'invited_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'submitted_at' => 'datetime',
        'probation_end_snapshot' => 'date',
        'contract_extension_effective_date' => 'date',
        'is_feedback_private'      => 'boolean',
        'final_score'              => 'decimal:2',
        'enable_skill_component'   => 'boolean',
        'enable_position_component' => 'boolean',
        'enable_kpi_component'     => 'boolean',
        'included_in_score'        => 'boolean',
    ];

    public function period() { return $this->belongsTo(AppraisalPeriod::class, 'appraisal_period_id'); }
    public function employee() { return $this->belongsTo(Employee::class, 'employee_id'); }
    public function appraiser() { return $this->belongsTo(User::class, 'appraiser_id'); }
    public function invitedBy() { return $this->belongsTo(User::class, 'invited_by_user_id'); }
    public function details() { return $this->hasMany(AppraisalDetail::class, 'appraisal_id'); }
    public function componentScores() { return $this->hasMany(AppraisalComponentScore::class, 'appraisal_id'); }
    public function invitationLogs() { return $this->hasMany(AppraisalInvitationLog::class, 'appraisal_id')->latest('id'); }
    public function editRequests() { return $this->hasMany(AppraisalEditRequest::class, 'appraisal_id')->latest('id'); }
}

