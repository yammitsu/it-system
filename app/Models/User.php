<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'language_id',
        'name',
        'email',
        'password',
        'slack_user_id',
        'slack_email',
        'role',
        'employee_number',
        'department',
        'position',
        'phone',
        'avatar',
        'enrollment_date',
        'completion_date',
        'status',
        'total_study_hours',
        'consecutive_days',
        'last_login_at',
        'last_login_ip',
        'notification_settings',
        'preferences',
        'timezone',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'enrollment_date' => 'date',
        'completion_date' => 'date',
        'last_login_at' => 'datetime',
        'notification_settings' => 'array',
        'preferences' => 'array',
        'total_study_hours' => 'integer',
        'consecutive_days' => 'integer',
        'password' => 'hashed',
    ];

    /**
     * Get the company that owns the user.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the language for the user.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the task submissions for the user.
     */
    public function taskSubmissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }

    /**
     * Get the attendances for the user.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the shifts for the teacher.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'teacher_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the badges for the user.
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * Get the learning paths for the user.
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'user_learning_paths')
            ->withPivot('enrolled_at', 'completed_at', 'progress_percentage', 'completed_tasks', 'total_tasks')
            ->withTimestamps();
    }

    /**
     * Get the feedback sent by the user.
     */
    public function sentFeedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the feedback received by the user.
     */
    public function receivedFeedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'target_user_id');
    }

    /**
     * Get the chat messages sent by the user.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /**
     * Get the chat messages received by the user.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'recipient_id');
    }

    /**
     * Get the learning notes for the user.
     */
    public function learningNotes(): HasMany
    {
        return $this->hasMany(LearningNote::class);
    }

    /**
     * Get the active sessions for the user.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Get the active session for the user.
     */
    public function activeSession()
    {
        return $this->sessions()->where('is_active', true)->latest()->first();
    }

    /**
     * Check if user is a system admin.
     */
    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    /**
     * Check if user is a company admin.
     */
    public function isCompanyAdmin(): bool
    {
        return $this->role === 'company_admin';
    }

    /**
     * Check if user is a teacher.
     */
    public function isTeacher(): bool
    {
        return in_array($this->role, ['teacher', 'company_admin', 'system_admin']);
    }

    /**
     * Check if user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get today's attendance.
     */
    public function todayAttendance()
    {
        return $this->attendances()->where('attendance_date', today())->first();
    }

    /**
     * Check if user has attended today.
     */
    public function hasAttendedToday(): bool
    {
        return $this->attendances()
            ->where('attendance_date', today())
            ->where('status', 'present')
            ->exists();
    }

    /**
     * Update study time.
     */
    public function updateStudyTime(int $minutes): void
    {
        $this->increment('total_study_hours', $minutes);
    }

    /**
     * Update consecutive days.
     */
    public function updateConsecutiveDays(): void
    {
        $yesterday = $this->attendances()
            ->where('attendance_date', now()->subDay()->toDateString())
            ->where('status', 'present')
            ->exists();

        if ($yesterday) {
            $this->increment('consecutive_days');
        } else {
            $this->update(['consecutive_days' => 1]);
        }
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for students.
     */
    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }

    /**
     * Scope for teachers.
     */
    public function scopeTeachers($query)
    {
        return $query->whereIn('role', ['teacher', 'company_admin']);
    }

    /**
     * Terminate all active sessions except current.
     */
    public function terminateOtherSessions(string $currentSessionId): void
    {
        $this->sessions()
            ->where('session_id', '!=', $currentSessionId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}