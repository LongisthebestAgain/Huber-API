<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'string', 'in:pending,confirmed,cancelled,completed'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date', 'after_or_equal:from_date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->user()->bookings()->with(['ride.driver', 'payment']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $bookings = $query->latest()->paginate(10);

        return response()->json([
            'bookings' => $bookings
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => ['required', 'exists:rides,id'],
            'seats_booked' => ['required', 'integer', 'min:1'],
            'special_requests' => ['sometimes', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ride = Ride::findOrFail($request->ride_id);

        if (!$ride->isAvailable()) {
            return response()->json([
                'message' => 'This ride is no longer available'
            ], 400);
        }

        if (!$ride->hasAvailableSeats($request->seats_booked)) {
            return response()->json([
                'message' => 'Not enough seats available'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $booking = Booking::create([
                'user_id' => $request->user()->id,
                'ride_id' => $ride->id,
                'seats_booked' => $request->seats_booked,
                'status' => 'pending',
                'payment_status' => 'pending',
                'special_requests' => $request->special_requests,
            ]);

            // Create pending payment record
            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $booking->total_amount,
                'status' => 'pending',
            ]);

            // Update available seats
            $ride->decrement('available_seats', $request->seats_booked);

            DB::commit();

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->load(['ride.driver', 'payment'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create booking'
            ], 500);
        }
    }

    public function show(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'booking' => $booking->load(['ride.driver', 'payment'])
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'seats_booked' => ['sometimes', 'integer', 'min:1'],
            'special_requests' => ['sometimes', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$booking->isPending()) {
            return response()->json([
                'message' => 'Booking can no longer be updated'
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($request->has('seats_booked') && $request->seats_booked !== $booking->seats_booked) {
                $ride = $booking->ride;
                $seatsDiff = $request->seats_booked - $booking->seats_booked;

                if ($seatsDiff > 0 && !$ride->hasAvailableSeats($seatsDiff)) {
                    return response()->json([
                        'message' => 'Not enough seats available'
                    ], 400);
                }

                $ride->increment('available_seats', -$seatsDiff);
                $booking->seats_booked = $request->seats_booked;
                $booking->total_amount = $booking->calculateTotalAmount();

                // Update payment amount
                $booking->payment->update([
                    'amount' => $booking->total_amount
                ]);
            }

            if ($request->has('special_requests')) {
                $booking->special_requests = $request->special_requests;
            }

            $booking->save();

            DB::commit();

            return response()->json([
                'message' => 'Booking updated successfully',
                'booking' => $booking->load(['ride.driver', 'payment'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update booking'
            ], 500);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$booking->isCancellable()) {
            return response()->json([
                'message' => 'Booking can no longer be cancelled'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Return seats to ride
            $booking->ride->increment('available_seats', $booking->seats_booked);

            // Update booking status
            $booking->status = 'cancelled';
            $booking->save();

            // Handle payment refund if necessary
            if ($booking->payment && $booking->payment->isCompleted()) {
                $booking->payment->update([
                    'status' => 'refunded',
                    'refund_reason' => 'Booking cancelled by user'
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Booking cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to cancel booking'
            ], 500);
        }
    }

    public function availableRides(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin' => ['sometimes', 'string'],
            'destination' => ['sometimes', 'string'],
            'departure_date' => ['sometimes', 'date'],
            'vehicle_type' => ['sometimes', 'string'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gt:min_price'],
            'min_seats' => ['sometimes', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Ride::with('driver')
            ->available();

        if ($request->origin) {
            $query->where('origin', 'like', '%' . $request->origin . '%');
        }

        if ($request->destination) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }

        if ($request->departure_date) {
            $query->whereDate('departure_time', $request->departure_date);
        }

        if ($request->vehicle_type) {
            $query->where('vehicle_type', $request->vehicle_type);
        }

        if ($request->min_price) {
            $query->where('price_per_seat', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price_per_seat', '<=', $request->max_price);
        }

        if ($request->min_seats) {
            $query->where('available_seats', '>=', $request->min_seats);
        }

        $rides = $query->orderBy('departure_time')->paginate(10);

        return response()->json([
            'rides' => $rides
        ]);
    }

    public function getRideDetails(Request $request, Ride $ride)
    {
        return response()->json([
            'ride' => $ride->load('driver')
        ]);
    }

    public function selectSeats(Request $request, Ride $ride)
    {
        $validator = Validator::make($request->all(), [
            'seats' => ['required', 'array'],
            'seats.*' => ['required', 'string', 'distinct'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$ride->isAvailable()) {
            return response()->json([
                'message' => 'This ride is no longer available'
            ], 400);
        }

        if (count($request->seats) > $ride->available_seats) {
            return response()->json([
                'message' => 'Not enough seats available'
            ], 400);
        }

        // Here you would implement seat selection logic
        // This is a placeholder for the actual implementation
        return response()->json([
            'message' => 'Seats selected successfully',
            'seats' => $request->seats
        ]);
    }
} 