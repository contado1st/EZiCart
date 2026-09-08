<?php

// use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

// Home page route//

use App\Http\Controllers\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Authentication routes//

use App\Http\Controllers\AuthController;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    // Buyer Registration
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');

    // Seller Registration
Route::get('/register/seller', [AuthController::class, 'showSellerRegisterForm'])->name('register.seller');
Route::post('/register/seller', [AuthController::class, 'sellerRegister'])->name('register.seller.post');

    // Courier Registration Placeholder
Route::get('/register/courier', [AuthController::class, 'showCourierRegisterForm'])->name('register.courier');
Route::post('/register/courier', [AuthController::class, 'courierRegister'])->name('register.courier.post');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin routes for managing user registrations//

use App\Http\Controllers\AdminController;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/registrations', [AdminController::class, 'index'])->name('registrations.index');
    Route::post('/registrations/{user}/approve', [AdminController::class, 'approve'])->name('registrations.approve');
    Route::post('/registrations/{user}/reject', [AdminController::class, 'reject'])->name('registrations.reject');
});

// Seller routes for managing products//

use App\Http\Controllers\ProductController;

    // Seller Routes
    Route::middleware(['role:seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
        
        // Product Management Routes
        Route::resource('products', ProductController::class);
    });