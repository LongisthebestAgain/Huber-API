<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'login_timestamp',
        'expiry_timestamp',
    ];

    protected $casts = [
        'login_timestamp' => 'datetime',
        'expiry_timestamp' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('expiry_timestamp', '>', now());
    }
} 