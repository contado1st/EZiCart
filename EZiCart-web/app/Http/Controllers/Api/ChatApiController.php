<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectConversation;
use App\Models\Order;
use App\Models\User;
use App\Services\DirectMessagingService;
use App\Services\MessagingInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatApiController extends Controller
{
    /**
     * Get buyer conversation threads list
     */
    public function threads(Request $request, MessagingInboxService $inbox): JsonResponse
    {
        $buyer = $request->user();
        $threads = $inbox->threads($request);

        $data = $threads->map(function ($t) use ($buyer) {
            return [
                'id' => $t->id,
                'partner' => [
                    'id' => $t->partner->id,
                    'name' => $t->partner->business_name ?? trim("{$t->partner->first_name} {$t->partner->last_name}"),
                    'role' => $t->partner->role,
                ],
                'last_message' => $t->last_message?->body ?? '',
                'last_message_at' => $t->last_message?->created_at?->toIso8601String() ?? $t->updated_at->toIso8601String(),
                'unread_count' => $t->unread_count ?? 0,
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
            ->with('sender:id,first_name,last_name,business_name')
            ->latest('id')
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->business_name ?? trim("{$partner->first_name} {$partner->last_name}"),
                    'role' => $partner->role,
                ],
                'messages' => $messages->map(fn ($m) => [
                    'id' => $m->id,
                    'sender_id' => $m->sender_id,
                    'is_me' => $m->sender_id === $buyer->id,
                    'body' => $m->body,
                    'created_at' => $m->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Send message in thread
     */
    public function sendMessage(int $id, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        $buyer = $request->user();
        $conversation = DirectConversation::find($id);

        if (! $conversation || ! $conversation->hasParticipant($buyer)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $order = ! empty($validated['order_id']) ? Order::find($validated['order_id']) : null;

        $msg = $messaging->send($conversation, $buyer, $validated['body'], $order);

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data' => [
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'is_me' => true,
                'body' => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Start conversation with a seller
     */
    public function start(int $sellerId, Request $request, DirectMessagingService $messaging): JsonResponse
    {
        $buyer = $request->user();
        $seller = User::find($sellerId);

        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Recipient not found.'], 404);
        }

        $conversation = $messaging->start($buyer, $seller);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
        ]);
    }
}

