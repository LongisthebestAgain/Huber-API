<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverDetails extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'profile_title',
        'about_text',
        'languages',
        'license_number',
        'license_expiry',
        'completion_rate',
        'average_rating',
    ];

    protected $casts = [
        'languages' => 'array',
        'license_expiry' => 'date',
        'completion_rate' => 'decimal:2',
        'average_rating' => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class, 'driver_id', 'user_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(DriverAchievement::class, 'driver_id', 'user_id');
    }
} 