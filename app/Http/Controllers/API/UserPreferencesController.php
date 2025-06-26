<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserPreferencesController extends Controller
{
    public function show(): JsonResponse
    {
        $preferences = auth()->user()->preferences ?? new UserPreferences([
            'preferred_language' => 'en',
            'currency' => 'usd',
            'notification_preferences' => [],
        ]);

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'preferred_language' => 'sometimes|string|max:10',
            'currency' => 'sometimes|string|max:10',
            'notification_preferences' => 'sometimes|array',
        ]);

        $user = auth()->user();
        $preferences = $user->preferences()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['preferred_language', 'currency', 'notification_preferences'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preferences
        ]);
    }
} 