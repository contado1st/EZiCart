<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class ChatInboxController extends Controller
{
    public function __invoke(Request $request)
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['buyer', 'seller'], true), 403);
        $orders = Order::query()->where($actor->role === 'buyer' ? 'buyer_id' : 'seller_id', $actor->id)
            ->with(['seller', 'buyer', 'conversation' => fn ($q) => $q->withMax('messages', 'created_at')])
            ->latest('updated_at')->paginate(20);

        return view('orders.inbox', compact('orders'));
    }
}
