<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Services\OrderTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminDisputeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $disputes = Dispute::with(['order', 'buyer', 'seller'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return view('admin.disputes.index', compact('disputes', 'status'));
    }

    public function show(Dispute $dispute)
    {
        $dispute->load(['order.items', 'buyer', 'seller']);

        return view('admin.disputes.show', compact('dispute'));
    }

    public function resolve(Request $request, Dispute $dispute, OrderTransitionService $transitions)
    {
        $validated = $request->validate([
            'status' => 'required|in:UNDER_REVIEW,REFUND_APPROVED,REPLACEMENT_APPROVED,REJECTED,RESOLVED',
            'admin_notes' => 'required|string|max:1500',
        ]);

        DB::transaction(function () use ($dispute, $validated, $transitions, $request): void {
            $order = Order::query()->whereKey($dispute->order_id)->lockForUpdate()->firstOrFail();
            $lockedDispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedDispute->status, ['PENDING', 'UNDER_REVIEW'], true)) {
                throw new HttpException(422, 'This dispute has already been resolved and cannot be changed.');
            }

            $status = $validated['status'];
            if ($status === 'UNDER_REVIEW' && $lockedDispute->status !== 'PENDING') {
                throw new HttpException(422, 'This dispute is already under review.');
            }

            if (in_array($status, ['REFUND_APPROVED', 'REPLACEMENT_APPROVED'], true)) {
                if (! in_array($order->status, ['DELIVERED', 'COMPLETED'], true)) {
                    throw new HttpException(422, 'The order is not in a state that can enter the return workflow.');
                }

                $transitions->transition(
                    $order,
                    $request->user(),
                    OrderStatus::ReturnInTransit,
                    $status === 'REFUND_APPROVED' ? 'dispute_refund_approved_return' : 'dispute_replacement_approved_return',
                    null,
                    $validated['admin_notes'],
                );
            }

            $final = in_array($status, ['REFUND_APPROVED', 'REPLACEMENT_APPROVED', 'REJECTED', 'RESOLVED'], true);
            $lockedDispute->forceFill([
                'status' => $status,
                'admin_notes' => $validated['admin_notes'],
                'resolved_at' => $final ? now() : null,
                'resolved_by' => $final ? $request->user()->id : null,
            ])->save();
        });

        return redirect()->route('admin.disputes.show', $dispute->id)->with('success', 'Dispute case updated successfully.');
    }
}
