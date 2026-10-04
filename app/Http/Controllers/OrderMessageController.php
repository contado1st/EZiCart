<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderConversation;
use App\Models\OrderMessage;
use App\Notifications\OrderMessageNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderMessageController extends Controller
{
    public function show(Order $order): View
    {
        $actor = $this->authenticatedUser();
        $this->authorizeParticipant($order, $actor);
        $conversation = $order->conversation;

        if ($conversation !== null) {
            $conversation->messages()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $actor->id)
                ->update(['read_at' => now()]);
            $conversation->load('messages.sender');
        }

        return view('orders.messages', compact('order', 'conversation'));
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $actor = $this->authenticatedUser();

        DB::transaction(function () use ($order, $actor, $validated): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($lockedOrder, $actor);
            $conversation = OrderConversation::query()->firstOrCreate(['order_id' => $lockedOrder->id]);
            OrderMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $actor->id,
                'body' => trim($validated['body']),
            ]);

            foreach ($conversation->participants()->where('id', '!=', $actor->id) as $recipient) {
                $recipient->notify(new OrderMessageNotification($lockedOrder, $actor->first_name.' '.$actor->last_name));
            }
        });

        return back()->with('success', 'Message sent.');
    }

    private function authorizeParticipant(Order $order, User $actor): void
    {
        abort_unless($order->messageParticipants()->contains('id', $actor->id), 403);
    }
}
