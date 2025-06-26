<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DriverDetails;
use App\Models\DriverDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DriverDetailsController extends Controller
{
    public function show($driverId = null): JsonResponse
    {
        $driverId = $driverId ?: auth()->id();
        
        $driverDetails = DriverDetails::with(['user', 'documents', 'achievements.achievement'])
            ->where('user_id', $driverId)
            ->first();

        if (!$driverDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Driver details not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $driverDetails
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'profile_title' => 'sometimes|string|max:255',
            'about_text' => 'sometimes|string',
            'languages' => 'sometimes|array',
            'license_number' => 'sometimes|string|max:255',
            'license_expiry' => 'sometimes|date|after:today',
        ]);

        $user = auth()->user();
        $driverDetails = $user->driverDetails()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['profile_title', 'about_text', 'languages', 'license_number', 'license_expiry'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver details updated successfully',
            'data' => $driverDetails
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => 'required|in:license,registration,insurance',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

        $file = $request->file('document');
        $path = $file->store('driver-documents', 'public');

        $document = auth()->user()->driverDocuments()->create([
            'document_type' => $request->document_type,
            'file_url' => Storage::url($path),
            'verification_status' => 'Pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $document
        ]);
    }

    public function getDocuments(): JsonResponse
    {
        $documents = auth()->user()->driverDocuments;

        return response()->json([
            'success' => true,
            'data' => $documents
        ]);
    }

    public function verifyDocument(Request $request, DriverDocument $document): JsonResponse
    {
        $request->validate([
            'verification_status' => 'required|in:Approved,Rejected,Pending',
        ]);

        $document->update([
            'verification_status' => $request->verification_status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document verification status updated',
            'data' => $document
        ]);
    }

    public function getDriverStats($driverId = null): JsonResponse
    {
        $driverId = $driverId ?: auth()->id();
        
        $driverDetails = DriverDetails::where('user_id', $driverId)->first();
        
        if (!$driverDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found'
            ], 404);
        }

        $user = $driverDetails->user;
        $totalRides = $user->driverRides()->count();
        $completedRides = $user->driverRides()->where('ride_status', 'Completed')->count();
        $totalEarnings = $user->getTotalEarnings();
        $monthlyEarnings = $user->getCurrentMonthEarnings();

        $stats = [
            'total_rides' => $totalRides,
            'completed_rides' => $completedRides,
            'completion_rate' => $driverDetails->completion_rate,
            'average_rating' => $driverDetails->average_rating,
            'total_earnings' => $totalEarnings,
            'monthly_earnings' => $monthlyEarnings,
            'achievements_count' => $user->driverAchievements()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
} 