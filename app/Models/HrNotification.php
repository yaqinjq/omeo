<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrNotification extends Model
{
    protected $table = 'hr_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'due_date',
        'is_read',
        'unique_key',
        'meta',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_read' => 'boolean',
        'meta' => 'array',
    ];
}
