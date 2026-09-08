<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\SellerVoucherController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SellerReportController;
use App\Http\Controllers\AdminReportController;

// 1. Public Marketplace & Browsing Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/product/{product}', [HomeController::class, 'showProduct'])->name('product.show');

// 2. Guest Authentication & Registration Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');

    Route::get('/register/seller', [AuthController::class, 'showSellerRegisterForm'])->name('register.seller');
    Route::post('/register/seller', [AuthController::class, 'sellerRegister'])->name('register.seller.post');

    Route::get('/register/courier', [AuthController::class, 'showCourierRegisterForm'])->name('register.courier');
    Route::post('/register/courier', [AuthController::class, 'courierRegister'])->name('register.courier.post');

    // Sorting Center Public Application
    Route::get('/register/sorting-center', [AuthController::class, 'showSortingCenterRegisterForm'])->name('register.sorting');
    Route::post('/register/sorting-center', [AuthController::class, 'sortingCenterRegister'])->name('register.sorting.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 3. Protected Routes (Strictly Isolated by Role)
Route::middleware(['auth'])->group(function () {

    // Buyer-Only Routes
    Route::middleware(['role:buyer'])->group(function () {
        Route::prefix('buyer')->name('buyer.')->group(function () {
            Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
            Route::post('/orders/{order}/confirm', [BuyerController::class, 'confirmReceived'])->name('orders.confirm');
            Route::post('/orders/{order}/review', [ReviewController::class, 'store'])->name('orders.review');
        });

        // Cart Actions
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/add/{product}', [CartController::class, 'add'])->name('add');
            Route::patch('/update/{product}', [CartController::class, 'update'])->name('update');
            Route::delete('/remove/{product}', [CartController::class, 'remove'])->name('remove');
        });

        // Checkout Actions & Voucher Application
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
        Route::post('/checkout/voucher', [CheckoutController::class, 'applyVoucher'])->name('checkout.voucher.apply');
        Route::delete('/checkout/voucher', [CheckoutController::class, 'removeVoucher'])->name('checkout.voucher.remove');
    });

    // Seller-Only Routes
    Route::middleware(['role:seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
        Route::resource('products', ProductController::class);
        Route::get('/reports', [SellerReportController::class, 'index'])->name('reports.index');

        // Order Fulfillment & Waybill Routes
        Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [SellerOrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [SellerOrderController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::get('/orders/{order}/waybill', [SellerOrderController::class, 'waybill'])->name('orders.waybill');

        // Promotional Vouchers & Discounts
        Route::get('/vouchers', [SellerVoucherController::class, 'index'])->name('vouchers.index');
        Route::post('/vouchers', [SellerVoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/vouchers/{voucher}/toggle', [SellerVoucherController::class, 'toggle'])->name('vouchers.toggle');
        Route::delete('/vouchers/{voucher}', [SellerVoucherController::class, 'destroy'])->name('vouchers.destroy');
    });

    // Courier-Only Routes (Fulfillment & Delivery Workspace)
    Route::middleware(['role:courier'])->prefix('courier')->name('courier.')->group(function () {
        Route::get('/dashboard', [CourierController::class, 'dashboard'])->name('dashboard');
        Route::post('/orders/{order}/claim', [CourierController::class, 'claimPickup'])->name('orders.claim');
        Route::post('/orders/{order}/start-delivery', [CourierController::class, 'startDelivery'])->name('orders.startDelivery');
        Route::patch('/orders/{order}/complete-delivery', [CourierController::class, 'completeDelivery'])->name('orders.completeDelivery');
    });

    // Logistics / Sorting Center Routes
    Route::middleware(['role:sorting_center'])->prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
        Route::post('/orders/{order}/receive', [LogisticsController::class, 'receiveParcel'])->name('orders.receive');
        Route::post('/orders/{order}/sort', [LogisticsController::class, 'sortParcel'])->name('orders.sort');
        Route::post('/orders/{order}/assign-rider', [LogisticsController::class, 'assignRider'])->name('orders.assignRider');

        Route::get('/riders', [LogisticsController::class, 'riders'])->name('riders');
        Route::post('/riders/{user}/approve', [LogisticsController::class, 'approveRider'])->name('riders.approve');
        Route::post('/riders/{user}/reject', [LogisticsController::class, 'rejectRider'])->name('riders.reject');
    });

    // Admin-Only Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/registrations', [AdminController::class, 'index'])->name('registrations.index');
        Route::post('/registrations/{user}/approve', [AdminController::class, 'approve'])->name('registrations.approve');
        Route::post('/registrations/{user}/reject', [AdminController::class, 'reject'])->name('registrations.reject');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    });

});