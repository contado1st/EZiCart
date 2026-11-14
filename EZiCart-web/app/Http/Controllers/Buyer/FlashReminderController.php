<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Services\FlashReminderService;
use Illuminate\Http\Request;

class FlashReminderController extends Controller
{
    public function store(Request $request, FlashSale $slot, FlashReminderService $service)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $service->subscribe($request->user(), $slot, (bool) $data['enabled']);

        return back()->with('success', $data['enabled'] ? 'Reminder saved. Advance reminders are sent about one minute before the slot; late subscriptions receive current status.' : 'Flash reminder cancelled.');
    }
}
