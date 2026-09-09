<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';

    protected $fillable = [
        'project_id',
        'parent_task_id',
        'title',
        'description',
        'position_id',
        'employee_id',
        'assignment_type',
        'due_date',
        'status',
        'priority',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Breakdown tugas tanpa batas kedalaman (ClickUp-style): tugas ini bisa
     * jadi sub-tugas dari tugas lain, dan sub-tugas itu sendiri bisa punya
     * sub-tugas lagi, dst. Papan utama cuma menampilkan tugas root
     * (parent_task_id null) — breakdown dilihat lewat modal edit tugas.
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Semua ID keturunan (anak, cucu, dst) — dipakai saat hapus tugas supaya
     * seluruh breakdown-nya ikut terhapus, bukan jadi baris yatim yang
     * nunjuk ke parent_task_id yang sudah tidak ada.
     */
    public function allDescendantIds(): array
    {
        $ids = [];
        $queue = [$this->id];

        while ($queue) {
            $childIds = static::where('parent_task_id', array_shift($queue))->pluck('id')->all();
            foreach ($childIds as $id) {
                $ids[] = $id;
                $queue[] = $id;
            }
        }

        return $ids;
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', self::STATUS_DONE)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }
}
