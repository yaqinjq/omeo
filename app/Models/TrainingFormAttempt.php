<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingFormAttempt extends Model
{
    protected $fillable = [
        'employee_id',
        'training_program_id',
        'training_material_id',
        'form_id',
        'purpose',
        'status',
        'started_at',
        'submitted_at',
        'time_spent_seconds',
        'computed_result',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'computed_result' => 'array',
    ];

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

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'form_id');
    }

    public function answers()
    {
        return $this->hasMany(TrainingFormAnswer::class)->orderBy('question_id');
    }
}
