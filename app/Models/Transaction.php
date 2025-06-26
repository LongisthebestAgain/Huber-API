<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'total_amount',
        'payment_method',
        'payment_status',
        'gateway_token',
        'transaction_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 