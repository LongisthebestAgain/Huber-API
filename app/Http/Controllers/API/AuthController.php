<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationEmail;
use App\Mail\PasswordResetEmail;
use App\Models\PasswordReset;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_role' => ['required', 'string', 'in:passenger,driver'],
            'phone_number' => ['required', 'string', 'max:20'],
            // Driver specific validation
            'license_number' => ['required_if:user_role,driver', 'string', 'max:50'],
            'vehicle_info' => ['required_if:user_role,driver', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate email verification token
        $verificationToken = Str::random(64);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'user_role' => $request->user_role,
            'phone_number' => $request->phone_number,
            'member_since' => now(),
            'account_status' => 'Active',
            'email_verification_token' => $verificationToken,
            'email_verified_at' => now(), // Auto-verify for testing
        ]);

        // Create driver details if user is a driver
        if ($request->user_role === 'driver') {
            $user->driverDetails()->create([
                'license_number' => $request->license_number,
                'about_text' => $request->vehicle_info ?? '',
                'completion_rate' => 100.0,
                'average_rating' => 5.0,
            ]);
        }

        // Send verification email - DISABLED FOR TESTING
        // Mail::to($user->email)->send(new VerificationEmail($verificationToken));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'user_role' => ['required', 'string', 'in:passenger,driver'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('user_role', $request->user_role)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Skip email verification check for testing
        // if (!$user->email_verified_at) {
        //     return response()->json([
        //         'message' => 'Please verify your email first'
        //     ], 403);
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid verification token'
            ], 400);
        }

        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate reset token
        $token = Str::random(64);

        // Store token
        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );

        // Send reset email
        Mail::to($request->email)->send(new PasswordResetEmail($token));

        return response()->json([
            'message' => 'Password reset link sent to your email'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reset = PasswordReset::where([
            'email' => $request->email,
            'token' => $request->token,
        ])->first();

        if (!$reset) {
            return response()->json([
                'message' => 'Invalid reset token'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password_hash = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        PasswordReset::where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password has been reset successfully'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        if (!\Hash::check($request->old_password, $user->password_hash)) {
            return response()->json([
                'message' => 'Old password is incorrect.'
            ], 400);
        }
        $user->password_hash = \Hash::make($request->password);
        $user->save();
        return response()->json([
            'message' => 'Password changed successfully.'
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->delete();
        return response()->json([
            'message' => 'Account deleted successfully.'
        ]);
    }
} 