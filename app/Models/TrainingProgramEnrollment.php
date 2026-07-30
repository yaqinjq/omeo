<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingProgramEnrollment extends Model
{
    protected $fillable = [
        'training_program_id',
        'employee_id',
        'status',
        'progress_percent',
        'last_training_material_id',
        'started_at',
        'completed_at',
        'last_activity_at',
        'assigned_by',
        'metadata',
    ];

    protected $casts = [
        'progress_percent' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function program()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function lastMaterial()
    {
        return $this->belongsTo(TrainingMaterial::class, 'last_training_material_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function progressItems()
    {
        return $this->hasMany(TrainingMaterialProgress::class)->orderBy('training_material_id');
    }
}
