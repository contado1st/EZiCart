<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderTransitionService;
use Illuminate\Http\Request;

class CancellationController extends Controller
{
    public function __invoke(Request $request, Order $order, OrderTransitionService $transitions)
    {
        abort_unless($order->seller_id === $request->user()->id, 403);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'response' => ['required', 'string', 'min:5', 'max:500']]);
        abort_unless($transitions->resolveCancellation($order, $request->user(), $data['decision'] === 'approve', $data['response']), 422, 'No pending cancellation request.');

        return back()->with('success', 'Cancellation request resolved.');
    }
}
