<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRetryRequest;
use App\Models\Order;
use App\Services\DeliveryRetryService;
use Illuminate\Http\Request;

class DeliveryRetryController extends Controller
{
    public function store(Request $request, Order $order, DeliveryRetryService $retries)
    {
        $data = $request->validate(['failed_attempt_id' => ['required', 'integer', 'min:1'], 'proposed_for' => ['required', 'date'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $retries->request($order, $request->user(), (int) $data['failed_attempt_id'], $data['proposed_for'], $data['reason']);

        return back()->with('success', 'Retry proposal recorded. Logistics must review it; no new attempt, assignment, handover or earnings credit was created.');
    }

    public function reject(Request $request, DeliveryRetryRequest $retry, DeliveryRetryService $retries)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $retries->reject($retry, $request->user(), trim($data['reason']));

        return back()->with('success', 'Retry proposal rejected. The rider was notified; parcel custody is unchanged.');
    }
}
