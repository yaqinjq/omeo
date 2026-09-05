<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nik',
        'employee_number',
        'external_id',
        'full_name',
        'email_private',
        'phone_number',
        'join_date',
        'probation_end_date',
        'current_salary',
        'status_employment',
        'department_id',
        'position_id',
        'manager_id',
        'outlet_id',
        'outlet_penugasan_id',
        'legal_entity_kontrak_id',
        'legal_entity_bpjs_id',
        'nokom',
        'jabatan',
        'npk',
        'lokasi_kerja',
        'payroll_session_tag',
    ];

    protected $casts = [
        'join_date' => 'date',
        'probation_end_date' => 'date',
        'current_salary' => 'decimal:2',
    ];

    protected $appends = ['computed_employment_status', 'nik_display'];

    /**
     * Hitung status employment secara otomatis dari join_date.
     * Tidak bergantung pada kolom status_employment yang sering basi hasil import HRIS.
     *
     * Prioritas: resigned/terminated eksplisit → probation_end_date manual HRD → join_date + 3 bulan.
     */
    public function getComputedEmploymentStatusAttribute(): string
    {
        if (in_array($this->status_employment, ['resigned', 'terminated'])) {
            return $this->status_employment;
        }

        $probationEnd = $this->probation_end_date
            ? \Carbon\Carbon::parse($this->probation_end_date)
            : null;

        if (! $probationEnd && $this->join_date) {
            $probationEnd = \Carbon\Carbon::parse($this->join_date)->addMonths(3);
        }

        if (! $probationEnd) {
            return $this->status_employment ?? 'unknown';
        }

        return now()->greaterThan($probationEnd) ? 'active' : 'probation';
    }

    /**
     * Tanggal akhir probation yang terhitung: pakai yang manual HRD jika ada,
     * atau hitung otomatis dari join_date + 3 bulan.
     */
    public function getComputedProbationEndDateAttribute(): ?\Carbon\Carbon
    {
        if ($this->probation_end_date) {
            return \Carbon\Carbon::parse($this->probation_end_date);
        }
        if ($this->join_date) {
            return \Carbon\Carbon::parse($this->join_date)->addMonths(3);
        }
        return null;
    }

    /**
     * NIK display: kolom nik dulu, fallback ke applicant_profile.ktp_number
     * Dipakai di view untuk display — BUKAN untuk query/filter
     */
    public function getNikDisplayAttribute(): string
    {
        if (!empty($this->nik)) {
            return $this->nik;
        }
        // Fallback via user → applicantProfile
        try {
            $profile = $this->user?->applicantProfile ?? null;
            $nik     = $profile?->ktp_number ?? '';
            return $nik ?: '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outletPenugasan()
    {
        return $this->belongsTo(Outlet::class, 'outlet_penugasan_id');
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function bpjsAssignments()
    {
        return $this->hasMany(EmployeeBpjsAssignment::class)->orderByDesc('effective_date');
    }

    public function activeBpjsAssignment()
    {
        return $this->hasOne(EmployeeBpjsAssignment::class)
            ->whereNull('end_date')
            ->latest('effective_date');
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeOutletAssignment::class)->orderByDesc('effective_date');
    }

    public function activeAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeOutletAssignment::class)
            ->whereNull('end_date')
            ->latest('effective_date');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(EmployeeBankAccount::class)->whereNull('deleted_at');
    }

    public function primaryBankAccount()
    {
        return $this->hasOne(EmployeeBankAccount::class)
            ->where('is_primary', true)
            ->whereNull('deleted_at');
    }

    public function salaryHistories()
    {
        return $this->hasMany(EmployeeSalaryHistory::class)
            ->whereNull('deleted_at')
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    public function appraisals()
    {
        return $this->hasMany(Appraisal::class)->orderByDesc('date_appraised')->orderByDesc('id');
    }

    public function trainingParticipations()
    {
        return $this->hasMany(TrainingParticipation::class)->orderByDesc('completion_date')->orderByDesc('id');
    }

    public function trainingMaterialProgress()
    {
        return $this->hasMany(TrainingMaterialProgress::class)->orderByDesc('completed_at')->orderByDesc('id');
    }

    public function trainingTrainer()
    {
        return $this->hasOne(TrainingTrainer::class);
    }

    public function legalEntityKontrak(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LegalEntity::class, 'legal_entity_kontrak_id');
    }

    public function legalEntityBpjs(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LegalEntity::class, 'legal_entity_bpjs_id');
    }
}

