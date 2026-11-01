<?php

use App\Http\Controllers\Api\AddressApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\CheckoutApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProfileApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EZiCart Buyer Mobile API Routes
|--------------------------------------------------------------------------
*/

$defineApiRoutes = function () {
    // Public Auth
    Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/register', [AuthApiController::class, 'register'])->middleware('throttle:registration');
    Route::post('/auth/register', [AuthApiController::class, 'register'])->middleware('throttle:registration');
    Route::post('/verify-email', [AuthApiController::class, 'verifyEmail'])->middleware('throttle:email-verification');
    Route::post('/auth/verify-email', [AuthApiController::class, 'verifyEmail'])->middleware('throttle:email-verification');
    Route::post('/resend-verification', [AuthApiController::class, 'resendVerificationCode'])->middleware('throttle:email-verification-resend');
    Route::post('/auth/resend-verification', [AuthApiController::class, 'resendVerificationCode'])->middleware('throttle:email-verification-resend');
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset-link');
    Route::post('/auth/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset-link');
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:password-reset');

    // Catalog & Products (Public)
    Route::get('/products', [ProductApiController::class, 'index']);
    Route::get('/products/{id}', [ProductApiController::class, 'show'])->whereNumber('id');
    Route::get('/categories', [ProductApiController::class, 'categories']);

    // Cart Quote (Public or Authenticated)
    Route::post('/cart/quote', [CartApiController::class, 'quote']);

    // Authenticated Buyer Only Routes
    Route::middleware('auth.api:buyer')->group(function () {
        // Current User Profile & Logout
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::get('/auth/me', [AuthApiController::class, 'me']);
        Route::post('/logout', [AuthApiController::class, 'logout']);
        Route::post('/auth/logout', [AuthApiController::class, 'logout']);

        // Profile & Security Management
        Route::put('/account/profile', [ProfileApiController::class, 'updateProfile']);
        Route::put('/account/password', [ProfileApiController::class, 'updatePassword']);

        // Checkout & Orders
        Route::post('/checkout', [CheckoutApiController::class, 'checkout'])->middleware('throttle:operational-action');
        Route::get('/orders', [OrderApiController::class, 'index']);
        Route::get('/orders/{id}', [OrderApiController::class, 'show'])->whereNumber('id');
        Route::post('/orders/{id}/confirm', [OrderApiController::class, 'confirmReceived'])->whereNumber('id');
        Route::post('/orders/{id}/cancel', [OrderApiController::class, 'cancel'])->whereNumber('id');
        Route::post('/orders/{id}/delivery-code/refresh', [OrderApiController::class, 'refreshDeliveryCode'])->whereNumber('id');
        Route::post('/orders/{id}/review', [OrderApiController::class, 'review'])->whereNumber('id');

        // Address Management
        Route::get('/addresses', [AddressApiController::class, 'index']);
        Route::post('/addresses', [AddressApiController::class, 'store']);
        Route::put('/addresses/{id}', [AddressApiController::class, 'update'])->whereNumber('id');
        Route::delete('/addresses/{id}', [AddressApiController::class, 'destroy'])->whereNumber('id');
        Route::patch('/addresses/{id}/default', [AddressApiController::class, 'makeDefault'])->whereNumber('id');

        // Chat & Messaging
        Route::get('/chat/threads', [ChatApiController::class, 'threads']);
        Route::get('/chat/{conversation}', [ChatApiController::class, 'show'])->whereNumber('conversation');
        Route::post('/chat/{conversation}/messages', [ChatApiController::class, 'sendMessage'])->whereNumber('conversation');
        Route::post('/chat/start/{sellerId}', [ChatApiController::class, 'start'])->whereNumber('sellerId');
    });
};

// Mount both at /v1 and root of API
Route::prefix('v1')->group($defineApiRoutes);
$defineApiRoutes();

