<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get admin dashboard data
     */
    public function getDashboard(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_drivers' => User::where('role', 'driver')->count(),
            'total_passengers' => User::where('role', 'passenger')->count(),
            'active_rides' => Ride::where('status', 'active')->count(),
            'pending_verifications' => User::where('role', 'driver')
                ->where('verification_status', 'pending')->count(),
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'monthly_growth' => $this->getMonthlyGrowth(),
        ];

        $recentActivity = $this->getRecentActivity();
        $systemAlerts = $this->getSystemAlerts();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'system_alerts' => $systemAlerts,
            ],
            'message' => 'Dashboard data retrieved successfully'
        ]);
    }

    /**
     * Get all users with filtering and pagination
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->with(['driverProfile', 'bookings', 'rides'])
            ->paginate($request->get('per_page', 15));

        // Transform data
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'avatar' => $user->profile_photo_url,
                'verification_status' => $user->verification_status,
                'total_rides' => $user->role === 'driver' 
                    ? $user->rides->count() 
                    : $user->bookings->count(),
                'total_earnings' => $user->role === 'driver' 
                    ? $user->rides->sum('price_per_seat') 
                    : null,
                'rating' => $user->rating,
                'joined_at' => $user->created_at->format('M d, Y'),
                'last_active' => $user->updated_at->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Users retrieved successfully'
        ]);
    }

    /**
     * Get all drivers with their verification status
     */
    public function getAllDrivers(Request $request): JsonResponse
    {
        $query = User::where('role', 'driver')->with('driverProfile');

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        $drivers = $query->paginate($request->get('per_page', 15));

        $drivers->getCollection()->transform(function ($driver) {
            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'avatar' => $driver->profile_photo_url,
                'verification_status' => $driver->verification_status,
                'license_number' => $driver->driverProfile->license_number ?? null,
                'vehicle_info' => [
                    'make' => $driver->driverProfile->vehicle_make ?? null,
                    'model' => $driver->driverProfile->vehicle_model ?? null,
                    'year' => $driver->driverProfile->vehicle_year ?? null,
                    'color' => $driver->driverProfile->vehicle_color ?? null,
                ],
                'total_rides' => $driver->rides->count(),
                'rating' => $driver->rating,
                'status' => $driver->status,
                'joined_at' => $driver->created_at->format('M d, Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $drivers,
            'message' => 'Drivers retrieved successfully'
        ]);
    }

    /**
     * Get all rides for admin monitoring
     */
    public function getAllRides(Request $request): JsonResponse
    {
        $query = Ride::with(['driver.user', 'bookings']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('departure_time', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('departure_time', '<=', $request->date_to);
        }

        $rides = $query->paginate($request->get('per_page', 15));

        $rides->getCollection()->transform(function ($ride) {
            return [
                'id' => $ride->id,
                'driver' => [
                    'name' => $ride->driver->user->name,
                    'email' => $ride->driver->user->email,
                    'phone' => $ride->driver->user->phone,
                ],
                'route' => [
                    'from' => $ride->pickup_location,
                    'to' => $ride->destination,
                ],
                'departure_time' => $ride->departure_time,
                'price_per_seat' => $ride->price_per_seat,
                'total_seats' => $ride->available_seats,
                'booked_seats' => $ride->bookings->sum('seats_booked'),
                'total_revenue' => $ride->bookings->sum('total_amount'),
                'status' => $ride->status,
                'passengers_count' => $ride->bookings->count(),
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
     * Update user status
     */
    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended,banned'
        ]);

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$request->status} successfully"
        ]);
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete admin users'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get driver applications pending verification
     */
    public function getDriverApplications(): JsonResponse
    {
        $applications = User::where('role', 'driver')
            ->where('verification_status', 'pending')
            ->with('driverProfile')
            ->get();

        $applicationsData = $applications->map(function ($driver) {
            return [
                'id' => $driver->id,
                'application_id' => 'DRV-' . date('Y') . '-' . str_pad($driver->id, 3, '0', STR_PAD_LEFT),
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'avatar' => $driver->profile_photo_url,
                'license_number' => $driver->driverProfile->license_number ?? null,
                'license_expiry' => $driver->driverProfile->license_expiry ?? null,
                'vehicle_info' => [
                    'make' => $driver->driverProfile->vehicle_make ?? null,
                    'model' => $driver->driverProfile->vehicle_model ?? null,
                    'year' => $driver->driverProfile->vehicle_year ?? null,
                    'color' => $driver->driverProfile->vehicle_color ?? null,
                    'license_plate' => $driver->driverProfile->license_plate ?? null,
                ],
                'documents' => [
                    'license_verified' => !empty($driver->driverProfile->license_document),
                    'registration_verified' => !empty($driver->driverProfile->vehicle_registration),
                    'insurance_verified' => !empty($driver->driverProfile->insurance_document),
                ],
                'submitted_at' => $driver->created_at,
                'status' => $driver->verification_status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $applicationsData,
            'message' => 'Driver applications retrieved successfully'
        ]);
    }

    /**
     * Approve driver application
     */
    public function approveDriver(Request $request, User $driver): JsonResponse
    {
        if ($driver->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'User is not a driver'
            ], 400);
        }

        $driver->update([
            'verification_status' => 'approved',
            'status' => 'active'
        ]);

        // Send notification email (would implement notification service)

        return response()->json([
            'success' => true,
            'message' => 'Driver application approved successfully'
        ]);
    }

    /**
     * Reject driver application
     */
    public function rejectDriver(Request $request, User $driver): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $driver->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Driver application rejected successfully'
        ]);
    }

    /**
     * Request more information from driver
     */
    public function requestMoreInfo(Request $request, User $driver): JsonResponse
    {
        $request->validate([
            'requested_info' => 'required|string|max:1000'
        ]);

        $driver->update([
            'verification_status' => 'info_requested',
            'requested_info' => $request->requested_info
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Information request sent to driver successfully'
        ]);
    }

    /**
     * Get system statistics
     */
    public function getSystemStatistics(): JsonResponse
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'rides' => [
                'total' => Ride::count(),
                'active' => Ride::where('status', 'active')->count(),
                'completed' => Ride::where('status', 'completed')->count(),
                'cancelled' => Ride::where('status', 'cancelled')->count(),
            ],
            'bookings' => [
                'total' => Booking::count(),
                'confirmed' => Booking::where('status', 'confirmed')->count(),
                'pending' => Booking::where('status', 'pending')->count(),
                'cancelled' => Booking::where('status', 'cancelled')->count(),
            ],
            'revenue' => [
                'total' => Payment::where('status', 'completed')->sum('amount'),
                'this_month' => Payment::where('status', 'completed')
                    ->whereMonth('created_at', now()->month)->sum('amount'),
                'last_month' => Payment::where('status', 'completed')
                    ->whereMonth('created_at', now()->subMonth()->month)->sum('amount'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'System statistics retrieved successfully'
        ]);
    }

    /**
     * Get monthly growth statistics
     */
    private function getMonthlyGrowth(): array
    {
        $currentMonth = User::whereMonth('created_at', now()->month)->count();
        $lastMonth = User::whereMonth('created_at', now()->subMonth()->month)->count();
        
        $growth = $lastMonth > 0 ? (($currentMonth - $lastMonth) / $lastMonth) * 100 : 0;

        return [
            'users' => round($growth, 1),
            'rides' => 8.5, // Mock data - would calculate similarly
            'revenue' => 15.2, // Mock data - would calculate similarly
        ];
    }

    /**
     * Get recent user activity
     */
    private function getRecentActivity(): array
    {
        return User::with(['rides', 'bookings'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->profile_photo_url,
                        'role' => $user->role,
                    ],
                    'activity' => $user->role === 'driver' ? 'Created ride' : 'Booked ride',
                    'time' => $user->updated_at->diffForHumans(),
                    'status' => $user->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get system alerts
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        $pendingVerifications = User::where('role', 'driver')
            ->where('verification_status', 'pending')->count();

        if ($pendingVerifications > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$pendingVerifications} driver applications pending verification",
                'action' => 'review_drivers',
            ];
        }

        $suspendedUsers = User::where('status', 'suspended')->count();
        if ($suspendedUsers > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$suspendedUsers} users currently suspended",
                'action' => 'review_suspensions',
            ];
        }

        return $alerts;
    }
} 