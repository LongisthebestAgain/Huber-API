<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password_hash',
        'phone_number',
        'phone',
        'user_role',
        'avatar_url',
        'member_since',
        'account_status',
        'email_verification_token',
        'address',
        'date_of_birth',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'member_since' => 'datetime',
        ];
    }

    // Relationships
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreferences::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function driverDetails(): HasOne
    {
        return $this->hasOne(DriverDetails::class);
    }

    public function driverDocuments(): HasMany
    {
        return $this->hasMany(DriverDocument::class, 'driver_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function driverRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function savedPaymentMethods(): HasMany
    {
        return $this->hasMany(UserSavedPaymentMethod::class);
    }

    public function driverReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'driver_id');
    }

    public function passengerReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'passenger_id');
    }

    public function driverAchievements(): HasMany
    {
        return $this->hasMany(DriverAchievement::class, 'driver_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    // Helper methods
    public function isDriver(): bool
    {
        return $this->user_role === 'driver';
    }

    public function isPassenger(): bool
    {
        return $this->user_role === 'passenger';
    }

    public function isAdmin(): bool
    {
        return $this->user_role === 'admin';
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAverageRating()
    {
        return $this->driverDetails?->average_rating ?? 0;
    }

    public function getCompletionRate()
    {
        return $this->driverDetails?->completion_rate ?? 0;
    }

    public function getTotalEarnings()
    {
        return $this->transactions()
            ->where('payment_status', 'successful')
            ->sum('total_amount');
    }

    public function getCurrentMonthEarnings()
    {
        return $this->transactions()
            ->where('payment_status', 'successful')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');
    }
}
