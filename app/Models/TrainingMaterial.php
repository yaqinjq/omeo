<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrainingMaterial extends Model
{
    protected $table = 'training_materials';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'youtube_url',
        'category',
        'duration_minutes',
        'audience_scope',
        'department_id',
        'position_id',
        'mentor_user_id',
        'pretest_form_id',
        'posttest_form_id',
        'content_source_type',
        'content_source_url',
        'thumbnail_path',
        'pass_score',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $material): void {
            if (blank($material->slug) && filled($material->title)) {
                $material->slug = Str::slug($material->title . '-' . Str::random(5));
            }

            if (blank($material->content_source_type) && filled($material->youtube_url)) {
                $material->content_source_type = 'youtube';
                $material->content_source_url = $material->youtube_url;
            }
        });
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function pretestForm()
    {
        return $this->belongsTo(AssessmentForm::class, 'pretest_form_id');
    }

    public function posttestForm()
    {
        return $this->belongsTo(AssessmentForm::class, 'posttest_form_id');
    }

    public function programs()
    {
        return $this->belongsToMany(TrainingProgram::class, 'training_program_material')
            ->using(TrainingProgramMaterial::class)
            ->withPivot(['id', 'sequence_order', 'is_required', 'unlock_after_previous_completed', 'estimated_minutes', 'metadata'])
            ->withTimestamps()
            ->orderBy('training_program_material.sequence_order');
    }

    public function legacyParticipations()
    {
        return $this->hasMany(TrainingParticipation::class, 'training_material_id');
    }
}
