<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'driver_id',
        'make',
        'model',
        'year',
        'color',
        'license_plate',
        'total_seats',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }
} 