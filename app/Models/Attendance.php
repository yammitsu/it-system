<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'attendance_date', 'status', 'check_in_time',
        'check_out_time', 'study_minutes', 'slack_channel_id',
        'slack_invited', 'slack_invited_at', 'notes', 'ip_address', 'metadata'
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'slack_invited_at' => 'datetime',
        'slack_invited' => 'boolean',
        'study_minutes' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsPresent(): void
    {
        $this->update([
            'status' => 'present',
            'check_in_time' => now()->format('H:i:s'),
        ]);

        $this->user->updateConsecutiveDays();
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeToday($query)
    {
        return $query->where('attendance_date', today());
    }

    public function scopeTomorrow($query)
    {
        return $query->where('attendance_date', today()->addDay());
    }
}