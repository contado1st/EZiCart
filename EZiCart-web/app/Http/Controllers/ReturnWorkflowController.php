<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderFinancialService;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\Request;

class ReturnWorkflowController extends Controller
{
    public function show(Request $request, Order $order, ReturnWorkflowService $workflow)
    {
        abort_unless($workflow->canView($request->user(), $order), 404);
        $order->load('items.product');
        $order->load('dispute.sellerResponse');
        $summary = $workflow->summary($order);
        $actor = $request->user();

        return response()->view('returns.show', compact('order', 'summary', 'actor'))->header('Cache-Control', 'private, no-store');
    }

    public function authorize(Request $request, Order $order, OrderFinancialService $finances)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $finances->authorizeRtsWalletRefund($order, $request->user(), $data['reason']);

        return back()->with('success', 'RTS wallet refund authorization recorded or existing authorization retained. Money has not been sent by this action; review its execution state separately.');
    }
}
