<?php

namespace App\Http\Controllers;

use App\Models\DirectConversation;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Services\DirectMessagingService;
use App\Services\MessagingInboxService;
use Illuminate\Http\Request;

class DirectMessageController extends Controller
{
    public function contacts(Request $request, DirectMessagingService $messaging)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $contacts = $messaging->contacts($request->user());
        if (filled($validated['q'] ?? null)) {
            $pattern = '%'.strtr(trim($validated['q']), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $contacts->where(fn ($q) => $q->whereRaw("first_name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("last_name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("business_name LIKE ? ESCAPE '!'", [$pattern]));
        }

        return view('orders.contacts', ['contacts' => $contacts->orderBy('first_name')->paginate(20)->withQueryString(), 'order' => null]);
    }

    public function start(Request $request, User $recipient, DirectMessagingService $messaging)
    {
        $data = $request->validate(['order_id' => ['nullable', 'integer', 'exists:orders,id'], 'panel' => ['nullable', 'boolean']]);
        if (isset($data['order_id'])) {
            $order = Order::findOrFail($data['order_id']);
            $messaging->authorizeOrder($order, $request->user());
            $messaging->authorizeOrder($order, $recipient);
        }
        $conversation = $messaging->start($request->user(), $recipient);

        return redirect()->route('chat.show', [$conversation, 'panel' => $request->boolean('panel') ? 1 : null, 'order_id' => $data['order_id'] ?? null]);
    }

    public function show(Request $request, DirectConversation $conversation, MessagingInboxService $inbox, DirectMessagingService $messaging)
    {
        $actor = $request->user();
        abort_unless($conversation->hasParticipant($actor), 403);
        $data = $request->validate(['order_id' => ['nullable', 'integer', 'exists:orders,id']]);
        $partner = $conversation->otherUser($actor);
        $order = isset($data['order_id']) ? Order::findOrFail($data['order_id']) : null;
        if ($order) {
            $messaging->authorizeOrder($order, $actor);
            $messaging->authorizeOrder($order, $partner);
        }
        $conversation->messages()->where('recipient_id', $actor->id)->whereNull('read_at')->update(['read_at' => now()]);
        $actor->unreadNotifications()->where('type', DirectMessageNotification::class)->where('data->conversation_id', $conversation->id)->update(['read_at' => now()]);
        $messages = $conversation->messages()->with('sender')->latest('id')->paginate(40, ['*'], 'messages_page')->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());

        return view('orders.messages', ['conversation' => $conversation, 'partner' => $partner, 'messages' => $messages,
            'threads' => $inbox->threads($request), 'order' => $order, 'orderUrl' => $order ? $inbox->orderUrl($actor, $order) : null,
            'canSend' => $messaging->canContact($actor, $partner)]);
    }

    public function store(Request $request, DirectConversation $conversation, DirectMessagingService $messaging)
    {
        abort_unless($conversation->hasParticipant($request->user()), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'order_id' => ['nullable', 'integer', 'exists:orders,id'], 'panel' => ['nullable', 'boolean']]);
        $messaging->send($conversation, $request->user(), $data['body'], isset($data['order_id']) ? Order::findOrFail($data['order_id']) : null);

        return redirect()->route('chat.show', [$conversation, 'panel' => $request->boolean('panel') ? 1 : null, 'order_id' => $data['order_id'] ?? null])->with('success', 'Message sent.');
    }
}
