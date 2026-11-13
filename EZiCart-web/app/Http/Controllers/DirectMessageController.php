<?php

namespace App\Http\Controllers;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Services\ChatAttachmentService;
use App\Services\DirectMessagingService;
use App\Services\MessagingInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $data = $request->validate(['order_id' => ['nullable', 'integer', 'exists:orders,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id'], 'panel' => ['nullable', 'boolean']]);
        if (isset($data['product_id'])) {
            $messaging->authorizeProduct(Product::findOrFail($data['product_id']), $request->user(), $recipient);
            abort_unless($request->user()->role === 'buyer', 403);
        }
        if (isset($data['order_id'])) {
            $order = Order::findOrFail($data['order_id']);
            $messaging->authorizeOrder($order, $request->user());
            $messaging->authorizeOrder($order, $recipient);
        }
        $conversation = $messaging->start($request->user(), $recipient);

        return redirect()->route('chat.show', [$conversation, 'panel' => $request->boolean('panel') ? 1 : null, 'order_id' => $data['order_id'] ?? null, 'product_id' => $data['product_id'] ?? null]);
    }

    public function show(Request $request, DirectConversation $conversation, MessagingInboxService $inbox, DirectMessagingService $messaging)
    {
        $actor = $request->user();
        abort_unless($conversation->hasParticipant($actor), 403);
        $data = $request->validate(['order_id' => ['nullable', 'integer', 'exists:orders,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id']]);
        $partner = $conversation->otherUser($actor);
        $productContext = isset($data['product_id']) ? Product::withVariantPrices()->findOrFail($data['product_id']) : null;
        if ($productContext) {
            $messaging->authorizeProduct($productContext, $actor, $partner);
        }
        $order = isset($data['order_id']) ? Order::findOrFail($data['order_id']) : null;
        if ($order) {
            $messaging->authorizeOrder($order, $actor);
            $messaging->authorizeOrder($order, $partner);
        }
        $conversation->messages()->where('recipient_id', $actor->id)->whereNull('read_at')->update(['read_at' => now()]);
        $actor->unreadNotifications()->where('type', DirectMessageNotification::class)->where('data->conversation_id', $conversation->id)->update(['read_at' => now()]);
        $messages = $conversation->messages()->with(['sender', 'attachments'])->latest('id')->paginate(40, ['*'], 'messages_page')->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());

        return view('orders.messages', ['conversation' => $conversation, 'partner' => $partner, 'messages' => $messages,
            'threads' => $inbox->threads($request), 'order' => $order, 'orderUrl' => $order ? $inbox->orderUrl($actor, $order) : null,
            'canSend' => $messaging->canContact($actor, $partner), 'productContext' => $productContext]);
    }

    public function store(Request $request, DirectConversation $conversation, DirectMessagingService $messaging)
    {
        abort_unless($conversation->hasParticipant($request->user()), 403);
        $data = $request->validate([
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['required', 'file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt', 'max:'.ChatAttachmentService::maxKilobytes()],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id'], 'panel' => ['nullable', 'boolean'],
        ]);
        app(ChatAttachmentService::class)->send($conversation, $request->user(), $data['body'] ?? null, $request->file('attachments', []), isset($data['order_id']) ? Order::findOrFail($data['order_id']) : null,
            isset($data['product_id']) ? Product::findOrFail($data['product_id']) : null);

        return redirect()->route('chat.show', [$conversation, 'panel' => $request->boolean('panel') ? 1 : null, 'order_id' => $data['order_id'] ?? null])->with('success', 'Message sent.');
    }

    public function productImage(Request $request, DirectMessage $message)
    {
        abort_unless($message->conversation->hasParticipant($request->user()), 403);
        $path = $message->product_context['image_path'] ?? null;
        abort_unless(is_string($path) && Storage::disk('public')->exists($path), 404);
        $mime = Storage::disk('public')->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk('public')->response($path, 'product-enquiry', ['Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"], 'inline');
    }
}
