<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $this->authenticatedUser()->notifications()->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $ownedNotification = $this->authenticatedUser()->notifications()->whereKey($notification)->firstOrFail();
        $ownedNotification->markAsRead();

        return back();
    }
}
