<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ride;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DriverController extends Controller
{
    public function getProfile(Request $request)
    {
        $driver = $request->user()->load([
            'rides' => function($query) {
                $query->latest()->take(5);
            },
            'reviews' => function($query) {
                $query->with('user:id,name,profile_photo')
                    ->latest()
                    ->take(5);
            }
        ]);

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        return response()->json([
            'driver' => $driver
        ]);
    }

    public function updateProfile(Request $request)
    {
        $driver = $request->user();

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'location' => ['sometimes', 'string', 'max:255'],
            'languages' => ['sometimes', 'array'],
            'languages.*' => ['string'],
            'bio' => ['sometimes', 'string', 'max:500'],
            'profile_photo' => ['sometimes', 'image', 'max:5120'], // 5MB max
            'license_number' => ['sometimes', 'string', 'max:50'],
            'vehicle_info' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            if ($driver->profile_photo) {
                Storage::delete($driver->profile_photo);
            }
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $driver->profile_photo = $path;
        }

        $driver->fill($request->only([
            'name',
            'phone',
            'location',
            'languages',
            'bio',
            'license_number',
            'vehicle_info',
        ]));

        $driver->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'driver' => $driver
        ]);
    }

    public function getStatistics(Request $request)
    {
        $driver = $request->user();
        $now = Carbon::now();
        
        // Calculate completion rate
        $totalRides = $driver->rides()->count();
        $completedRides = $driver->rides()->where('status', 'completed')->count();
        $completionRate = $totalRides > 0 ? ($completedRides / $totalRides) * 100 : 0;

        // Calculate earnings
        $totalEarnings = $driver->rides()->where('status', 'completed')->sum('earnings');
        $monthlyEarnings = $driver->rides()
            ->where('status', 'completed')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('earnings');
        $weeklyEarnings = $driver->rides()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()])
            ->sum('earnings');

        // Get recent reviews
        $recentReviews = $driver->reviews()
            ->with('user:id,name,profile_photo')
            ->latest()
            ->take(5)
            ->get();

        // Calculate rating statistics
        $ratings = $driver->reviews()->select('rating')->get();
        $ratingStats = [
            'average' => $ratings->avg('rating'),
            'total' => $ratings->count(),
            'distribution' => [
                5 => $ratings->where('rating', 5)->count(),
                4 => $ratings->where('rating', 4)->count(),
                3 => $ratings->where('rating', 3)->count(),
                2 => $ratings->where('rating', 2)->count(),
                1 => $ratings->where('rating', 1)->count(),
            ]
        ];

        $stats = [
            'rating' => $ratingStats,
            'total_rides' => $totalRides,
            'completed_rides' => $completedRides,
            'completion_rate' => round($completionRate, 2),
            'earnings' => [
                'total' => $totalEarnings,
                'monthly' => $monthlyEarnings,
                'weekly' => $weeklyEarnings,
                'currency' => 'USD'
            ],
            'recent_reviews' => $recentReviews,
            'vehicle_details' => $driver->vehicle_details,
            'languages' => $driver->languages ?? [],
            'member_since' => $driver->created_at->format('F Y'),
        ];

        return response()->json(['statistics' => $stats]);
    }

    public function updateVehicle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'make' => ['required', 'string'],
            'model' => ['required', 'string'],
            'year' => ['required', 'integer', 'min:2000', 'max:' . (date('Y') + 1)],
            'color' => ['required', 'string'],
            'seats' => ['required', 'integer', 'min:2', 'max:8'],
            'features' => ['array'],
            'features.*' => ['string'],
            'plate_number' => ['required', 'string'],
            'photos' => ['array', 'max:5'],
            'photos.*' => ['image', 'max:5120'], // 5MB per image
            'vehicle_type' => ['required', 'string', 'in:standard,premium,van'],
            'amenities' => ['array'],
            'amenities.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $driver = $request->user();
        
        $vehicleDetails = $request->except('photos');
        
        // Handle vehicle photos
        if ($request->hasFile('photos')) {
            // Remove old photos if they exist
            if (isset($driver->vehicle_details['photos'])) {
                foreach ($driver->vehicle_details['photos'] as $oldPhoto) {
                    Storage::delete($oldPhoto);
                }
            }

            $photos = [];
            foreach ($request->file('photos') as $photo) {
                $photos[] = $photo->store('vehicle-photos', 'public');
            }
            $vehicleDetails['photos'] = $photos;
        }

        $driver->vehicle_details = $vehicleDetails;
        $driver->save();

        return response()->json([
            'message' => 'Vehicle details updated successfully',
            'vehicle' => $driver->vehicle_details
        ]);
    }

    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['sometimes', 'numeric', 'between:0,360'],
            'speed' => ['sometimes', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $driver = $request->user();
        
        $driver->update([
            'current_latitude' => $request->latitude,
            'current_longitude' => $request->longitude,
            'current_heading' => $request->heading,
            'current_speed' => $request->speed,
            'last_location_update' => now(),
        ]);

        // If driver has an active ride, broadcast location update
        $activeRide = $driver->rides()->where('status', 'in_progress')->first();
        if ($activeRide) {
            broadcast(new DriverLocationUpdated($activeRide->id, [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'heading' => $request->heading,
                'speed' => $request->speed,
            ]));
        }

        return response()->json([
            'message' => 'Location updated successfully'
        ]);
    }

    public function getRides(Request $request)
    {
        $driver = $request->user();

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,cancelled'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date', 'after_or_equal:from_date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $driver->rides()->with(['bookings.user', 'reviews']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $rides = $query->latest()->paginate(10);

        return response()->json([
            'rides' => $rides
        ]);
    }

    public function createRide(Request $request)
    {
        $driver = $request->user();

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date', 'after:now'],
            'estimated_arrival_time' => ['required', 'date', 'after:departure_time'],
            'available_seats' => ['required', 'integer', 'min:1'],
            'price_per_seat' => ['required', 'numeric', 'min:0'],
            'vehicle_type' => ['required', 'string', 'in:economy,premium'],
            'notes' => ['sometimes', 'string', 'max:500'],
            'route_info' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ride = $driver->rides()->create([
            'origin' => $request->origin,
            'destination' => $request->destination,
            'departure_time' => $request->departure_time,
            'estimated_arrival_time' => $request->estimated_arrival_time,
            'available_seats' => $request->available_seats,
            'price_per_seat' => $request->price_per_seat,
            'vehicle_type' => $request->vehicle_type,
            'status' => 'available',
            'notes' => $request->notes,
            'route_info' => $request->route_info,
        ]);

        return response()->json([
            'message' => 'Ride created successfully',
            'ride' => $ride
        ], 201);
    }

    public function updateRideStatus(Request $request, Ride $ride)
    {
        $driver = $request->user();

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        if ($ride->driver_id !== $driver->id) {
            return response()->json([
                'message' => 'Unauthorized. This ride belongs to another driver.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:in_progress,completed,cancelled'],
            'cancellation_reason' => ['required_if:status,cancelled', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $ride->status = $request->status;
            if ($request->status === 'cancelled') {
                $ride->cancellation_reason = $request->cancellation_reason;
                $ride->cancelled_at = now();
            }
            $ride->save();

            // Update related bookings status
            if ($request->status === 'completed') {
                $ride->bookings()->where('status', 'confirmed')->update([
                    'status' => 'completed'
                ]);
                
                // Update driver stats
                $driver->increment('total_rides');
            } elseif ($request->status === 'cancelled') {
                // Handle cancellation logic
                $ride->bookings()->whereIn('status', ['pending', 'confirmed'])->update([
                    'status' => 'cancelled'
                ]);

                // Return seats
                $ride->available_seats = $ride->total_seats;
            }

            DB::commit();

            // Notify passengers about status change
            foreach ($ride->bookings as $booking) {
                broadcast(new RideStatusUpdated($booking->id, $request->status));
            }

            return response()->json([
                'message' => 'Ride status updated successfully',
                'ride' => $ride->load(['bookings.user', 'reviews'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update ride status'
            ], 500);
        }
    }

    public function getStats(Request $request)
    {
        $driver = $request->user();

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        $stats = [
            'total_rides' => $driver->total_rides,
            'rating' => $driver->rating,
            'total_earnings' => $driver->rides()
                ->whereHas('bookings', function($query) {
                    $query->whereHas('payment', function($q) {
                        $q->where('status', 'completed');
                    });
                })
                ->sum(DB::raw('price_per_seat * (total_seats - available_seats)')),
            'completed_rides' => $driver->rides()
                ->where('status', 'completed')
                ->count(),
            'cancelled_rides' => $driver->rides()
                ->where('status', 'cancelled')
                ->count(),
            'upcoming_rides' => $driver->rides()
                ->where('status', 'available')
                ->where('departure_time', '>', now())
                ->count(),
        ];

        return response()->json([
            'stats' => $stats
        ]);
    }

    public function uploadDocument(Request $request)
    {
        $driver = $request->user();

        if (!$driver->isDriver()) {
            return response()->json([
                'message' => 'Unauthorized. User is not a driver.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // 10MB max
            'document_type' => ['required', 'string', 'in:license,registration,insurance,permit'],
            'description' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create directory if it doesn't exist
            $path = $request->file('document')->store('driver-documents', 'public');
            
            // Save document record
            $document = $driver->documents()->create([
                'document_type' => $request->document_type,
                'file_path' => $path,
                'original_name' => $request->file('document')->getClientOriginalName(),
                'file_size' => $request->file('document')->getSize(),
                'mime_type' => $request->file('document')->getMimeType(),
                'description' => $request->description,
                'status' => 'pending_verification',
                'uploaded_at' => now(),
            ]);

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => [
                    'id' => $document->id,
                    'type' => $document->document_type,
                    'status' => $document->status,
                    'uploaded_at' => $document->uploaded_at,
                    'file_url' => Storage::disk('public')->url($path),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 