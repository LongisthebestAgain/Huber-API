<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\DriverAchievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AchievementController extends Controller
{
    public function index(): JsonResponse
    {
        $achievements = Achievement::all();

        return response()->json([
            'success' => true,
            'data' => $achievements
        ]);
    }

    public function getDriverAchievements($driverId = null): JsonResponse
    {
        $driverId = $driverId ?: auth()->id();
        
        $achievements = DriverAchievement::where('driver_id', $driverId)
            ->with('achievement')
            ->orderBy('date_earned', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $achievements
        ]);
    }

    public function awardAchievement(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'achievement_id' => 'required|exists:achievements,id',
        ]);

        // Check if achievement already exists
        $existingAchievement = DriverAchievement::where('driver_id', $request->driver_id)
            ->where('achievement_id', $request->achievement_id)
            ->first();

        if ($existingAchievement) {
            return response()->json([
                'success' => false,
                'message' => 'Achievement already awarded to this driver'
            ], 400);
        }

        $driverAchievement = DriverAchievement::create([
            'driver_id' => $request->driver_id,
            'achievement_id' => $request->achievement_id,
            'date_earned' => now(),
        ]);

        $driverAchievement->load('achievement');

        return response()->json([
            'success' => true,
            'message' => 'Achievement awarded successfully',
            'data' => $driverAchievement
        ]);
    }
} 