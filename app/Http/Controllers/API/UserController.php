<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user()->load(['bookings' => function($query) {
            $query->with('ride')->latest();
        }]);

        return response()->json([
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'location' => ['sometimes', 'string', 'max:255'],
            'profile_photo' => ['sometimes', 'image', 'max:5120'], // 5MB max
            // Driver specific validation
            'license_number' => ['sometimes', 'required_if:role,driver', 'string', 'max:50'],
            'vehicle_info' => ['sometimes', 'required_if:role,driver', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::delete($user->profile_photo);
            }
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo = $path;
        }

        $user->fill($request->only([
            'name',
            'phone',
            'location',
            'license_number',
            'vehicle_info',
        ]));

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function getBookings(Request $request)
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['ride.driver', 'payment'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'bookings' => $bookings
        ]);
    }

    public function getRideHistory(Request $request)
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['ride.driver', 'payment'])
            ->where('status', 'completed')
            ->latest()
            ->paginate(10);

        return response()->json([
            'history' => $bookings
        ]);
    }

    public function deleteProfilePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->profile_photo) {
            Storage::delete($user->profile_photo);
            $user->profile_photo = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Profile photo deleted successfully'
        ]);
    }

    public function updateNotificationPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => ['required', 'boolean'],
            'push_notifications' => ['required', 'boolean'],
            'sms_notifications' => ['required', 'boolean'],
            'ride_updates' => ['required', 'boolean'],
            'promotional_emails' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->notification_preferences = $request->all();
        $user->save();

        return response()->json([
            'message' => 'Notification preferences updated successfully',
            'preferences' => $user->notification_preferences
        ]);
    }
} 