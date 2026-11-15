<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ChatAttachmentService;
use App\Services\DirectMessagingService;
use App\Services\MessageRetractionService;
use App\Services\MessagingInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatApiController extends Controller
{
    private function formatPartner(?User $partner): ?array
    {
        if (! $partner) {
            return null;
        }

        $onlineMinutes = (int) config('web-commerce.online_threshold_minutes', 10);
        $isOnline = $partner->last_web_activity_at && $partner->last_web_activity_at->gte(now()->subMinutes($onlineMinutes));
        $activeText = $isOnline
            ? 'Active now'
            : ($partner->last_web_activity_at
                ? 'Active '.$partner->last_web_activity_at->diffForHumans()
                : ($partner->last_web_login_at ? 'Active '.$partner->last_web_login_at->diffForHumans() : 'Offline'));

        return [
            'id' => $partner->id,
            'name' => $partner->business_name ?: trim("{$partner->first_name} {$partner->last_name}"),
            'role' => $partner->role,
            'avatar_url' => $partner->profile_photo_path ? asset('storage/'.$partner->profile_photo_path) : null,
            'is_online' => (bool) $isOnline,
            'last_active_at' => $partner->last_web_activity_at?->toIso8601String() ?? $partner->last_web_login_at?->toIso8601String(),
            'last_active_text' => $activeText,
            'shop_status' => $partner->shop_availability ?? 'open',
        ];
    }

    /**
     * Get buyer conversation threads list
     */
    public function threads(Request $request, MessagingInboxService $inbox): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $threads = $inbox->threads($request);

        $data = collect($threads->items())->map(function (DirectConversation $t) use ($buyer) {
            $partner = $t->otherUser($buyer);
            if (! $partner) {
                return null;
            }

            $latest = $t->latestMessage;
            $isUnsent = $latest && $latest->unsent_at !== null;
            $lastBody = $isUnsent
                ? ($latest->sender_id === $buyer->id ? 'You unsent this message.' : 'This message was unsent.')
                : ($latest?->body ?? '');

            return [
                'id' => $t->id,
                'partner' => $this->formatPartner($partner),
                'last_message' => $lastBody,
                'last_message_at' => $latest?->created_at?->toIso8601String() ?? $t->updated_at?->toIso8601String() ?? '',
                'unread_count' => (int) ($t->unread_count ?? 0),
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get message history for conversation
     */
    public function show(int $id, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $conversation = DirectConversation::find($id);

        if (! $conversation || ! $conversation->hasParticipant($buyer)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $partner = $conversation->otherUser($buyer);
        $canSend = $partner ? $messaging->canContact($buyer, $partner) : false;

        // Mark incoming messages as read
        $conversation->messages()
            ->where('recipient_id', $buyer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with(['sender:id,first_name,last_name,business_name', 'attachments'])
            ->latest('id')
            ->take(60)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'partner' => $this->formatPartner($partner),
                'can_send' => $canSend,
                'messages' => $messages->map(function ($m) use ($buyer) {
                    $isMe = $m->sender_id === $buyer->id;
                    $isUnsent = $m->unsent_at !== null;
                    $body = $isUnsent ? ($isMe ? 'You unsent this message.' : 'This message was unsent.') : $m->body;

                    return [
                        'id' => $m->id,
                        'sender_id' => $m->sender_id,
                        'is_me' => $isMe,
                        'is_unsent' => $isUnsent,
                        'body' => $body,
                        'created_at' => $m->created_at->toIso8601String(),
                        'product_context' => (! $isUnsent && $m->product_context) ? [
                            'product_id' => $m->product_context['product_id'] ?? null,
                            'name' => $m->product_context['name'] ?? null,
                            'price_label' => $m->product_context['price_label'] ?? null,
                            'image_url' => ! empty($m->product_context['image_path']) ? asset('storage/'.$m->product_context['image_path']) : null,
                        ] : null,
                        'attachments' => $isUnsent ? [] : $m->attachments->map(fn ($a) => [
                            'id' => $a->id,
                            'name' => $a->unsent_at ? 'Attachment unsent' : $a->original_name,
                            'mime_type' => $a->mime_type,
                            'size' => (int) $a->size,
                            'url' => url('/api/v1/chat/attachments/'.$a->id),
                            'preview_url' => url('/api/v1/chat/attachments/'.$a->id.'?preview=1'),
                            'download_url' => url('/api/v1/chat/attachments/'.$a->id.'?download=1'),
                            'is_image' => in_array($a->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true),
                            'is_unsent' => $a->unsent_at !== null,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    /**
     * Send message in thread (supports up to 3 attachments, max per ChatAttachmentService, allowed web extensions, 2000 chars body)
     */
    public function sendMessage(int $id, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $conversation = DirectConversation::find($id);

        if (! $conversation || ! $conversation->hasParticipant($buyer)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $partner = $conversation->otherUser($buyer);
        if (! $partner || ! $messaging->canContact($buyer, $partner)) {
            return response()->json([
                'success' => false,
                'message' => 'Messaging is unavailable because this contact is no longer active or related to your account.',
            ], 403);
        }

        // Normalize single attachment uploads so both single and multi-file inputs are gracefully accepted
        if ($request->hasFile('attachment') && ! $request->hasFile('attachments')) {
            $request->files->set('attachments', [$request->file('attachment')]);
        } elseif ($request->hasFile('attachments') && ! is_array($request->file('attachments'))) {
            $request->files->set('attachments', [$request->file('attachments')]);
        }

        if ($request->has('body') && trim((string) $request->input('body')) === '') {
            $request->merge(['body' => null]);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => [
                'required',
                'file',
                'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt',
                'max:'.ChatAttachmentService::maxKilobytes(),
            ],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $order = ! empty($validated['order_id']) ? Order::find($validated['order_id']) : null;
        if ($order) {
            $messaging->authorizeOrder($order, $buyer);
            $messaging->authorizeOrder($order, $partner);
        }

        $product = ! empty($validated['product_id']) ? Product::withVariantPrices()->find($validated['product_id']) : null;
        if ($product) {
            $messaging->authorizeProduct($product, $buyer, $partner);
        }

        $files = $request->file('attachments', []);

        if (! empty($files)) {
            $msg = app(ChatAttachmentService::class)->send($conversation, $buyer, $validated['body'] ?? null, $files, $order, $product);
        } else {
            $msg = $messaging->send($conversation, $buyer, $validated['body'], $order, $product);
        }

        $msg->load('attachments');

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data' => [
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'is_me' => true,
                'is_unsent' => false,
                'body' => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
                'product_context' => $msg->product_context ? [
                    'product_id' => $msg->product_context['product_id'] ?? null,
                    'name' => $msg->product_context['name'] ?? null,
                    'price_label' => $msg->product_context['price_label'] ?? null,
                    'image_url' => ! empty($msg->product_context['image_path']) ? asset('storage/'.$msg->product_context['image_path']) : null,
                ] : null,
                'attachments' => $msg->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'mime_type' => $a->mime_type,
                    'size' => (int) $a->size,
                    'url' => url('/api/v1/chat/attachments/'.$a->id),
                    'preview_url' => url('/api/v1/chat/attachments/'.$a->id.'?preview=1'),
                    'download_url' => url('/api/v1/chat/attachments/'.$a->id.'?download=1'),
                    'is_image' => in_array($a->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true),
                    'is_unsent' => false,
                ])->values()->all(),
            ],
        ], 201);
    }

    /**
     * Unsend message for everyone
     */
    public function unsendMessage(int $conversationId, int $messageId, Request $request, MessageRetractionService $retractions): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $conversation = DirectConversation::find($conversationId);
        if (! $conversation || ! $conversation->hasParticipant($buyer)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $message = DirectMessage::where('conversation_id', $conversationId)->whereKey($messageId)->first();
        if (! $message || $message->sender_id !== $buyer->id) {
            return response()->json(['success' => false, 'message' => 'Message not found or not owned by you.'], 403);
        }

        $retractions->message($message, $buyer);

        return response()->json([
            'success' => true,
            'message' => 'Message unsent for everyone.',
        ]);
    }

    /**
     * Unsend specific attachment
     */
    public function unsendAttachment(int $conversationId, int $attachmentId, Request $request, MessageRetractionService $retractions): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $conversation = DirectConversation::find($conversationId);
        if (! $conversation || ! $conversation->hasParticipant($buyer)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $attachment = MessageAttachment::whereHas('message', fn ($q) => $q->where('conversation_id', $conversationId)->where('sender_id', $buyer->id))->whereKey($attachmentId)->first();
        if (! $attachment) {
            return response()->json(['success' => false, 'message' => 'Attachment not found or not owned by you.'], 403);
        }

        $retractions->attachment($attachment, $buyer);

        return response()->json([
            'success' => true,
            'message' => 'Attachment unsent.',
        ]);
    }

    /**
     * Download / preview chat message attachment
     */
    public function attachment(int $id, Request $request)
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $attachment = MessageAttachment::with('message.conversation')->findOrFail($id);

        abort_unless($attachment->message->conversation->hasParticipant($buyer), 403);
        abort_if($attachment->unsent_at, 410);
        abort_unless(Storage::disk('private')->exists($attachment->path), 404);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        $isImage = in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
        $isPdf = in_array($attachment->mime_type, ['application/pdf', 'pdf'], true);

        if (! $request->boolean('download') && ($isImage || ($isPdf && $request->boolean('preview')))) {
            return Storage::disk('private')->response(
                $attachment->path,
                $attachment->original_name,
                $headers + ['Content-Type' => $attachment->mime_type]
            );
        }

        return Storage::disk('private')->download($attachment->path, $attachment->original_name, $headers);
    }

    /**
     * Start conversation with a recipient (seller, admin, or contact)
     */
    public function start(int $recipientId, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $recipient = User::find($recipientId);

        if (! $recipient) {
            return response()->json(['success' => false, 'message' => 'Recipient not found.'], 404);
        }

        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        if (isset($validated['product_id'])) {
            $messaging->authorizeProduct(Product::findOrFail($validated['product_id']), $buyer, $recipient);
        }
        if (isset($validated['order_id'])) {
            $order = Order::findOrFail($validated['order_id']);
            $messaging->authorizeOrder($order, $buyer);
            $messaging->authorizeOrder($order, $recipient);
        }

        try {
            $conversation = $messaging->start($buyer, $recipient);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
