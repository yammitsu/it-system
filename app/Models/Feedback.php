<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'target_user_id', 'task_id', 'learning_path_id',
        'type', 'title', 'content', 'rating', 'ratings_detail',
        'status', 'priority', 'assigned_to', 'response', 'responded_at',
        'is_public', 'is_anonymous', 'attachments', 'metadata'
    ];

    protected $casts = [
        'ratings_detail' => 'array',
        'attachments' => 'array',
        'metadata' => 'array',
        'responded_at' => 'datetime',
        'is_public' => 'boolean',
        'is_anonymous' => 'boolean',
        'rating' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function respond(string $response, ?int $responderId = null): void
    {
        $this->update([
            'response' => $response,
            'responded_at' => now(),
            'status' => 'resolved',
            'assigned_to' => $responderId ?? auth()->id(),
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}