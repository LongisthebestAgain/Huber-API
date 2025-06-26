<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAchievement extends Model
{
    protected $fillable = [
        'driver_id',
        'achievement_id',
        'date_earned',
    ];

    protected $casts = [
        'date_earned' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
} 