<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingProgram extends Model
{
    protected $fillable = [
        'name',
        'description',
        'audience_scope',
        'department_id',
        'position_id',
        'mentor_user_id',
        'is_sequential',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_sequential' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function materials()
    {
        return $this->belongsToMany(TrainingMaterial::class, 'training_program_material')
            ->using(TrainingProgramMaterial::class)
            ->withPivot(['id', 'sequence_order', 'is_required', 'unlock_after_previous_completed', 'estimated_minutes', 'metadata'])
            ->withTimestamps()
            ->orderBy('training_program_material.sequence_order');
    }

    public function enrollments()
    {
        return $this->hasMany(TrainingProgramEnrollment::class)->orderByDesc('updated_at');
    }

    public function events()
    {
        return $this->hasMany(TrainingEvent::class)->orderBy('starts_at');
    }
}
