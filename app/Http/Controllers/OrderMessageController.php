<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\DirectMessagingService;
use Illuminate\Http\Request;

class OrderMessageController extends Controller
{
    public function show(Request $request, Order $order, DirectMessagingService $messaging)
    {
        $actor = $request->user();
        $messaging->authorizeOrder($order, $actor);
        $ids = $order->messageParticipants()->pluck('id');
        $contacts = $messaging->contacts($actor)->where(function ($q) use ($ids, $actor) {
            $q->whereIn('id', $ids)->orWhereIn('role', in_array($actor->role, ['buyer', 'seller'], true) ? ['admin', 'ecommerce_admin'] : ['logistics_admin']);
        })->orderBy('first_name')->paginate(20)->withQueryString();

        return view('orders.contacts', compact('order', 'contacts'));
    }

    public function store(Request $request, Order $order, DirectMessagingService $messaging)
    {
        $messaging->authorizeOrder($order, $request->user());
        $data = $request->validate(['recipient_id' => ['required', 'integer', 'exists:users,id'], 'body' => ['required', 'string', 'max:2000'], 'panel' => ['nullable', 'boolean']]);
        $recipient = User::findOrFail($data['recipient_id']);
        $messaging->authorizeOrder($order, $recipient);
        $conversation = $messaging->start($request->user(), $recipient);
        $messaging->send($conversation, $request->user(), $data['body'], $order);

        return redirect()->route('chat.show', [$conversation, 'order_id' => $order->id, 'panel' => $request->boolean('panel') ? 1 : null])->with('success', 'Message sent.');
    }
}
