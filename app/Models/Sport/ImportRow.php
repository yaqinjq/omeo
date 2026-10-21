<?php

namespace App\Models\Sport;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $table = 'sport_import_rows';

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_UNMATCHED = 'unmatched';

    public const PARTICIPANT_TYPE_MKO = 'mko';
    public const PARTICIPANT_TYPE_NON_MKO = 'non_mko';

    protected $fillable = [
        'sport_import_batch_id',
        'row_number',
        'raw_data',
        'participant_type',
        'matched_employee_id',
        'matched_participant_id',
        'row_status',
        'error_message',
        'resulting_attendance_id',
        'resulting_non_mko_attendance_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'sport_import_batch_id');
    }

    public function matchedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'matched_employee_id');
    }

    public function matchedParticipant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'matched_participant_id');
    }
}
