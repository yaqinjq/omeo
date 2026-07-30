<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TrainingProgramMaterial extends Pivot
{
    protected $table = 'training_program_material';

    protected $fillable = [
        'training_program_id',
        'training_material_id',
        'sequence_order',
        'is_required',
        'unlock_after_previous_completed',
        'estimated_minutes',
        'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'unlock_after_previous_completed' => 'boolean',
        'metadata' => 'array',
    ];
}
