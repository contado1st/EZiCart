<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Notifications\OrderMessageNotification;
use App\Notifications\OrderStatusNotification;
use App\Notifications\OrderWorkflowNotification;
use App\Notifications\StockAvailabilityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    /**
     * Get paginated notifications for the buyer
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $filter = $request->input('filter', 'all');
        $category = $request->input('category', 'all');
        $perPage = min((int) $request->input('per_page', 20), 50);

        $owned = $buyer->notifications();
        $totalCount = (clone $owned)->count();
        $unreadCount = (clone $owned)->whereNull('read_at')->count();

        $query = (clone $owned)
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($category !== 'all', function ($q) use ($category) {
                $messageType = OrderMessageNotification::class;
                $stockType = StockAvailabilityNotification::class;
                match ($category) {
                    'messages' => $q->whereIn('type', [$messageType, DirectMessageNotification::class]),
                    'stock' => $q->where('type', $stockType),
                    'promotions' => $q->where('data->category', 'promotions'),
                    'orders' => $q->whereIn('type', [OrderWorkflowNotification::class, OrderStatusNotification::class]),
                    'account' => $q->whereNotIn('type', [$messageType, DirectMessageNotification::class, $stockType, OrderWorkflowNotification::class, OrderStatusNotification::class])
                        ->where(fn ($sub) => $sub->whereNull('data->category')->orWhere('data->category', '!=', 'promotions')),
                    default => null,
                };
            })->latest();

        $notifications = $query->paginate($perPage);

        $items = collect($notifications->items())->map(function ($n) {
            $data = is_array($n->data) ? $n->data : [];
            $type = $n->type;

            // Infer friendly category & title
            $cat = 'account';
            $title = 'Notification';
            if ($type === OrderStatusNotification::class || $type === OrderWorkflowNotification::class) {
                $cat = 'orders';
                $title = isset($data['status']) ? 'Order '.str_replace('_', ' ', $data['status']) : 'Order Update';
            } elseif ($type === DirectMessageNotification::class || $type === OrderMessageNotification::class) {
                $cat = 'messages';
                $title = 'New Message';
            } elseif ($type === StockAvailabilityNotification::class) {
                $cat = 'stock';
                $title = 'Stock Update';
            } elseif (($data['category'] ?? '') === 'promotions') {
                $cat = 'promotions';
                $title = $data['title'] ?? 'Store Promotion';
            }

            return [
                'id' => $n->id,
                'category' => $cat,
                'title' => $data['title'] ?? $title,
                'message' => $data['message'] ?? '',
                'data' => $data,
                'order_id' => $data['order_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'is_read' => $n->read_at !== null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'total_count' => $totalCount,
            'unread_count' => $unreadCount,
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ]);
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $count = $buyer->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a single notification as read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $notification = $buyer->notifications()->whereKey($id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $buyer->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Get promotional preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();

        return response()->json([
            'success' => true,
            'seller_promotions_enabled' => (bool) ($buyer->seller_promotions_enabled ?? true),
        ]);
    }

    /**
     * Update promotional preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $buyer */
        $buyer = $request->user();
        $data = $request->validate([
            'seller_promotions_enabled' => ['required', 'boolean'],
        ]);

        $buyer->forceFill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Promotion preferences updated successfully.',
            'seller_promotions_enabled' => (bool) $buyer->seller_promotions_enabled,
        ]);
    }
}

