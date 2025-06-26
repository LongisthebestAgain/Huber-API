<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'location' => $this->location,
            'profile_photo' => $this->profile_photo ? url('storage/' . $this->profile_photo) : null,
            'notification_preferences' => $this->notification_preferences,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Driver specific fields
            'license_number' => $this->when($this->role === 'driver', $this->license_number),
            'vehicle_info' => $this->when($this->role === 'driver', $this->vehicle_info),
            'current_location' => $this->when($this->role === 'driver', [
                'latitude' => $this->current_latitude,
                'longitude' => $this->current_longitude,
                'heading' => $this->current_heading,
                'last_update' => $this->last_location_update,
            ]),
            'stats' => $this->when($this->role === 'driver', [
                'rating' => $this->rating,
                'total_rides' => $this->total_rides,
                'completed_rides' => $this->completed_rides,
                'cancelled_rides' => $this->cancelled_rides,
            ]),
        ];
    }
} 