<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicles = auth()->user()->vehicles;

        return response()->json([
            'success' => true,
            'data' => $vehicles
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1950|max:' . (date('Y') + 1),
            'color' => 'required|string|max:50',
            'license_plate' => 'required|string|max:50|unique:vehicles',
            'total_seats' => 'required|integer|min:1|max:50',
            'features' => 'sometimes|array',
        ]);

        $vehicle = auth()->user()->vehicles()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'data' => $vehicle
        ], 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json([
            'success' => true,
            'data' => $vehicle
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $request->validate([
            'make' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1950|max:' . (date('Y') + 1),
            'color' => 'sometimes|string|max:50',
            'license_plate' => 'sometimes|string|max:50|unique:vehicles,license_plate,' . $vehicle->id,
            'total_seats' => 'sometimes|integer|min:1|max:50',
            'features' => 'sometimes|array',
        ]);

        $vehicle->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle
        ]);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully'
        ]);
    }
} 