<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DisputeController extends Controller
{
    public function create(Order $order)
    {
        $buyer = $this->authenticatedUser();
        abort_if($order->buyer_id !== $buyer->id, 403);

        if (! in_array($order->status, ['DELIVERED', 'COMPLETED'])) {
            return redirect()->route('buyer.dashboard')->with('error', 'Disputes can only be raised for delivered or completed orders.');
        }

        if ($order->dispute) {
            return redirect()->route('buyer.dashboard')->with('error', 'A dispute case is already open for this order.');
        }

        return view('buyer.disputes.create', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        $buyer = $this->authenticatedUser();
        abort_if($order->buyer_id !== $buyer->id, 403);

        $validated = $request->validate([
            'reason' => 'required|string|in:Damaged Item,Wrong Item Delivered,Missing Items,Non-Delivery,Defective Goods,Other',
            'description' => 'required|string|min:20|max:2000',
            'evidence_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $evidencePath = null;

        try {
            DB::transaction(function () use ($order, $buyer, $validated, $request, &$evidencePath): void {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_if($lockedOrder->buyer_id !== $buyer->id, 403);
                abort_unless(in_array($lockedOrder->status, ['DELIVERED', 'COMPLETED'], true), 422, 'Disputes can only be raised for delivered or completed orders.');
                abort_if($lockedOrder->dispute()->exists(), 422, 'A dispute case is already open for this order.');

                if ($request->hasFile('evidence_file')) {
                    $evidencePath = $request->file('evidence_file')->store('disputes/evidence', 'private');
                }

                Dispute::create([
                    'order_id' => $lockedOrder->id,
                    'buyer_id' => $buyer->id,
                    'seller_id' => $lockedOrder->seller_id,
                    'reason' => $validated['reason'],
                    'description' => $validated['description'],
                    'evidence_path' => $evidencePath,
                    'status' => 'PENDING',
                ]);
            });
        } catch (Throwable $exception) {
            if ($evidencePath !== null) {
                Storage::disk('private')->delete($evidencePath);
            }

            throw $exception;
        }

        return redirect()->route('buyer.dashboard')->with('success', 'Dispute ticket submitted. An administrator will review your evidence.');
    }
}
