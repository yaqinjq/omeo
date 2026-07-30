<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeBpjsAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'legal_entity_id',
        'effective_date',
        'end_date',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date'       => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->end_date) || $this->end_date->isFuture();
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'join'     => 'Bergabung',
            'transfer' => 'Pindah PT',
            'resign'   => 'Resign',
            default    => $this->reason,
        };
    }

    // Scope: assignment yang aktif (end_date null atau belum lewat)
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now()->toDateString());
        });
    }

    // Scope: assignment yang sudah berakhir
    public function scopeEnded($query)
    {
        return $query->whereNotNull('end_date')
                     ->where('end_date', '<', now()->toDateString());
    }
}
