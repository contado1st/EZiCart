<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class SellerVoucherController extends Controller
{
    public function index()
    {
        $vouchers = auth()->user()->vouchers()->latest()->paginate(10);
        return view('seller.vouchers.index', compact('vouchers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:50|unique:vouchers,code',
            'type'        => 'required|in:fixed,percent',
            'value'       => 'required|numeric|min:1',
            'min_spend'   => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'expires_at'  => 'nullable|date|after:today',
        ]);

        if ($validated['type'] === 'percent' && $validated['value'] > 100) {
            return back()->with('error', 'Percentage discount cannot exceed 100%.');
        }

        auth()->user()->vouchers()->create([
            'code'        => strtoupper(trim($validated['code'])),
            'type'        => $validated['type'],
            'value'       => $validated['value'],
            'min_spend'   => $validated['min_spend'] ?? 0.00,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'expires_at'  => $validated['expires_at'] ?? null,
            'is_active'   => true,
        ]);

        return back()->with('success', 'Promotional voucher created successfully.');
    }

    public function toggle(Voucher $voucher)
    {
        abort_if($voucher->seller_id !== auth()->id(), 403);

        $voucher->update([
            'is_active' => !$voucher->is_active,
        ]);

        $status = $voucher->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Voucher {$voucher->code} has been {$status}.");
    }

    public function destroy(Voucher $voucher)
    {
        abort_if($voucher->seller_id !== auth()->id(), 403);

        $voucher->delete();

        return back()->with('success', 'Voucher deleted successfully.');
    }
}