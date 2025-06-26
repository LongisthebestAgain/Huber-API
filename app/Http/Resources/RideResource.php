<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver' => new UserResource($this->driver),
            'pickup_location' => $this->pickup_location,
            'dropoff_location' => $this->dropoff_location,
            'price' => $this->price,
            'available_seats' => $this->available_seats,
            'remaining_seats' => $this->remaining_seats,
            'departure_time' => $this->departure_time,
            'status' => $this->status,
            'route_info' => $this->route_info,
            'description' => $this->description,
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 