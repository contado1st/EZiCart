<?php

use App\Http\Controllers\Api\AddressApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\CheckoutApiController;
use App\Http\Controllers\Api\CourierApiController;
use App\Http\Controllers\Api\DiscoveryApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\ShopApiController;
use App\Http\Controllers\Api\VoucherApiController;
use App\Http\Controllers\FinancialOperationsController;
use App\Http\Controllers\PsgcAddressController;
use Illuminate\Support\Facades\Route;

Route::post('/finance/sandbox-webhook', [FinancialOperationsController::class, 'webhook'])->middleware('throttle:60,1')->name('finance.sandbox.webhook');

/*
|--------------------------------------------------------------------------
| EZiCart Buyer Mobile API Routes
|--------------------------------------------------------------------------
*/

$defineApiRoutes = function () {
    // Public Auth
    Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/courier/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/courier/login', [AuthApiController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/register', [AuthApiController::class, 'register'])->middleware('throttle:registration');
    Route::post('/auth/register', [AuthApiController::class, 'register'])->middleware('throttle:registration');
    Route::post('/verify-email', [AuthApiController::class, 'verifyEmail'])->middleware('throttle:email-verification');
    Route::post('/auth/verify-email', [AuthApiController::class, 'verifyEmail'])->middleware('throttle:email-verification');
    Route::post('/resend-verification', [AuthApiController::class, 'resendVerificationCode'])->middleware('throttle:email-verification-resend');
    Route::post('/auth/resend-verification', [AuthApiController::class, 'resendVerificationCode'])->middleware('throttle:email-verification-resend');
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset-link');
    Route::post('/auth/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset-link');
    Route::post('/courier/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset-link');
    Route::post('/auth/courier/forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset-link');
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::post('/courier/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/courier/reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:password-reset');

    // Catalog & Products (Public)
    Route::get('/products', [ProductApiController::class, 'index']);
    Route::get('/products/suggestions', [ProductApiController::class, 'suggestions']);
    Route::get('/products/{id}', [ProductApiController::class, 'show'])->whereNumber('id');
    Route::get('/categories', [ProductApiController::class, 'categories']);

    // Seller Shop Front (Public)
    Route::get('/shops/{id}', [ShopApiController::class, 'show'])->whereNumber('id');

    // PSGC Locations (Public for registration & address management)
    Route::get('/psgc-addresses', PsgcAddressController::class);

    // Cart Quote (Public or Authenticated)
    Route::post('/cart/quote', [CartApiController::class, 'quote']);

    // Authenticated Buyer Only Routes
    Route::middleware('auth.api:buyer')->group(function () {
        // Persistent Buyer Cart (synchronized with Web database)
        Route::get('/cart', [CartApiController::class, 'index']);
        Route::post('/cart/items', [CartApiController::class, 'addItem']);
        Route::patch('/cart/items/{key}', [CartApiController::class, 'updateItem']);
        Route::delete('/cart/items/{key}', [CartApiController::class, 'removeItem']);
        Route::delete('/cart', [CartApiController::class, 'clearCart']);
        Route::post('/cart/sync', [CartApiController::class, 'sync']);

        // Current User Profile & Logout
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::get('/auth/me', [AuthApiController::class, 'me']);
        Route::post('/logout', [AuthApiController::class, 'logout']);
        Route::post('/auth/logout', [AuthApiController::class, 'logout']);

        // Profile & Security Management
        Route::put('/account/profile', [ProfileApiController::class, 'updateProfile']);
        Route::put('/account/password', [ProfileApiController::class, 'updatePassword']);

        // Seller Shop Following
        Route::post('/shops/{id}/follow', [ShopApiController::class, 'follow'])->whereNumber('id')->middleware('throttle:operational-action');
        Route::get('/buyer/followed-shops', [ShopApiController::class, 'followedShops']);

        // Notifications & Preferences
        Route::get('/notifications', [NotificationApiController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationApiController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationApiController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationApiController::class, 'markRead']);
        Route::get('/notifications/preferences', [NotificationApiController::class, 'getPreferences']);
        Route::patch('/notifications/preferences', [NotificationApiController::class, 'updatePreferences']);

        // Checkout & Orders
        Route::post('/checkout', [CheckoutApiController::class, 'checkout'])->middleware('throttle:operational-action');
        Route::get('/orders', [OrderApiController::class, 'index']);
        Route::get('/orders/{id}', [OrderApiController::class, 'show'])->whereNumber('id');
        Route::post('/orders/{id}/confirm', [OrderApiController::class, 'confirmReceived'])->whereNumber('id');
        Route::post('/orders/{id}/cancel', [OrderApiController::class, 'cancel'])->whereNumber('id');
        Route::post('/orders/{id}/delivery-code/refresh', [OrderApiController::class, 'refreshDeliveryCode'])->whereNumber('id');
        Route::post('/orders/{id}/review', [OrderApiController::class, 'review'])->whereNumber('id');

        // Order Disputes & Returns
        Route::get('/disputes', [OrderApiController::class, 'disputesList']);
        Route::get('/orders/{id}/dispute', [OrderApiController::class, 'getDispute'])->whereNumber('id');
        Route::post('/orders/{id}/dispute', [OrderApiController::class, 'createDispute'])->whereNumber('id');

        // Vouchers & Claims
        Route::get('/vouchers', [VoucherApiController::class, 'index']);
        Route::post('/vouchers/{id}/claim', [VoucherApiController::class, 'claim'])->whereNumber('id');

        // Personalized Discovery ("For You") & Stock Alerts
        Route::get('/discovery/for-you', [DiscoveryApiController::class, 'forYou']);
        Route::delete('/discovery/history', [DiscoveryApiController::class, 'resetHistory']);
        Route::get('/stock-alerts', [DiscoveryApiController::class, 'stockAlerts']);
        Route::post('/stock-alerts/{id}', [DiscoveryApiController::class, 'watchStock'])->whereNumber('id');
        Route::delete('/stock-alerts/{id}', [DiscoveryApiController::class, 'unwatchStock'])->whereNumber('id');

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

    // Authenticated Driver & Courier Routes
    Route::middleware('auth.api:courier')->prefix('courier')->group(function () {
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::get('/auth/me', [AuthApiController::class, 'me']);
        Route::post('/logout', [AuthApiController::class, 'logout']);
        Route::post('/auth/logout', [AuthApiController::class, 'logout']);
        Route::put('/password', [ProfileApiController::class, 'updatePassword']);
        Route::put('/account/password', [ProfileApiController::class, 'updatePassword']);

        Route::get('/dashboard', [CourierApiController::class, 'dashboard']);
        Route::post('/badge/rotate', [CourierApiController::class, 'rotateBadge']);
        Route::get('/orders/{id}', [CourierApiController::class, 'showOrder'])->whereNumber('id');
        Route::post('/orders/{id}/claim', [CourierApiController::class, 'claimPickup'])->whereNumber('id');
        Route::post('/orders/{id}/decline-pickup', [CourierApiController::class, 'declinePickup'])->whereNumber('id');
        Route::post('/orders/{id}/arrive', [CourierApiController::class, 'arriveAtPickup'])->whereNumber('id');
        Route::post('/orders/{id}/confirm-pickup', [CourierApiController::class, 'confirmPickup'])->whereNumber('id');
        Route::post('/orders/{id}/accept-assignment', [CourierApiController::class, 'acceptDeliveryAssignment'])->whereNumber('id');
        Route::post('/orders/{id}/start-delivery', [CourierApiController::class, 'startDelivery'])->whereNumber('id');
        Route::post('/orders/{id}/decline-delivery-assignment', [CourierApiController::class, 'declineDeliveryAssignment'])->whereNumber('id');
        Route::post('/orders/{id}/complete-delivery', [CourierApiController::class, 'completeDelivery'])->whereNumber('id');
        Route::patch('/orders/{id}/complete-delivery', [CourierApiController::class, 'completeDelivery'])->whereNumber('id');
        Route::post('/orders/{id}/fail-delivery', [CourierApiController::class, 'failDelivery'])->whereNumber('id');
        Route::patch('/orders/{id}/fail-delivery', [CourierApiController::class, 'failDelivery'])->whereNumber('id');
        Route::post('/orders/{id}/confirm-return-delivery', [CourierApiController::class, 'confirmReturnDelivery'])->whereNumber('id');
        Route::get('/history', [CourierApiController::class, 'history']);
        Route::get('/tracking', [CourierApiController::class, 'tracking']);
        Route::get('/earnings', [CourierApiController::class, 'earnings']);
        Route::post('/orders/{id}/remittance', [CourierApiController::class, 'submitRemittance'])->whereNumber('id');
        Route::get('/orders/{id}/messages', [CourierApiController::class, 'orderMessages'])->whereNumber('id');
        Route::post('/orders/{id}/messages', [CourierApiController::class, 'sendOrderMessage'])->whereNumber('id');
    });
};

// Mount both at /v1 and root of API
Route::prefix('v1')->group($defineApiRoutes);
$defineApiRoutes();
