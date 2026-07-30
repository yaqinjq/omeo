<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionSalaryRange extends Model
{
    protected $fillable = [
        'position_id', 'region_id', 'min_salary', 'max_salary',
        'effective_from', 'effective_to', 'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'is_active'      => 'boolean',
        'min_salary'     => 'decimal:2',
        'max_salary'     => 'decimal:2',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
