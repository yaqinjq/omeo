<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormOption extends Model
{
    protected $fillable = [
        'question_id',
        'position',
        'option_text',
        'value',
        'weight',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'question_id');
    }
}

