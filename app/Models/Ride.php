<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'pickup_location',
        'dropoff_location',
        'price',
        'available_seats',
        'departure_time',
        'status',
        'route_info',
        'description'
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'route_info' => 'array',
        'price' => 'decimal:2'
    ];

    // Relationships
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function passengers()
    {
        return $this->belongsToMany(User::class, 'bookings', 'ride_id', 'user_id')
            ->withPivot(['status', 'seats_booked', 'total_amount'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')
            ->where('departure_time', '>', now())
            ->where('available_seats', '>', 0);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('departure_time', '>', now())
            ->orderBy('departure_time', 'asc');
    }

    // Helper methods
    public function isAvailable()
    {
        return $this->status === 'available' && 
               $this->departure_time > now() && 
               $this->available_seats > 0;
    }

    public function hasAvailableSeats($seats)
    {
        return $this->available_seats >= $seats;
    }

    public function getRemainingSeatsAttribute(): int
    {
        return $this->available_seats - $this->bookings()->sum('number_of_seats');
    }
} 