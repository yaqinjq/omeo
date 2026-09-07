<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgChartNode extends Model
{
    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_BRAND      = 'brand';
    public const TYPE_OUTLET     = 'outlet';
    public const TYPE_EMPLOYEE   = 'employee';

    protected $fillable = [
        'parent_id',
        'node_type',
        'department_id',
        'outlet_id',
        'brand_name',
        'employee_id',
        'is_leader_override',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_leader_override' => 'boolean',
        'sort_order'          => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgChartNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgChartNode::class, 'parent_id')->orderBy('sort_order');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Status Leader/Anggota "kombinasi" yang diminta user: kalau HRD belum
     * pernah override manual (is_leader_override null), tebak dari ada
     * tidaknya bawahan hari ini — begitu node ini punya anak, otomatis
     * tampil sebagai Leader tanpa perlu di-set manual dulu.
     */
    public function getEffectiveIsLeaderAttribute(): bool
    {
        if ($this->is_leader_override !== null) {
            return $this->is_leader_override;
        }

        return $this->children()->exists();
    }
}
