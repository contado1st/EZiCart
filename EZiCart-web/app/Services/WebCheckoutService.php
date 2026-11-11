<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Web-only checks around the existing checkout shared with mobile. */
class WebCheckoutService
{
    public function __construct(private PlacePurchaseService $purchases, private VoucherClaimService $claims) {}

    public function assertShopsOpen(array $cart): void
    {
        app(ShopAvailabilityService::class)->assertOpen($cart);
        $products = Product::whereKey(array_map(fn ($line) => $line['product_id'] ?? $line['id'] ?? 0, $cart))->orderBy('id');
        if (DB::transactionLevel() > 0) {
            $products->lockForUpdate();
        }
        if ($products->get(['id', 'user_id'])->pluck('user_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['selected_keys' => 'Choose products from one shop per checkout. Items from other shops stay saved in your cart.']);
        }
    }

    public function place(User $buyer, string $token, string $requestHash, array $cart, string $reviewedHash, ?int $voucherId, array $address, string $paymentMethod = 'COD'): Purchase
    {
        return DB::transaction(function () use ($buyer, $token, $requestHash, $cart, $reviewedHash, $voucherId, $address, $paymentMethod) {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('users')->where('id', $buyer->id)->update(['updated_at' => DB::raw('updated_at')]);
            }
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            if (Purchase::where('buyer_id', $buyer->id)->where('checkout_token', $token)->exists()) {
                return $this->purchases->place($buyer, $token, $requestHash, $cart, $reviewedHash, $voucherId, $address, $paymentMethod);
            }
            $voucher = $voucherId ? Voucher::whereKey($voucherId)->lockForUpdate()->first() : null;
            if ($voucher) {
                $this->claims->assertUsable($buyer, $voucher);
            }
            $this->assertShopsOpen($cart);
            $purchase = $this->purchases->place($buyer, $token, $requestHash, $cart, $reviewedHash, $voucherId, $address, $paymentMethod);

            return $purchase;
        }, 3);
    }
}
