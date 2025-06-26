<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'exists:bookings,id'],
            'payment_method' => ['required', 'string', 'in:card,paypal'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking = Booking::with('payment')
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->booking_id);

        if ($booking->payment->status !== 'pending') {
            return response()->json([
                'message' => 'Payment has already been processed'
            ], 400);
        }

        try {
            // Here you would integrate with your payment provider (e.g., Stripe)
            // This is a placeholder for the actual payment integration
            $paymentIntent = [
                'id' => 'pi_' . uniqid(),
                'amount' => $booking->total_amount * 100, // Convert to cents
                'currency' => 'usd',
                'payment_method_types' => [$request->payment_method],
            ];

            $booking->payment->update([
                'payment_method' => $request->payment_method,
                'payment_intent_id' => $paymentIntent['id'],
                'payment_details' => $paymentIntent,
            ]);

            return response()->json([
                'message' => 'Payment intent created',
                'client_secret' => 'dummy_client_secret_' . uniqid(),
                'payment' => $booking->payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent'
            ], 500);
        }
    }

    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'exists:bookings,id'],
            'payment_intent_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking = Booking::with(['payment', 'ride'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->booking_id);

        if ($booking->payment->status !== 'pending') {
            return response()->json([
                'message' => 'Payment has already been processed'
            ], 400);
        }

        if ($booking->payment->payment_intent_id !== $request->payment_intent_id) {
            return response()->json([
                'message' => 'Invalid payment intent'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Here you would confirm the payment with your payment provider
            // This is a placeholder for the actual payment confirmation

            // Update payment status
            $booking->payment->update([
                'status' => 'completed',
            ]);

            // Update booking status
            $booking->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment processed successfully',
                'booking' => $booking->load(['ride.driver', 'payment'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process payment'
            ], 500);
        }
    }

    public function getPaymentStatus(Request $request, Payment $payment)
    {
        // Check if the payment belongs to the user's booking
        $booking = Booking::where('user_id', $request->user()->id)
            ->where('id', $payment->booking_id)
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'payment' => $payment->load('booking')
        ]);
    }

    public function refund(Request $request, Payment $payment)
    {
        // Check if the payment belongs to the user's booking
        $booking = Booking::where('user_id', $request->user()->id)
            ->where('id', $payment->booking_id)
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$payment->isRefundable()) {
            return response()->json([
                'message' => 'Payment is not refundable'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Here you would process the refund with your payment provider
            // This is a placeholder for the actual refund processing

            $payment->update([
                'status' => 'refunded',
                'refund_reason' => 'Refund requested by user'
            ]);

            $booking->update([
                'status' => 'cancelled',
                'payment_status' => 'refunded'
            ]);

            // Return seats to ride
            $booking->ride->increment('available_seats', $booking->seats_booked);

            DB::commit();

            return response()->json([
                'message' => 'Refund processed successfully',
                'payment' => $payment->load('booking')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process refund'
            ], 500);
        }
    }
} 