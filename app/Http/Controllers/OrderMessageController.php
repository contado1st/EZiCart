<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderConversation;
use App\Models\OrderMessage;
use App\Models\User;
use App\Notifications\OrderMessageNotification;
use App\Services\MessagingInboxService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderMessageController extends Controller
{
    public function show(Request $request, Order $order, MessagingInboxService $inbox): View
    {
        $actor = $this->authenticatedUser();
        $this->authorizeParticipant($order, $actor);
        $conversation = $order->conversation;

        if ($conversation !== null) {
            $conversation->messages()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $actor->id)
                ->update(['read_at' => now()]);
        }
        $messages = $conversation?->messages()->with('sender')->reorder()->latest('id')->paginate(40, ['*'], 'messages_page')->withQueryString();
        if ($messages) {
            $messages->setCollection($messages->getCollection()->reverse()->values());
        }
        $order->loadMissing('seller', 'buyer');
        $threads = $inbox->threads($request);
        $messagePrefix = $inbox->routePrefix($actor);
        $orderUrl = $inbox->orderUrl($actor, $order);

        return view('orders.messages', compact('order', 'conversation', 'messages', 'threads', 'messagePrefix', 'orderUrl'));
    }

    public function store(Request $request, Order $order, TransactionAwareNotificationSender $notifications, MessagingInboxService $inbox): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $actor = $this->authenticatedUser();

        DB::transaction(function () use ($order, $actor, $validated, $notifications): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($lockedOrder, $actor);
            $conversation = OrderConversation::query()->firstOrCreate(['order_id' => $lockedOrder->id]);
            OrderMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $actor->id,
                'body' => trim($validated['body']),
            ]);

            foreach ($conversation->participants()->where('id', '!=', $actor->id) as $recipient) {
                $notifications->send($recipient, new OrderMessageNotification($lockedOrder, $actor->first_name.' '.$actor->last_name));
            }
        });

        return redirect()->route($inbox->routePrefix($actor).'.orders.messages.show', $order)->with('success', 'Message sent.');
    }

    private function authorizeParticipant(Order $order, User $actor): void
    {
        abort_unless($actor->isCommerceAdmin() || $actor->isLogisticsAdmin() || $order->messageParticipants()->contains('id', $actor->id), 403);
    }
}
