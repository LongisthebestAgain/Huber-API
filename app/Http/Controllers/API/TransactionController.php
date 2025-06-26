<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\UserSavedPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function index(): JsonResponse
    {
        $transactions = auth()->user()->transactions()
            ->with(['booking.ride'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        $transaction->load(['booking.ride', 'user']);

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:card,wallet,bank',
            'amount' => 'required|numeric|min:0.01',
            'saved_payment_method_id' => 'sometimes|exists:user_saved_payment_methods,id',
        ]);

        // Here you would integrate with your payment gateway
        // For now, we'll create a mock transaction

        $transaction = Transaction::create([
            'booking_id' => $request->booking_id,
            'user_id' => auth()->id(),
            'total_amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_status' => 'successful', // In real implementation, this would be 'pending'
            'gateway_token' => 'mock_token_' . uniqid(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
            'data' => $transaction
        ]);
    }

    public function getSavedPaymentMethods(): JsonResponse
    {
        $paymentMethods = auth()->user()->savedPaymentMethods;

        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    public function savePaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'gateway_token' => 'required|string',
            'description' => 'required|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        // If this is set as default, make sure no other method is default
        if ($request->is_default) {
            auth()->user()->savedPaymentMethods()->update(['is_default' => false]);
        }

        $paymentMethod = auth()->user()->savedPaymentMethods()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment method saved successfully',
            'data' => $paymentMethod
        ]);
    }

    public function deleteSavedPaymentMethod(UserSavedPaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('delete', $paymentMethod);

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully'
        ]);
    }
} 