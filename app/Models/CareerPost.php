<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerPost extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CLOSED,
    ];

    public const EMPLOYMENT_TYPES = [
        'full-time',
        'part-time',
        'contract',
        'internship',
        'freelance',
    ];

    protected $fillable = [
        'career_department_id',
        'title',
        'slug',
        'location',
        'employment_type',
        'description',
        'qualifications',
        'benefits',
        'status',
        'published_at',
        'closing_at',
        'seo_title',
        'seo_description',
        'apply_button_label',
        'apply_url',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'closing_at' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(CareerDepartment::class, 'career_department_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $builder): void {
                $builder->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('closing_at')->orWhereDate('closing_at', '>=', now()->toDateString());
            });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getApplyLinkAttribute(): string
    {
        return filled($this->apply_url) ? (string) $this->apply_url : route('register');
    }
}
