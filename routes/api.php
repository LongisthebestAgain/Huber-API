<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\DriverController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\UserPreferencesController;
use App\Http\Controllers\API\EmergencyContactController;
use App\Http\Controllers\API\VehicleController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\AchievementController;
use App\Http\Controllers\API\ContentController;
use App\Http\Controllers\API\DriverDetailsController;
use App\Http\Controllers\API\RideController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public content routes
Route::prefix('content')->group(function () {
    Route::get('/testimonials', [ContentController::class, 'getTestimonials']);
    Route::get('/faqs', [ContentController::class, 'getFAQs']);
    Route::get('/faq-categories', [ContentController::class, 'getFAQCategories']);
});

// Public achievements
Route::get('/achievements', [AchievementController::class, 'index']);

// Public ride routes (for browsing without authentication)
Route::prefix('rides')->group(function () {
    Route::get('/', [RideController::class, 'index']);
    Route::get('/search', [RideController::class, 'search']);
    Route::get('/{ride}', [RideController::class, 'show']);
    Route::get('/{ride}/seats', [RideController::class, 'getAvailableSeats']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // User routes
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/profile/photo', [UserController::class, 'uploadProfilePhoto']);
        Route::delete('/profile/photo', [UserController::class, 'deleteProfilePhoto']);
        Route::get('/bookings', [UserController::class, 'getBookings']);
        Route::get('/rides', [UserController::class, 'getRideHistory']);
        Route::put('/notification-preferences', [UserController::class, 'updateNotificationPreferences']);
    });

    // User Preferences routes
    Route::prefix('preferences')->group(function () {
        Route::get('/', [UserPreferencesController::class, 'show']);
        Route::put('/', [UserPreferencesController::class, 'update']);
    });

    // Emergency Contacts routes
    Route::prefix('emergency-contacts')->group(function () {
        Route::get('/', [EmergencyContactController::class, 'index']);
        Route::post('/', [EmergencyContactController::class, 'store']);
        Route::put('/{contact}', [EmergencyContactController::class, 'update']);
        Route::delete('/{contact}', [EmergencyContactController::class, 'destroy']);
    });

    // Authenticated ride routes
    Route::prefix('rides')->group(function () {
        Route::post('/{ride}/reserve', [RideController::class, 'reserveSeats']);
    });

    // Driver routes
    Route::prefix('driver')->middleware('role:driver')->group(function () {
        Route::get('/profile', [DriverController::class, 'getProfile']);
        Route::put('/profile', [DriverController::class, 'updateProfile']);
        Route::get('/statistics', [DriverController::class, 'getStatistics']);
        Route::put('/vehicle', [DriverController::class, 'updateVehicle']);
        Route::post('/location', [DriverController::class, 'updateLocation']);
        Route::get('/rides', [DriverController::class, 'getRides']);
        Route::put('/rides/{ride}/status', [DriverController::class, 'updateRideStatus']);
        Route::post('/rides', [DriverController::class, 'createRide']);
        Route::get('/stats', [DriverController::class, 'getStats']);
        Route::post('/documents/upload', [DriverController::class, 'uploadDocument']);
    });

    // Driver Details routes
    Route::prefix('driver-details')->group(function () {
        Route::get('/{driverId?}', [DriverDetailsController::class, 'show']);
        Route::put('/', [DriverDetailsController::class, 'update'])->middleware('role:driver');
        Route::get('/stats/{driverId?}', [DriverDetailsController::class, 'getDriverStats']);
        
        // Document management
        Route::post('/documents', [DriverDetailsController::class, 'uploadDocument'])->middleware('role:driver');
        Route::get('/documents', [DriverDetailsController::class, 'getDocuments'])->middleware('role:driver');
        Route::put('/documents/{document}/verify', [DriverDetailsController::class, 'verifyDocument'])->middleware('role:admin');
    });

    // Vehicle routes
    Route::prefix('vehicles')->middleware('role:driver')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/{vehicle}', [VehicleController::class, 'show']);
        Route::put('/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy']);
    });

    // Booking routes
    Route::prefix('bookings')->group(function () {
        Route::post('/', [BookingController::class, 'create']);
        Route::get('/{booking}', [BookingController::class, 'show']);
        Route::put('/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/{booking}/review', [BookingController::class, 'addReview']);
    });

    // Transaction routes
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{transaction}', [TransactionController::class, 'show']);
        Route::post('/payment-intent', [TransactionController::class, 'createPaymentIntent']);
        
        // Saved payment methods
        Route::get('/payment-methods', [TransactionController::class, 'getSavedPaymentMethods']);
        Route::post('/payment-methods', [TransactionController::class, 'savePaymentMethod']);
        Route::delete('/payment-methods/{paymentMethod}', [TransactionController::class, 'deleteSavedPaymentMethod']);
    });

    // Payment routes (legacy support)
    Route::prefix('payments')->group(function () {
        Route::post('/intent', [PaymentController::class, 'createPaymentIntent']);
        Route::post('/confirm', [PaymentController::class, 'confirmPayment']);
        Route::post('/refund', [PaymentController::class, 'processRefund']);
    });

    // Achievement routes
    Route::prefix('achievements')->group(function () {
        Route::get('/driver/{driverId?}', [AchievementController::class, 'getDriverAchievements']);
        Route::post('/award', [AchievementController::class, 'awardAchievement'])->middleware('role:admin');
    });

    // Admin content management routes
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::post('/testimonials', [ContentController::class, 'createTestimonial']);
        
        Route::prefix('faqs')->group(function () {
            Route::post('/', [ContentController::class, 'createFAQ']);
            Route::put('/{faq}', [ContentController::class, 'updateFAQ']);
            Route::delete('/{faq}', [ContentController::class, 'deleteFAQ']);
        });
    });
}); 