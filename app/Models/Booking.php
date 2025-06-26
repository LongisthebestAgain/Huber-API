<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ride_id',
        'number_of_seats',
        'total_amount',
        'status', // pending, confirmed, cancelled, completed
        'payment_status', // pending, paid, refunded
        'payment_method',
        'booking_reference',
        'special_requests',
        'rating',
        'review'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'rating' => 'integer'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isConfirmed()
    {
        return $this->status === 'confirmed';
    }

    public function isCancellable()
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
               $this->ride->departure_time > now()->addHours(2); // 2 hours cancellation policy
    }

    public function calculateTotalAmount()
    {
        return $this->number_of_seats * $this->ride->price_per_seat;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            $booking->booking_reference = 'BK' . strtoupper(uniqid());
            $booking->total_amount = $booking->calculateTotalAmount();
        });
    }
} 