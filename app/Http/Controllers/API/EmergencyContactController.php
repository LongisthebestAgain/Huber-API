<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmergencyContactController extends Controller
{
    public function index(): JsonResponse
    {
        $contacts = auth()->user()->emergencyContacts;

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'contact_name' => 'required|string|max:255',
            'relationship' => 'required|string|max:50',
            'phone_number' => 'required|string|max:50',
        ]);

        $contact = auth()->user()->emergencyContacts()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact added successfully',
            'data' => $contact
        ], 201);
    }

    public function update(Request $request, EmergencyContact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $request->validate([
            'contact_name' => 'sometimes|string|max:255',
            'relationship' => 'sometimes|string|max:50',
            'phone_number' => 'sometimes|string|max:50',
        ]);

        $contact->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact updated successfully',
            'data' => $contact
        ]);
    }

    public function destroy(EmergencyContact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact deleted successfully'
        ]);
    }
} 