<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'language_id',
        'company_id',
        'created_by',
        'parent_task_id',
        'category_major',
        'category_minor',
        'title',
        'description',
        'instructions',
        'prerequisites',
        'file_path',
        'file_name',
        'file_size',
        'file_mime_type',
        'file_scanned_at',
        'file_is_safe',
        'estimated_hours',
        'points',
        'difficulty',
        'display_order',
        'is_required',
        'is_template',
        'is_active',
        'available_from',
        'available_until',
        'tags',
        'metadata',
        'completion_count',
        'average_completion_time',
        'average_rating',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'file_scanned_at' => 'datetime',
        'available_from' => 'date',
        'available_until' => 'date',
        'file_is_safe' => 'boolean',
        'is_required' => 'boolean',
        'is_template' => 'boolean',
        'is_active' => 'boolean',
        'estimated_hours' => 'integer',
        'points' => 'integer',
        'file_size' => 'integer',
        'completion_count' => 'integer',
        'average_completion_time' => 'decimal:2',
        'average_rating' => 'decimal:2',
    ];

    /**
     * Get the language that owns the task.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the company that owns the task.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the parent task.
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Get the child tasks.
     */
    public function childTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Get the task submissions.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }

    /**
     * Get the dependencies for the task.
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('dependency_type', 'description')
            ->withTimestamps();
    }

    /**
     * Get the tasks that depend on this task.
     */
    public function dependentTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('dependency_type', 'description')
            ->withTimestamps();
    }

    /**
     * Get the feedback for the task.
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the chat messages for the task.
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Get the download logs for the task.
     */
    public function downloadLogs(): HasMany
    {
        return $this->hasMany(FileDownloadLog::class);
    }

    /**
     * Check if task is available.
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now()->toDateString();

        if ($this->available_from && $this->available_from > $now) {
            return false;
        }

        if ($this->available_until && $this->available_until < $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has completed prerequisites.
     */
    public function hasCompletedPrerequisites(User $user): bool
    {
        $requiredDependencies = $this->dependencies()
            ->where('dependency_type', 'required')
            ->get();

        foreach ($requiredDependencies as $dependency) {
            $submission = $user->taskSubmissions()
                ->where('task_id', $dependency->id)
                ->where('status', 'completed')
                ->first();

            if (!$submission) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's submission for this task.
     */
    public function getUserSubmission(User $user)
    {
        return $this->submissions()->where('user_id', $user->id)->first();
    }

    /**
     * Update completion statistics.
     */
    public function updateStatistics(): void
    {
        $completedSubmissions = $this->submissions()
            ->where('status', 'completed')
            ->get();

        $this->completion_count = $completedSubmissions->count();

        if ($this->completion_count > 0) {
            $totalTime = $completedSubmissions->sum('actual_hours') / 60; // Convert to hours
            $this->average_completion_time = $totalTime / $this->completion_count;

            $ratings = $completedSubmissions->whereNotNull('rating')->pluck('rating');
            if ($ratings->count() > 0) {
                $this->average_rating = $ratings->avg();
            }
        }

        $this->save();
    }

    /**
     * Get file URL.
     */
    public function getFileUrl(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    /**
     * Scope for active tasks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for available tasks.
     */
    public function scopeAvailable($query)
    {
        $now = now()->toDateString();
        
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', $now);
            });
    }

    /**
     * Scope for required tasks.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope for tasks by difficulty.
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    /**
     * Order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}