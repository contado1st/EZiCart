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
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 3. Protected Routes (Strictly Isolated by Role)
Route::middleware(['auth'])->group(function () {

    // Buyer-Only Routes (Cart, Checkout & Demand Workspace)
    Route::middleware(['role:buyer'])->group(function () {
        Route::prefix('buyer')->name('buyer.')->group(function () {
            Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
            Route::post('/orders/{order}/confirm', [BuyerController::class, 'confirmReceived'])->name('orders.confirm');
        });

        // Cart Actions
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/add/{product}', [CartController::class, 'add'])->name('add');
            Route::patch('/update/{product}', [CartController::class, 'update'])->name('update');
            Route::delete('/remove/{product}', [CartController::class, 'remove'])->name('remove');
        });

        // Checkout Actions
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    });

    // Seller-Only Routes (Shop & Inventory Supply Workspace)
    Route::middleware(['role:seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
        Route::resource('products', ProductController::class);
    });

    // Courier-Only Routes (Fulfillment & Delivery Workspace)
    Route::middleware(['role:courier'])->prefix('courier')->name('courier.')->group(function () {
        Route::get('/dashboard', function () {
            return view('courier.dashboard');
        })->name('dashboard');
    });

    // Admin-Only Routes (Governance & Platform Control)
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::get('/registrations', [AdminController::class, 'index'])->name('registrations.index');
        Route::post('/registrations/{user}/approve', [AdminController::class, 'approve'])->name('registrations.approve');
        Route::post('/registrations/{user}/reject', [AdminController::class, 'reject'])->name('registrations.reject');
    });

});