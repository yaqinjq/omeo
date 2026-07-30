<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingFormAnswer extends Model
{
    protected $fillable = [
        'training_form_attempt_id',
        'question_id',
        'answer_text',
        'answer_value',
        'answer_json',
        'answer_file_path',
    ];

    protected $casts = [
        'answer_json' => 'array',
    ];

    public function attempt()
    {
        return $this->belongsTo(TrainingFormAttempt::class, 'training_form_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'question_id');
    }
}
