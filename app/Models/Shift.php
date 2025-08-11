<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id', 'company_id', 'language_id', 'shift_date',
        'start_time', 'end_time', 'status', 'slack_channel_id',
        'slack_channel_created', 'max_students', 'current_students',
        'notes', 'metadata'
    ];

    protected $casts = [
        'shift_date' => 'date',
        'slack_channel_created' => 'boolean',
        'max_students' => 'integer',
        'current_students' => 'integer',
        'metadata' => 'array',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function isAvailable(): bool
    {
        return $this->current_students < $this->max_students;
    }

    public function scopeUpcoming($query)
    {
        return $query->where('shift_date', '>=', today())
            ->where('status', 'scheduled');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('shift_date', $date);
    }
}