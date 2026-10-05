<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminComplianceController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminModerationController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Buyer\BuyerController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\DisputeController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\Courier\CourierController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Logistics\LogisticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderMessageController;
use App\Http\Controllers\SecureDocumentController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\SellerController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\SellerReportController;
use App\Http\Controllers\Seller\SellerVoucherController;
use Illuminate\Support\Facades\Route;

// 1. Public Marketplace & Browsing Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/product/{product}', [HomeController::class, 'showProduct'])->name('product.show');

// 2. Guest Authentication & Registration Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login.post');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:password-reset-link')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register.post');

    Route::get('/register/seller', [AuthController::class, 'showSellerRegisterForm'])->name('register.seller');
    Route::post('/register/seller', [AuthController::class, 'sellerRegister'])->middleware('throttle:registration')->name('register.seller.post');

    Route::get('/register/courier', [AuthController::class, 'showCourierRegisterForm'])->name('register.courier');
    Route::post('/register/courier', [AuthController::class, 'courierRegister'])->middleware('throttle:registration')->name('register.courier.post');

    // Sorting Center Public Application
    Route::get('/register/sorting-center', [AuthController::class, 'showSortingCenterRegisterForm'])->name('register.sorting');
    Route::post('/register/sorting-center', [AuthController::class, 'sortingCenterRegister'])->middleware('throttle:registration')->name('register.sorting.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 3. Protected Routes (Strictly Isolated by Role)
Route::middleware(['auth', 'account.active', 'auth.session'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/account/documents/{type}', [SecureDocumentController::class, 'ownDocument'])->name('account.documents.show');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/account', [AccountController::class, 'update'])->middleware('throttle:operational-action')->name('account.profile.update');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password.update');
    Route::get('/delivery-attempts/{attempt}/proof', [SecureDocumentController::class, 'deliveryProof'])->name('delivery-attempts.proof');

    // Buyer-Only Routes
    Route::middleware(['role:buyer'])->group(function () {
        Route::prefix('buyer')->name('buyer.')->group(function () {
            Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
            Route::get('/orders/{order}', [BuyerController::class, 'showOrder'])->name('orders.show');
            Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
            Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
            Route::post('/orders/{order}/confirm', [BuyerController::class, 'confirmReceived'])->middleware('throttle:operational-action')->name('orders.confirm');
            Route::post('/orders/{order}/cancel', [BuyerController::class, 'cancel'])->middleware('throttle:operational-action')->name('orders.cancel');
            Route::post('/orders/{order}/review', [ReviewController::class, 'store'])->name('orders.review');
            Route::get('/orders/{order}/dispute', [DisputeController::class, 'create'])->name('orders.dispute.create');
            Route::post('/orders/{order}/dispute', [DisputeController::class, 'store'])->middleware('throttle:operational-action')->name('orders.dispute.store');
        });

        // Cart Actions
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/add/{product}', [CartController::class, 'add'])->name('add');
            Route::patch('/update/{cartKey}', [CartController::class, 'update'])->name('update');
            Route::delete('/remove/{cartKey}', [CartController::class, 'remove'])->name('remove');
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
        Route::resource('products', ProductController::class)->except(['show']);
        Route::get('/reports', [SellerReportController::class, 'index'])->name('reports.index');

        // Order Fulfillment & Waybill Routes
        Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [SellerOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
        Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
        Route::patch('/orders/{order}/status', [SellerOrderController::class, 'updateStatus'])->middleware('throttle:operational-action')->name('orders.updateStatus');
        Route::post('/orders/{order}/schedule-pickup', [SellerOrderController::class, 'schedulePickup'])->middleware('throttle:pickup-schedule')->name('orders.schedulePickup');
        Route::post('/orders/{order}/confirm-handover', [SellerOrderController::class, 'confirmHandover'])->middleware('throttle:operational-action')->name('orders.confirmHandover');
        Route::post('/orders/{order}/confirm-return', [SellerOrderController::class, 'confirmReturn'])->middleware('throttle:operational-action')->name('orders.confirmReturn');
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
        Route::post('/orders/{order}/claim', [CourierController::class, 'claimPickup'])->middleware('throttle:operational-action')->name('orders.claim');
        Route::post('/orders/{order}/decline-pickup', [CourierController::class, 'declinePickup'])->middleware('throttle:operational-action')->name('orders.declinePickup');
        Route::get('/orders/{order}', [CourierController::class, 'showOrder'])->name('orders.show');
        Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
        Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
        Route::post('/orders/{order}/confirm-pickup', [CourierController::class, 'confirmPickup'])->middleware('throttle:operational-action')->name('orders.confirmPickup');
        Route::post('/orders/{order}/start-delivery', [CourierController::class, 'startDelivery'])->middleware('throttle:operational-action')->name('orders.startDelivery');
        Route::post('/orders/{order}/decline-delivery-assignment', [CourierController::class, 'declineDeliveryAssignment'])->middleware('throttle:operational-action')->name('orders.declineDeliveryAssignment');
        Route::post('/orders/{order}/confirm-return-delivery', [CourierController::class, 'confirmReturnDelivery'])->middleware('throttle:operational-action')->name('orders.confirmReturnDelivery');
        Route::patch('/orders/{order}/complete-delivery', [CourierController::class, 'completeDelivery'])->middleware('throttle:operational-action')->name('orders.completeDelivery');
        Route::patch('/orders/{order}/fail-delivery', [CourierController::class, 'failDelivery'])->middleware('throttle:operational-action')->name('orders.failDelivery');
        Route::get('/history', [CourierController::class, 'history'])->name('history');
        Route::get('/tracking', [CourierController::class, 'tracking'])->name('tracking');
        Route::get('/earnings', [CourierController::class, 'earnings'])->name('earnings');
    });

    // Logistics / Sorting Center Routes
    Route::middleware(['role:sorting_center'])->prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
        Route::get('/intake', [LogisticsController::class, 'intake'])->name('intake');
        Route::get('/pickup-requests', [LogisticsController::class, 'pickupRequests'])->name('pickupRequests');
        Route::post('/orders/{order}/assign-pickup', [LogisticsController::class, 'assignPickup'])->middleware('throttle:operational-action')->name('orders.assignPickup');
        Route::post('/scan', [LogisticsController::class, 'scan'])->middleware('throttle:operational-action')->name('scan');
        Route::get('/sorting', [LogisticsController::class, 'sorting'])->name('sorting');
        Route::get('/dispatch', [LogisticsController::class, 'dispatch'])->name('dispatch');
        Route::get('/tracking', [LogisticsController::class, 'tracking'])->name('tracking');
        Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
        Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
        Route::get('/reports', [LogisticsController::class, 'reports'])->name('reports');
        Route::get('/areas', [LogisticsController::class, 'areas'])->name('areas');
        Route::post('/areas', [LogisticsController::class, 'storeArea'])->middleware('throttle:operational-action')->name('areas.store');
        Route::post('/areas/municipalities', [LogisticsController::class, 'storeAreaMunicipality'])->middleware('throttle:operational-action')->name('areas.municipalities.store');
        Route::patch('/areas/municipalities/{areaMunicipality}', [LogisticsController::class, 'updateAreaMunicipality'])->middleware('throttle:operational-action')->name('areas.municipalities.update');
        Route::post('/orders/{order}/receive', [LogisticsController::class, 'receiveParcel'])->middleware('throttle:operational-action')->name('orders.receive');
        Route::post('/orders/{order}/sort', [LogisticsController::class, 'sortParcel'])->middleware('throttle:operational-action')->name('orders.sort');
        Route::post('/orders/{order}/assign-rider', [LogisticsController::class, 'assignRider'])->middleware('throttle:operational-action')->name('orders.assignRider');
        Route::post('/orders/{order}/release-to-rider', [LogisticsController::class, 'releaseToRider'])->middleware('throttle:operational-action')->name('orders.releaseToRider');
        Route::post('/orders/{order}/recover-from-rider', [LogisticsController::class, 'recoverReleasedParcel'])->middleware('throttle:operational-action')->name('orders.recoverReleasedParcel');
        Route::post('/orders/{order}/return', [LogisticsController::class, 'returnParcel'])->middleware('throttle:operational-action')->name('orders.return');

        Route::get('/riders', [LogisticsController::class, 'riders'])->name('riders');
        Route::post('/riders/{user}/approve', [LogisticsController::class, 'approveRider'])->middleware('throttle:operational-action')->name('riders.approve');
        Route::post('/riders/{user}/reject', [LogisticsController::class, 'rejectRider'])->middleware('throttle:operational-action')->name('riders.reject');
        Route::post('/riders/{user}/suspend', [LogisticsController::class, 'suspendRider'])->middleware('throttle:operational-action')->name('riders.suspend');
        Route::post('/riders/{user}/reactivate', [LogisticsController::class, 'reactivateRider'])->middleware('throttle:operational-action')->name('riders.reactivate');
        Route::patch('/riders/{user}/areas', [LogisticsController::class, 'updateRiderAreas'])->middleware('throttle:operational-action')->name('riders.areas.update');
        Route::get('/riders/{user}/documents/{type}', [SecureDocumentController::class, 'userDocument'])->name('riders.documents.show');
    });

    // Admin-Only Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
        Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
        Route::get('/compliance/products', [AdminComplianceController::class, 'index'])->name('compliance.products.index');
        Route::patch('/compliance/products/{product}', [AdminComplianceController::class, 'review'])->middleware('throttle:operational-action')->name('compliance.products.review');
        Route::post('/compliance/products/{product}/warn-seller', [AdminComplianceController::class, 'warn'])->middleware('throttle:operational-action')->name('compliance.products.warn');
        Route::get('/registrations', [AdminController::class, 'index'])->name('registrations.index');
        Route::post('/registrations/{user}/approve', [AdminController::class, 'approve'])->middleware('throttle:operational-action')->name('registrations.approve');
        Route::post('/registrations/{user}/reject', [AdminController::class, 'reject'])->middleware('throttle:operational-action')->name('registrations.reject');
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes.index');
        Route::get('/disputes/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
        Route::get('/disputes/{dispute}/evidence', [SecureDocumentController::class, 'disputeEvidence'])->name('disputes.evidence');
        Route::get('/users/{user}/documents/{type}', [SecureDocumentController::class, 'userDocument'])->name('users.documents.show');
        Route::patch('/disputes/{dispute}/resolve', [AdminDisputeController::class, 'resolve'])->middleware('throttle:operational-action')->name('disputes.resolve');
        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('/announcements/{announcement}/toggle', [AdminAnnouncementController::class, 'toggle'])->name('announcements.toggle');
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::get('/moderation', [AdminModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/{user}/suspend', [AdminModerationController::class, 'suspend'])->middleware('throttle:operational-action')->name('moderation.suspend');
        Route::post('/moderation/{user}/reactivate', [AdminModerationController::class, 'reactivate'])->middleware('throttle:operational-action')->name('moderation.reactivate');
    });

});
