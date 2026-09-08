<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDisputeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $disputes = Dispute::with(['order', 'buyer', 'seller'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return view('admin.disputes.index', compact('disputes', 'status'));
    }

    public function show(Dispute $dispute)
    {
        $dispute->load(['order.items', 'buyer', 'seller']);
        return view('admin.disputes.show', compact('dispute'));
    }

    public function resolve(Request $request, Dispute $dispute)
    {
        $validated = $request->validate([
            'status'      => 'required|in:UNDER_REVIEW,REFUND_APPROVED,REPLACEMENT_APPROVED,REJECTED,RESOLVED',
            'admin_notes' => 'required|string|max:1500',
        ]);

        $dispute->update([
            'status'      => $validated['status'],
            'admin_notes' => $validated['admin_notes'],
            'resolved_at' => in_array($validated['status'], ['REFUND_APPROVED', 'REPLACEMENT_APPROVED', 'REJECTED', 'RESOLVED']) 
                ? Carbon::now() 
                : null,
        ]);

        return redirect()->route('admin.disputes.show', $dispute->id)->with('success', 'Dispute case updated successfully.');
    }
}