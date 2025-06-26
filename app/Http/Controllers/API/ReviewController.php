<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Create a review for a ride
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'driver_rating' => 'required|integer|min:1|max:5',
            'passenger_rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'review_type' => 'required|in:driver_review,passenger_review',
        ]);

        $ride = Ride::findOrFail($request->ride_id);
        
        // Check if user has booking for this ride
        if (Auth::user()->role === 'passenger') {
            $booking = Booking::where('ride_id', $ride->id)
                ->where('user_id', Auth::id())
                ->where('status', 'completed')
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review rides you have completed'
                ], 403);
            }

            $revieweeId = $ride->driver_id;
            $rating = $request->driver_rating;
        } else {
            // Driver reviewing passenger
            if ($ride->driver_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review your own rides'
                ], 403);
            }

            $revieweeId = $request->passenger_id ?? null;
            $rating = $request->passenger_rating;
        }

        // Check if review already exists
        $existingReview = Review::where('ride_id', $ride->id)
            ->where('reviewer_id', Auth::id())
            ->where('reviewee_id', $revieweeId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this ride'
            ], 400);
        }

        $review = Review::create([
            'ride_id' => $ride->id,
            'reviewer_id' => Auth::id(),
            'reviewee_id' => $revieweeId,
            'rating' => $rating,
            'comment' => $request->comment,
            'review_type' => $request->review_type,
        ]);

        // Update user's average rating
        $this->updateUserRating($revieweeId);

        return response()->json([
            'success' => true,
            'data' => [
                'review_id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
            ],
            'message' => 'Review submitted successfully'
        ]);
    }

    /**
     * Get reviews for a specific driver
     */
    public function getDriverReviews(User $driver): JsonResponse
    {
        if ($driver->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'User is not a driver'
            ], 400);
        }

        $reviews = Review::where('reviewee_id', $driver->id)
            ->where('review_type', 'driver_review')
            ->with(['reviewer', 'ride'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $reviewsData = $reviews->getCollection()->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer' => [
                    'name' => $review->reviewer->name,
                    'avatar' => $review->reviewer->profile_photo_url,
                ],
                'ride' => [
                    'from' => $review->ride->pickup_location,
                    'to' => $review->ride->destination,
                    'date' => $review->ride->departure_time->format('M d, Y'),
                ],
                'created_at' => $review->created_at->format('M d, Y'),
            ];
        });

        $stats = [
            'average_rating' => $driver->rating ?? 0,
            'total_reviews' => $reviews->total(),
            'rating_breakdown' => $this->getRatingBreakdown($driver->id),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviewsData,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'total_pages' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
                'stats' => $stats,
            ],
            'message' => 'Driver reviews retrieved successfully'
        ]);
    }

    /**
     * Get review history for a user
     */
    public function getUserReviews(User $user): JsonResponse
    {
        // Reviews given by the user
        $reviewsGiven = Review::where('reviewer_id', $user->id)
            ->with(['reviewee', 'ride'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Reviews received by the user
        $reviewsReceived = Review::where('reviewee_id', $user->id)
            ->with(['reviewer', 'ride'])
            ->orderBy('created_at', 'desc')
            ->get();

        $reviewsGivenData = $reviewsGiven->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewee' => [
                    'name' => $review->reviewee->name,
                    'avatar' => $review->reviewee->profile_photo_url,
                    'role' => $review->reviewee->role,
                ],
                'ride' => [
                    'from' => $review->ride->pickup_location,
                    'to' => $review->ride->destination,
                    'date' => $review->ride->departure_time->format('M d, Y'),
                ],
                'type' => $review->review_type,
                'created_at' => $review->created_at->format('M d, Y'),
            ];
        });

        $reviewsReceivedData = $reviewsReceived->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer' => [
                    'name' => $review->reviewer->name,
                    'avatar' => $review->reviewer->profile_photo_url,
                    'role' => $review->reviewer->role,
                ],
                'ride' => [
                    'from' => $review->ride->pickup_location,
                    'to' => $review->ride->destination,
                    'date' => $review->ride->departure_time->format('M d, Y'),
                ],
                'type' => $review->review_type,
                'created_at' => $review->created_at->format('M d, Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'reviews_given' => $reviewsGivenData,
                'reviews_received' => $reviewsReceivedData,
                'stats' => [
                    'average_rating_received' => $user->rating ?? 0,
                    'total_reviews_given' => $reviewsGiven->count(),
                    'total_reviews_received' => $reviewsReceived->count(),
                ],
            ],
            'message' => 'User review history retrieved successfully'
        ]);
    }

    /**
     * Get pending reviews for a user
     */
    public function getPendingReviews(): JsonResponse
    {
        $user = Auth::user();
        $pendingReviews = [];

        if ($user->role === 'passenger') {
            // Get completed rides that haven't been reviewed
            $completedBookings = Booking::where('user_id', $user->id)
                ->where('status', 'completed')
                ->with('ride.driver.user')
                ->get();

            foreach ($completedBookings as $booking) {
                $existingReview = Review::where('ride_id', $booking->ride_id)
                    ->where('reviewer_id', $user->id)
                    ->where('review_type', 'driver_review')
                    ->exists();

                if (!$existingReview) {
                    $pendingReviews[] = [
                        'ride_id' => $booking->ride_id,
                        'type' => 'driver_review',
                        'driver' => [
                            'id' => $booking->ride->driver->user->id,
                            'name' => $booking->ride->driver->user->name,
                            'avatar' => $booking->ride->driver->user->profile_photo_url,
                        ],
                        'ride' => [
                            'from' => $booking->ride->pickup_location,
                            'to' => $booking->ride->destination,
                            'date' => $booking->ride->departure_time->format('M d, Y'),
                        ],
                        'completed_at' => $booking->updated_at,
                    ];
                }
            }
        } else if ($user->role === 'driver') {
            // Get completed rides with passengers that haven't been reviewed
            $completedRides = Ride::where('driver_id', $user->id)
                ->where('status', 'completed')
                ->with(['bookings.user'])
                ->get();

            foreach ($completedRides as $ride) {
                foreach ($ride->bookings as $booking) {
                    if ($booking->status === 'completed') {
                        $existingReview = Review::where('ride_id', $ride->id)
                            ->where('reviewer_id', $user->id)
                            ->where('reviewee_id', $booking->user_id)
                            ->where('review_type', 'passenger_review')
                            ->exists();

                        if (!$existingReview) {
                            $pendingReviews[] = [
                                'ride_id' => $ride->id,
                                'type' => 'passenger_review',
                                'passenger' => [
                                    'id' => $booking->user->id,
                                    'name' => $booking->user->name,
                                    'avatar' => $booking->user->profile_photo_url,
                                ],
                                'ride' => [
                                    'from' => $ride->pickup_location,
                                    'to' => $ride->destination,
                                    'date' => $ride->departure_time->format('M d, Y'),
                                ],
                                'completed_at' => $booking->updated_at,
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $pendingReviews,
            'message' => 'Pending reviews retrieved successfully'
        ]);
    }

    /**
     * Update a review
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->reviewer_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reviews'
            ], 403);
        }

        $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $oldRating = $review->rating;
        $review->update($request->only(['rating', 'comment']));

        // Update user's average rating if rating changed
        if ($request->has('rating') && $oldRating !== $request->rating) {
            $this->updateUserRating($review->reviewee_id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'review_id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'updated_at' => $review->updated_at,
            ],
            'message' => 'Review updated successfully'
        ]);
    }

    /**
     * Delete a review
     */
    public function destroy(Review $review): JsonResponse
    {
        if ($review->reviewer_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own reviews'
            ], 403);
        }

        $revieweeId = $review->reviewee_id;
        $review->delete();

        // Update user's average rating
        $this->updateUserRating($revieweeId);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * Update user's average rating based on their reviews
     */
    private function updateUserRating(int $userId): void
    {
        $averageRating = Review::where('reviewee_id', $userId)
            ->avg('rating');

        User::where('id', $userId)->update([
            'rating' => $averageRating ? round($averageRating, 1) : null
        ]);
    }

    /**
     * Get rating breakdown for a user
     */
    private function getRatingBreakdown(int $userId): array
    {
        $breakdown = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $count = Review::where('reviewee_id', $userId)
                ->where('rating', $i)
                ->count();
            $breakdown[$i] = $count;
        }

        return $breakdown;
    }
} 