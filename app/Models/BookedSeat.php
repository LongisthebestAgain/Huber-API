<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookedSeat extends Model
{
    protected $fillable = [
        'booking_id',
        'ride_id',
        'seat_number',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
} 