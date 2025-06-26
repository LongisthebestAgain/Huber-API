<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'amount',
        'payment_method',
        'status', // pending, completed, failed, refunded
        'transaction_id',
        'payment_intent_id', // For Stripe integration
        'payment_details',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_details' => 'array',
    ];

    // Relationships
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isRefundable()
    {
        return $this->status === 'completed' &&
               $this->booking->isCancellable();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $payment->transaction_id = 'TXN' . strtoupper(uniqid());
        });
    }
} 