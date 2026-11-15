<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReturnInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnInspectionController extends Controller
{
    public function store(Request $request, Order $order, ReturnInspectionService $inspections): RedirectResponse
    {
        abort_unless($order->seller_id === $request->user()->id, 403);
        $data = $request->validate(['restock' => ['required_without:quantities', 'boolean'], 'quantities' => ['required_without:restock', 'array'],
            'quantities.*' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $inspections->inspect($order, $request->user(), $data);

        return back()->with('success', 'Inspection recorded. Only approved sellable quantities were restored; excluded units remain out of stock. No refund or earnings payment was issued by inspection.');
    }
}
