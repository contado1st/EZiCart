<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectConversation;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\User;
use App\Services\ChatAttachmentService;
use App\Services\DirectMessagingService;
use App\Services\MessagingInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatApiController extends Controller
{
    private function formatPartner(User $partner): array
    {
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
            $latest = $t->latestMessage;

            return [
                'id' => $t->id,
                'partner' => $this->formatPartner($partner),
                'last_message' => $latest?->body ?? '',
                'last_message_at' => $latest?->created_at?->toIso8601String() ?? $t->updated_at?->toIso8601String() ?? '',
                'unread_count' => (int) ($t->unread_count ?? 0),
            ];
        });

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
                'messages' => $messages->map(fn ($m) => [
                    'id' => $m->id,
                    'sender_id' => $m->sender_id,
                    'is_me' => $m->sender_id === $buyer->id,
                    'body' => $m->body,
                    'created_at' => $m->created_at->toIso8601String(),
                    'attachments' => $m->attachments->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->original_name,
                        'mime_type' => $a->mime_type,
                        'size' => (int) $a->size,
                        'url' => url('/api/v1/chat/attachments/'.$a->id),
                        'preview_url' => url('/api/v1/chat/attachments/'.$a->id.'?preview=1'),
                        'is_image' => str_starts_with((string) $a->mime_type, 'image/'),
                    ])->values()->all(),
                ]),
            ],
        ]);
    }

    /**
     * Send message in thread (supports up to 3 attachments, max 2MB each, 2000 chars body)
     */
    public function sendMessage(int $id, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $conversation = DirectConversation::find($id);

        if (! $conversation || ! $conversation->hasParticipant($buyer)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['required', 'file', 'max:2048'], // 2MB limit per file
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $order = ! empty($validated['order_id']) ? Order::find($validated['order_id']) : null;
        $files = $request->file('attachments', []);

        if (! empty($files)) {
            $msg = app(ChatAttachmentService::class)->send($conversation, $buyer, $validated['body'] ?? null, $files, $order);
        } else {
            $msg = $messaging->send($conversation, $buyer, $validated['body'], $order);
        }

        $msg->load('attachments');

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data' => [
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'is_me' => true,
                'body' => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
                'attachments' => $msg->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'mime_type' => $a->mime_type,
                    'size' => (int) $a->size,
                    'url' => url('/api/v1/chat/attachments/'.$a->id),
                    'preview_url' => url('/api/v1/chat/attachments/'.$a->id.'?preview=1'),
                    'is_image' => str_starts_with((string) $a->mime_type, 'image/'),
                ])->values()->all(),
            ],
        ], 201);
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
        abort_unless(Storage::disk('private')->exists($attachment->path), 404);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($request->boolean('preview') && in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Storage::disk('private')->response($attachment->path, $attachment->original_name, $headers + ['Content-Type' => $attachment->mime_type]);
        }

        return Storage::disk('private')->download($attachment->path, $attachment->original_name, $headers);
    }

    /**
     * Start conversation with a seller
     */
    public function start(int $sellerId, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $seller = User::find($sellerId);

        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Recipient not found.'], 404);
        }

        try {
            $conversation = $messaging->start($buyer, $seller);

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
