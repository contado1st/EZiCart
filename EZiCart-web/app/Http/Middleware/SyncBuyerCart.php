<?php

namespace App\Http\Middleware;

use App\Services\BuyerCartService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncBuyerCart
{
    public function handle(Request $request, Closure $next): Response
    {
        $buyer = $request->user();
        if ($buyer?->role === 'buyer' && $request->hasSession()) {
            $owner = $request->session()->get('cart_owner_id');
            if ($owner !== null && (int) $owner !== $buyer->id) {
                $request->session()->forget(['cart', 'checkout_context', 'checkout_token', 'checkout_quote_hash', 'applied_voucher']);
            }
            $carts = app(BuyerCartService::class);
            $carts->read($buyer, $request->session()->get('cart', []));
            $request->session()->put(['cart_owner_id' => $buyer->id, 'cart' => $carts->display($buyer)]);
        }

        return $next($request);
    }
}
