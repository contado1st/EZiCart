<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminComplianceController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminModerationController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RiderActivationController;
use App\Http\Controllers\Buyer\BuyerController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\DisputeController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\Courier\CourierController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Logistics\LogisticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PsgcAddressController;
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
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::get('/product/{product}', [HomeController::class, 'showProduct'])->name('product.show');
Route::get('/psgc-addresses', PsgcAddressController::class)->middleware('throttle:120,1')->name('psgc.addresses');

// 2. Guest Authentication & Registration Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login.post');
    Route::get('/login/verify', [AuthController::class, 'showLoginOtp'])->name('login.otp.notice');
    Route::post('/login/verify', [AuthController::class, 'verifyLoginOtp'])->middleware('throttle:email-verification')->name('login.otp.verify');
    Route::post('/login/verify/resend', [AuthController::class, 'resendLoginOtp'])->middleware('throttle:email-verification-resend')->name('login.otp.resend');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:password-reset-link')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register.post');

    Route::get('/register/seller', [AuthController::class, 'showSellerRegisterForm'])->name('register.seller');
    Route::post('/register/seller', [AuthController::class, 'sellerRegister'])->middleware('throttle:registration')->name('register.seller.post');

    Route::get('/verify-email', [AuthController::class, 'showEmailVerification'])->name('verification.notice');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:email-verification')->name('verification.verify');
    Route::post('/verify-email/resend', [AuthController::class, 'resendVerificationCode'])->middleware('throttle:email-verification-resend')->name('verification.resend');

    // Logistics company application (operational role: sorting_center)
    Route::get('/register/sorting-center', [AuthController::class, 'showSortingCenterRegisterForm'])->name('register.sorting');
    Route::post('/register/sorting-center', [AuthController::class, 'sortingCenterRegister'])->middleware('throttle:registration')->name('register.sorting.post');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/application/resubmit', [AuthController::class, 'showApplicationResubmission'])->name('application.resubmit');
    Route::post('/application/resubmit', [AuthController::class, 'resubmitApplication'])
        ->middleware('throttle:registration')
        ->name('application.resubmit.post');
});

// Token-protected rider activation remains available even if this browser has another signed-in account.
Route::get('/activate-rider/{token}', [RiderActivationController::class, 'show'])->name('rider.activation.show');
Route::post('/activate-rider', [RiderActivationController::class, 'activate'])->middleware('throttle:rider-activation')->name('rider.activation.store');

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
            Route::post('/orders/{order}/delivery-code/refresh', [BuyerController::class, 'refreshDeliveryCode'])->middleware('throttle:operational-action')->name('orders.delivery-code.refresh');
            Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
            Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
            Route::post('/orders/{order}/confirm', [BuyerController::class, 'confirmReceived'])->middleware('throttle:operational-action')->name('orders.confirm');
            Route::post('/orders/{order}/cancel', [BuyerController::class, 'cancel'])->middleware('throttle:operational-action')->name('orders.cancel');
            Route::post('/orders/{order}/review', [ReviewController::class, 'store'])->middleware('throttle:operational-action')->name('orders.review');
            Route::get('/orders/{order}/dispute', [DisputeController::class, 'create'])->name('orders.dispute.create');
            Route::post('/orders/{order}/dispute', [DisputeController::class, 'store'])->middleware('throttle:operational-action')->name('orders.dispute.store');
        });

        // Cart Actions
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/add/{product}', [CartController::class, 'add'])->middleware('throttle:operational-action')->name('add');
            Route::patch('/update/{cartKey}', [CartController::class, 'update'])->middleware('throttle:operational-action')->name('update');
            Route::delete('/remove/{cartKey}', [CartController::class, 'remove'])->middleware('throttle:operational-action')->name('remove');
        });

        // Checkout Actions & Voucher Application
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'process'])->middleware('throttle:operational-action')->name('checkout.process');
        Route::post('/checkout/voucher', [CheckoutController::class, 'applyVoucher'])->middleware('throttle:operational-action')->name('checkout.voucher.apply');
        Route::delete('/checkout/voucher', [CheckoutController::class, 'removeVoucher'])->middleware('throttle:operational-action')->name('checkout.voucher.remove');
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
        Route::post('/orders/{order}/cancel', [SellerOrderController::class, 'cancel'])->middleware('throttle:operational-action')->name('orders.cancel');
        Route::post('/orders/{order}/schedule-pickup', [SellerOrderController::class, 'schedulePickup'])->middleware('throttle:pickup-schedule')->name('orders.schedulePickup');
        Route::post('/orders/{order}/confirm-handover', [SellerOrderController::class, 'confirmHandover'])->middleware('throttle:operational-action')->name('orders.confirmHandover');
        Route::post('/orders/{order}/confirm-return', [SellerOrderController::class, 'confirmReturn'])->middleware('throttle:operational-action')->name('orders.confirmReturn');
        Route::get('/orders/{order}/waybill', [SellerOrderController::class, 'waybill'])->name('orders.waybill');
        Route::post('/orders/{order}/waybill/reprint', [SellerOrderController::class, 'reprintWaybill'])->middleware('throttle:operational-action')->name('orders.waybill.reprint');

        // Promotional Vouchers & Discounts
        Route::get('/vouchers', [SellerVoucherController::class, 'index'])->name('vouchers.index');
        Route::post('/vouchers', [SellerVoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/vouchers/{voucher}/toggle', [SellerVoucherController::class, 'toggle'])->name('vouchers.toggle');
        Route::delete('/vouchers/{voucher}', [SellerVoucherController::class, 'destroy'])->name('vouchers.destroy');
    });

    // Courier-Only Routes (Fulfillment & Delivery Workspace)
    Route::middleware(['role:courier'])->prefix('courier')->name('courier.')->group(function () {
        Route::get('/dashboard', [CourierController::class, 'dashboard'])->name('dashboard');
        Route::post('/badge/rotate', [CourierController::class, 'rotateBadge'])->middleware('throttle:operational-action')->name('badge.rotate');
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
        Route::get('/storage', [LogisticsController::class, 'storage'])->name('storage');
        Route::get('/scan-history', [LogisticsController::class, 'scanHistory'])->name('scan-history');
        Route::post('/storage/locations', [LogisticsController::class, 'createStorageLocation'])->middleware('throttle:operational-action')->name('storage.locations.create');
        Route::post('/storage/putaway', [LogisticsController::class, 'putAwayParcel'])->middleware('throttle:operational-action')->name('storage.putaway');
        Route::post('/storage/pick', [LogisticsController::class, 'pickStoredParcel'])->middleware('throttle:operational-action')->name('storage.pick');
        Route::post('/storage/cycle-count', [LogisticsController::class, 'cycleCount'])->middleware('throttle:operational-action')->name('storage.cycle-count');
        Route::get('/dispatch', [LogisticsController::class, 'dispatch'])->name('dispatch');
        Route::get('/tracking', [LogisticsController::class, 'tracking'])->name('tracking');
        Route::get('/orders/{order}/messages', [OrderMessageController::class, 'show'])->name('orders.messages.show');
        Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware('throttle:operational-action')->name('orders.messages.store');
        Route::get('/reports', [LogisticsController::class, 'reports'])->name('reports');
        Route::get('/areas', [LogisticsController::class, 'areas'])->name('areas');
        Route::post('/areas', [LogisticsController::class, 'storeArea'])->middleware('throttle:operational-action')->name('areas.store');
        Route::post('/areas/{area}/claim', [LogisticsController::class, 'claimArea'])->middleware('throttle:operational-action')->name('areas.claim');
        Route::post('/areas/municipalities', [LogisticsController::class, 'storeAreaMunicipality'])->middleware('throttle:operational-action')->name('areas.municipalities.store');
        Route::patch('/areas/municipalities/{areaMunicipality}', [LogisticsController::class, 'updateAreaMunicipality'])->middleware('throttle:operational-action')->name('areas.municipalities.update');
        Route::post('/orders/{order}/receive', [LogisticsController::class, 'receiveParcel'])->middleware('throttle:operational-action')->name('orders.receive');
        Route::post('/orders/{order}/sort', [LogisticsController::class, 'sortParcel'])->middleware('throttle:operational-action')->name('orders.sort');
        Route::post('/orders/{order}/assign-rider', [LogisticsController::class, 'assignRider'])->middleware('throttle:operational-action')->name('orders.assignRider');
        Route::post('/orders/{order}/release-to-rider', [LogisticsController::class, 'releaseToRider'])->middleware('throttle:operational-action')->name('orders.releaseToRider');
        Route::post('/dispatch/release-batch', [LogisticsController::class, 'releaseBatch'])->middleware('throttle:operational-action')->name('dispatch.releaseBatch');
        Route::post('/orders/{order}/recover-from-rider', [LogisticsController::class, 'recoverReleasedParcel'])->middleware('throttle:operational-action')->name('orders.recoverReleasedParcel');
        Route::post('/orders/{order}/return', [LogisticsController::class, 'returnParcel'])->middleware('throttle:operational-action')->name('orders.return');

        Route::get('/riders', [LogisticsController::class, 'riders'])->name('riders');
        Route::post('/riders', [LogisticsController::class, 'storeRider'])->middleware('throttle:operational-action')->name('riders.store');
        Route::post('/riders/{user}/resend-activation', [LogisticsController::class, 'resendRiderActivation'])->middleware('throttle:operational-action')->name('riders.activation.resend');
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
        Route::post('/orders/{order}/cancel', [AdminController::class, 'cancelOrder'])->middleware('throttle:operational-action')->name('orders.cancel');
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
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->middleware('throttle:operational-action')->name('announcements.store');
        Route::patch('/announcements/{announcement}/toggle', [AdminAnnouncementController::class, 'toggle'])->name('announcements.toggle');
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::get('/moderation', [AdminModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/{user}/suspend', [AdminModerationController::class, 'suspend'])->middleware('throttle:operational-action')->name('moderation.suspend');
        Route::post('/moderation/{user}/reactivate', [AdminModerationController::class, 'reactivate'])->middleware('throttle:operational-action')->name('moderation.reactivate');
    });

});
