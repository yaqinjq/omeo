<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingParticipation extends Model
{
    protected $table = 'training_participations';

    protected $fillable = [
        'employee_id',
        'training_material_id',
        'status',
        'completion_date',
        'quiz_score',
        'is_refreshment',
    ];

    protected $casts = [
        'completion_date' => 'datetime',
        'is_refreshment' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function material()
    {
        return $this->belongsTo(TrainingMaterial::class, 'training_material_id');
    }
}
