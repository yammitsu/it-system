<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'task_id', 'evaluated_by', 'status',
        'first_downloaded_at', 'started_at', 'completed_at',
        'actual_hours', 'progress_comment', 'completion_note',
        'rating', 'feedback', 'score', 'evaluation_comment',
        'evaluated_at', 'download_count', 'submission_files', 'metadata'
    ];

    protected $casts = [
        'first_downloaded_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'submission_files' => 'array',
        'metadata' => 'array',
        'actual_hours' => 'integer',
        'rating' => 'integer',
        'score' => 'integer',
        'download_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->task->increment('completion_count');
        $this->task->updateStatistics();
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
}