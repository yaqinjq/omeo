<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'outlet_id',
        'payroll_outlet_id',
        'legal_entity_id',
        'department',
        'effective_date',
        'start_date',
        'end_date',
        'assignment_type',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'start_date'     => 'date',
        'end_date'       => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function payrollOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'payroll_outlet_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getEffectiveDateDisplayAttribute(): string
    {
        $date = $this->effective_date ?? $this->start_date;

        return $date ? $date->format('d M Y') : '-';
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->end_date) || $this->end_date->isFuture();
    }

    public function getAssignmentTypeLabelAttribute(): string
    {
        return $this->assignment_type === 'primary' ? 'Utama' : 'Tambahan';
    }
}
