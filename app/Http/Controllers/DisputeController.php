<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function create(Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);

        if (!in_array($order->status, ['DELIVERED', 'COMPLETED'])) {
            return redirect()->route('buyer.dashboard')->with('error', 'Disputes can only be raised for delivered or completed orders.');
        }

        if ($order->dispute) {
            return redirect()->route('buyer.dashboard')->with('error', 'A dispute case is already open for this order.');
        }

        return view('buyer.disputes.create', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);

        if ($order->dispute) {
            return redirect()->route('buyer.dashboard')->with('error', 'A dispute case is already open for this order.');
        }

        $validated = $request->validate([
            'reason'        => 'required|string|in:Damaged Item,Wrong Item Delivered,Missing Items,Non-Delivery,Defective Goods,Other',
            'description'   => 'required|string|min:20|max:2000',
            'evidence_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $evidencePath = null;
        if ($request->hasFile('evidence_file')) {
            $evidencePath = $request->file('evidence_file')->store('disputes/evidence', 'public');
        }

        Dispute::create([
            'order_id'      => $order->id,
            'buyer_id'      => auth()->id(),
            'seller_id'     => $order->seller_id,
            'reason'        => $validated['reason'],
            'description'   => $validated['description'],
            'evidence_path' => $evidencePath,
            'status'        => 'PENDING',
        ]);

        return redirect()->route('buyer.dashboard')->with('success', 'Dispute ticket submitted. An administrator will review your evidence.');
    }
}