<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Models\ParcelTransfer;
use Closure;
use Illuminate\Http\Request;

class EnsureParcelAvailable
{
    public function handle(Request $request, Closure $next)
    {
        $order = $request->route('order');
        if (! $request->isMethodSafe() && $order instanceof Order && $order->sorting_center_id === $request->user()->id && ! $request->routeIs('*.messages.*')) {
            abort_if(ParcelTransfer::query()->where('active_order_id', $order->id)->exists(), 422, 'Complete or cancel the active hub transfer before operating this parcel.');
        }

        return $next($request);
    }
}
