<?php

namespace App\Http\Controllers;

use App\Models\SellerPromotion;
use App\Notifications\DirectMessageNotification;
use App\Notifications\OrderMessageNotification;
use App\Notifications\OrderStatusNotification;
use App\Notifications\OrderWorkflowNotification;
use App\Notifications\StockAvailabilityNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['filter' => ['nullable', 'in:all,unread'], 'category' => ['nullable', 'in:all,orders,messages,stock,account,promotions']]);
        $owned = $this->authenticatedUser()->notifications();
        $totalCount = (clone $owned)->count();
        $unreadCount = (clone $owned)->whereNull('read_at')->count();
        $notifications = (clone $owned)
            ->when(($filters['filter'] ?? 'all') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when(($filters['category'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $messageType = OrderMessageNotification::class;
                $stockType = StockAvailabilityNotification::class;
                match ($filters['category']) {
                    'messages' => $query->whereIn('type', [$messageType, DirectMessageNotification::class]),
                    'stock' => $query->where('type', $stockType),
                    'promotions' => $query->where('data->category', 'promotions'),
                    'orders' => $query->whereIn('type', [OrderWorkflowNotification::class, OrderStatusNotification::class]),
                    'account' => $query->whereNotIn('type', [$messageType, DirectMessageNotification::class, $stockType, OrderWorkflowNotification::class, OrderStatusNotification::class])->where(fn ($q) => $q->whereNull('data->category')->orWhere('data->category', '!=', 'promotions')),
                };
            })->latest()->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications', 'totalCount', 'unreadCount'));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $ownedNotification = $this->authenticatedUser()->notifications()->whereKey($notification)->firstOrFail();
        $ownedNotification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->authenticatedUser()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $owned = $this->authenticatedUser()->notifications()->whereKey($notification)->firstOrFail();
        $owned->markAsRead();
        if (($owned->data['category'] ?? null) === 'promotions') {
            $promotion = SellerPromotion::find($owned->data['promotion_id'] ?? 0);
            if (! $promotion || $promotion->cancelled_at || $promotion->expires_at->isPast()) {
                return redirect()->route('notifications.index')->with('success', 'This promotion has ended.');
            }
        }
        $url = (string) ($owned->data['url'] ?? '');
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $allowedHosts = array_filter([$request->getHost(), parse_url(config('app.url'), PHP_URL_HOST)]);
        if (! is_array($parts) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')
            || (isset($parts['scheme']) && ! in_array($parts['scheme'], ['http', 'https'], true))
            || (isset($parts['host']) && ! in_array($parts['host'], $allowedHosts, true))) {
            return redirect()->route('notifications.index');
        }

        return redirect($path.(isset($parts['query']) ? '?'.$parts['query'] : ''));
    }
}
