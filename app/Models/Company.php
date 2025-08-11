<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'address',
        'postal_code',
        'representative_name',
        'slack_workspace_id',
        'slack_workspace_name',
        'max_users',
        'contract_start_date',
        'contract_end_date',
        'status',
        'settings',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'max_users' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the users for the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the students for the company.
     */
    public function students(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'student');
    }

    /**
     * Get the teachers for the company.
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(User::class)->whereIn('role', ['teacher', 'company_admin']);
    }

    /**
     * Get the tasks for the company.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the shifts for the company.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Get the learning paths for the company.
     */
    public function learningPaths(): HasMany
    {
        return $this->hasMany(LearningPath::class);
    }

    /**
     * Get the company settings.
     */
    public function companySettings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    /**
     * Get the licenses for the company.
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(CompanyLicense::class);
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $category, string $key, $default = null)
    {
        $setting = $this->companySettings()
            ->where('category', $category)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Check if the company is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' 
            && $this->contract_end_date >= now()->toDateString();
    }

    /**
     * Check if the company has reached user limit.
     */
    public function hasReachedUserLimit(): bool
    {
        return $this->users()->count() >= $this->max_users;
    }

    /**
     * Scope for active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('contract_end_date', '>=', now()->toDateString());
    }

    /**
     * Generate unique company code.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'CP' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists());

        return $code;
    }
}