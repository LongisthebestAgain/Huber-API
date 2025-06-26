<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver_id',
        'booking_id',
        'rating',
        'comment',
        'driver_response',
        'response_at',
    ];

    protected $casts = [
        'rating' => 'float',
        'response_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Scopes
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeWithResponse($query)
    {
        return $query->whereNotNull('driver_response');
    }

    public function scopeWithoutResponse($query)
    {
        return $query->whereNull('driver_response');
    }

    public function scopeHighRating($query, $minRating = 4.0)
    {
        return $query->where('rating', '>=', $minRating);
    }

    public function scopeLowRating($query, $maxRating = 3.0)
    {
        return $query->where('rating', '<=', $maxRating);
    }

    // Helper methods
    public function hasDriverResponse()
    {
        return !is_null($this->driver_response);
    }

    public function addDriverResponse($response)
    {
        $this->update([
            'driver_response' => $response,
            'response_at' => now(),
        ]);
    }
} 