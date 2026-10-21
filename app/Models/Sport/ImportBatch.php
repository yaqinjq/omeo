<?php

namespace App\Models\Sport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $table = 'sport_import_batches';

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_MAPPED = 'mapped';
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'sport_event_id',
        'uploaded_by_user_id',
        'original_filename',
        'file_hash',
        'column_mapping',
        'status',
        'total_rows',
        'valid_rows',
        'duplicate_rows',
        'invalid_rows',
        'unmatched_rows',
        'committed_at',
        'committed_by_user_id',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'committed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'sport_event_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'sport_import_batch_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
