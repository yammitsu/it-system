<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'company_id', 'event_type', 'model_type', 'model_id',
        'action', 'description', 'old_values', 'new_values', 'ip_address',
        'user_agent', 'session_id', 'request_method', 'request_url',
        'request_data', 'response_code', 'execution_time', 'metadata'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'request_data' => 'array',
        'metadata' => 'array',
        'response_code' => 'integer',
        'execution_time' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function log(array $data): self
    {
        $data['ip_address'] = request()->ip();
        $data['user_agent'] = request()->userAgent();
        $data['session_id'] = session()->getId();
        $data['request_method'] = request()->method();
        $data['request_url'] = request()->fullUrl();
        
        if (auth()->check()) {
            $data['user_id'] = auth()->id();
            $data['company_id'] = auth()->user()->company_id;
        }

        return self::create($data);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}