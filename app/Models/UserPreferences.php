<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreferences extends Model
{
    protected $fillable = [
        'user_id',
        'preferred_language',
        'currency',
        'notification_preferences',
    ];

    protected $casts = [
        'notification_preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 