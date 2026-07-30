<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingMaterialProgress extends Model
{
    protected $table = 'training_material_progress';

    protected $fillable = [
        'training_program_enrollment_id',
        'employee_id',
        'training_program_id',
        'training_material_id',
        'status',
        'progress_percent',
        'pretest_score',
        'posttest_score',
        'pretest_attempt_id',
        'posttest_attempt_id',
        'started_at',
        'completed_at',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'progress_percent' => 'decimal:2',
        'pretest_score' => 'decimal:2',
        'posttest_score' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function enrollment()
    {
        return $this->belongsTo(TrainingProgramEnrollment::class, 'training_program_enrollment_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function program()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function material()
    {
        return $this->belongsTo(TrainingMaterial::class, 'training_material_id');
    }
}
