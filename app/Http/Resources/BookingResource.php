<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->user),
            'ride' => new RideResource($this->whenLoaded('ride')),
            'number_of_seats' => $this->number_of_seats,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'special_requests' => $this->special_requests,
            'rating' => $this->rating,
            'review' => $this->review,
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 