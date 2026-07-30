<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormAnswer extends Model
{
    protected $fillable = [
        'form_attempt_id',
        'question_id',
        'answer_text',
        'answer_value',
        'answer_json',
    ];

    protected $casts = [
        'answer_json' => 'array',
    ];

    public function attempt()
    {
        return $this->belongsTo(FormAttempt::class, 'form_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'question_id');
    }
}

