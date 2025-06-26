<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'payment_details' => $this->payment_details,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 