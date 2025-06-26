<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RideController extends Controller
{
    /**
     * Search and filter rides
     */
    public function search(Request $request): JsonResponse
    {
        $query = Ride::with(['driver.user', 'bookings'])
            ->where('status', 'active')
            ->where('departure_time', '>', now());

        // Filter by locations
        if ($request->filled('from')) {
            $query->where('pickup_location', 'LIKE', '%' . $request->from . '%');
        }

        if ($request->filled('to')) {
            $query->where('destination', 'LIKE', '%' . $request->to . '%');
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price_per_seat', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_seat', '<=', $request->max_price);
        }

        // Filter by ride type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('ride_type', $request->type);
        }

        // Filter by time of day
        if ($request->filled('time_filter')) {
            switch ($request->time_filter) {
                case 'morning':
                    $query->whereTime('departure_time', '>=', '06:00:00')
                          ->whereTime('departure_time', '<', '12:00:00');
                    break;
                case 'afternoon':
                    $query->whereTime('departure_time', '>=', '12:00:00')
                          ->whereTime('departure_time', '<', '18:00:00');
                    break;
                case 'evening':
                    $query->whereTime('departure_time', '>=', '18:00:00')
                          ->whereTime('departure_time', '<', '24:00:00');
                    break;
            }
        }

        // Sort results
        $sortBy = $request->get('sort_by', 'price_low');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price_per_seat', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price_per_seat', 'desc');
                break;
            case 'time_early':
                $query->orderBy('departure_time', 'asc');
                break;
            case 'rating':
                $query->leftJoin('users', 'rides.driver_id', '=', 'users.id')
                      ->orderBy('users.rating', 'desc');
                break;
            default:
                $query->orderBy('departure_time', 'asc');
        }

        $rides = $query->paginate($request->get('per_page', 10));

        // Transform data for frontend
        $rides->getCollection()->transform(function ($ride) {
            return [
                'id' => $ride->id,
                'driver' => [
                    'id' => $ride->driver->user->id,
                    'name' => $ride->driver->user->name,
                    'avatar' => $ride->driver->user->profile_photo_url,
                    'rating' => $ride->driver->user->rating ?? 4.5,
                    'total_rides' => $ride->driver->total_rides ?? 0,
                ],
                'vehicle' => [
                    'make' => $ride->driver->vehicle_make,
                    'model' => $ride->driver->vehicle_model,
                    'year' => $ride->driver->vehicle_year,
                    'color' => $ride->driver->vehicle_color,
                    'license_plate' => $ride->driver->license_plate,
                ],
                'route' => [
                    'from' => $ride->pickup_location,
                    'to' => $ride->destination,
                    'departure_time' => $ride->departure_time->format('H:i'),
                    'departure_date' => $ride->departure_time->format('Y-m-d'),
                    'estimated_duration' => $ride->estimated_duration,
                ],
                'pricing' => [
                    'price_per_seat' => $ride->price_per_seat,
                    'total_price' => $ride->price_per_seat,
                    'currency' => 'USD',
                ],
                'seats' => [
                    'total' => $ride->available_seats,
                    'booked' => $ride->bookings->sum('seats_booked'),
                    'available' => $ride->available_seats - $ride->bookings->sum('seats_booked'),
                ],
                'ride_type' => $ride->ride_type,
                'status' => $ride->status,
                'amenities' => $ride->amenities ? json_decode($ride->amenities) : [],
                'created_at' => $ride->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rides,
            'message' => 'Rides retrieved successfully'
        ]);
    }

    /**
     * Get all available rides
     */
    public function index(Request $request): JsonResponse
    {
        return $this->search($request);
    }

    /**
     * Get specific ride details
     */
    public function show(Ride $ride): JsonResponse
    {
        $ride->load(['driver.user', 'bookings.user']);

        $rideData = [
            'id' => $ride->id,
            'driver' => [
                'id' => $ride->driver->user->id,
                'name' => $ride->driver->user->name,
                'avatar' => $ride->driver->user->profile_photo_url,
                'rating' => $ride->driver->user->rating ?? 4.5,
                'total_rides' => $ride->driver->total_rides ?? 0,
                'phone' => $ride->driver->user->phone,
            ],
            'vehicle' => [
                'make' => $ride->driver->vehicle_make,
                'model' => $ride->driver->vehicle_model,
                'year' => $ride->driver->vehicle_year,
                'color' => $ride->driver->vehicle_color,
                'license_plate' => $ride->driver->license_plate,
            ],
            'route' => [
                'from' => $ride->pickup_location,
                'to' => $ride->destination,
                'departure_time' => $ride->departure_time,
                'estimated_duration' => $ride->estimated_duration,
                'distance' => $ride->distance,
            ],
            'pricing' => [
                'base_fare' => $ride->base_fare ?? 15.00,
                'price_per_seat' => $ride->price_per_seat,
                'service_fee' => $ride->service_fee ?? 2.50,
                'currency' => 'USD',
            ],
            'seats' => [
                'total' => $ride->available_seats,
                'booked' => $ride->bookings->sum('seats_booked'),
                'available' => $ride->available_seats - $ride->bookings->sum('seats_booked'),
                'passengers' => $ride->bookings->map(function ($booking) {
                    return [
                        'name' => $booking->user->name,
                        'seats' => $booking->seats_booked,
                        'status' => $booking->status,
                    ];
                }),
            ],
            'ride_type' => $ride->ride_type,
            'status' => $ride->status,
            'amenities' => $ride->amenities ? json_decode($ride->amenities) : [],
            'notes' => $ride->notes,
            'created_at' => $ride->created_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $rideData,
            'message' => 'Ride details retrieved successfully'
        ]);
    }

    /**
     * Get available seats for a ride
     */
    public function getAvailableSeats(Ride $ride): JsonResponse
    {
        $bookedSeats = $ride->bookings()
            ->where('status', '!=', 'cancelled')
            ->sum('seats_booked');

        $availableSeats = $ride->available_seats - $bookedSeats;

        // Generate seat layout (assuming 4-seat layout for shared rides)
        $seatLayout = [];
        for ($i = 1; $i <= $ride->available_seats; $i++) {
            $seatLayout[] = [
                'seat_number' => $i,
                'position' => $this->getSeatPosition($i),
                'status' => $i <= $bookedSeats ? 'occupied' : 'available',
                'price' => $ride->price_per_seat,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ride_id' => $ride->id,
                'total_seats' => $ride->available_seats,
                'booked_seats' => $bookedSeats,
                'available_seats' => $availableSeats,
                'price_per_seat' => $ride->price_per_seat,
                'seat_layout' => $seatLayout,
                'vehicle_info' => [
                    'make' => $ride->driver->vehicle_make,
                    'model' => $ride->driver->vehicle_model,
                    'color' => $ride->driver->vehicle_color,
                ],
            ],
            'message' => 'Seat information retrieved successfully'
        ]);
    }

    /**
     * Reserve seats for a ride
     */
    public function reserveSeats(Request $request, Ride $ride): JsonResponse
    {
        $request->validate([
            'seats' => 'required|integer|min:1',
            'passenger_details' => 'sometimes|array',
        ]);

        $requestedSeats = $request->seats;
        $currentBookings = $ride->bookings()
            ->where('status', '!=', 'cancelled')
            ->sum('seats_booked');

        $availableSeats = $ride->available_seats - $currentBookings;

        if ($requestedSeats > $availableSeats) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough seats available',
                'available_seats' => $availableSeats
            ], 400);
        }

        // Create booking record
        $booking = new Booking([
            'user_id' => Auth::id(),
            'ride_id' => $ride->id,
            'seats_booked' => $requestedSeats,
            'total_amount' => $requestedSeats * $ride->price_per_seat,
            'status' => 'pending',
            'passenger_details' => $request->passenger_details ? json_encode($request->passenger_details) : null,
        ]);

        $booking->save();

        return response()->json([
            'success' => true,
            'data' => [
                'booking_id' => $booking->id,
                'seats_reserved' => $requestedSeats,
                'total_amount' => $booking->total_amount,
                'status' => $booking->status,
                'expires_at' => now()->addMinutes(15), // 15 minutes to complete payment
            ],
            'message' => 'Seats reserved successfully'
        ]);
    }

    /**
     * Get seat position for layout
     */
    private function getSeatPosition(int $seatNumber): array
    {
        // Simple 4-seat layout: front-left, front-right, back-left, back-right
        $positions = [
            1 => ['row' => 'front', 'side' => 'left'],
            2 => ['row' => 'front', 'side' => 'right'],
            3 => ['row' => 'back', 'side' => 'left'],
            4 => ['row' => 'back', 'side' => 'right'],
        ];

        return $positions[$seatNumber] ?? ['row' => 'back', 'side' => 'right'];
    }
} 