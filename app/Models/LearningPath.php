<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'language_id', 'company_id', 'created_by', 'name', 'description',
        'slug', 'level', 'estimated_weeks', 'objectives', 'prerequisites',
        'task_ids', 'display_order', 'is_recommended', 'is_active',
        'enrollment_count', 'completion_count', 'average_rating', 'metadata'
    ];

    protected $casts = [
        'objectives' => 'array',
        'prerequisites' => 'array',
        'task_ids' => 'array',
        'metadata' => 'array',
        'is_recommended' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'estimated_weeks' => 'integer',
        'enrollment_count' => 'integer',
        'completion_count' => 'integer',
        'average_rating' => 'decimal:2',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_learning_paths')
            ->withPivot('enrolled_at', 'completed_at', 'progress_percentage')
            ->withTimestamps();
    }

    public function tasks()
    {
        return Task::whereIn('id', $this->task_ids ?? [])->ordered();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }
}