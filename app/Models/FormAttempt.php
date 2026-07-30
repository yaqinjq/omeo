<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormAttempt extends Model
{
    protected $fillable = [
        'form_assignment_id',
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

    public function assignment()
    {
        return $this->belongsTo(FormAssignment::class, 'form_assignment_id');
    }

    public function answers()
    {
        return $this->hasMany(FormAnswer::class, 'form_attempt_id');
    }
}

