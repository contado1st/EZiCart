<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\AdministrativeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerVoucherController extends Controller
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function index()
    {
        $vouchers = $this->authenticatedUser()->vouchers()->latest()->paginate(10);

        return view('seller.vouchers.index', compact('vouchers'));
    }

    public function store(Request $request)
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:vouchers,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:1',
            'min_spend' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validated['type'] === 'percent' && $validated['value'] > 100) {
            return back()->with('error', 'Percentage discount cannot exceed 100%.');
        }

        DB::transaction(function () use ($validated): void {
            $voucher = $this->authenticatedUser()->vouchers()->create([
                'code' => strtoupper(trim($validated['code'])),
                'type' => $validated['type'],
                'value' => $validated['value'],
                'min_spend' => $validated['min_spend'] ?? 0.00,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => true,
            ]);
            $this->audits->record($this->authenticatedUser(), $voucher, 'seller_voucher_created', [], $voucher->only(['code', 'type', 'value', 'min_spend', 'usage_limit', 'expires_at']), 'Seller-funded store voucher created.');
        });

        return back()->with('success', 'Promotional voucher created successfully.');
    }

    public function toggle(Voucher $voucher)
    {
        abort_if($voucher->seller_id !== $this->authenticatedUser()->id, 403);

        DB::transaction(function () use ($voucher): void {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $previous = (bool) $locked->is_active;
            $locked->update(['is_active' => ! $previous]);
            $this->audits->record($this->authenticatedUser(), $locked, 'seller_voucher_toggled', ['is_active' => $previous], ['is_active' => ! $previous], 'Seller changed store voucher availability.');
        });
        $voucher->refresh();

        $status = $voucher->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Voucher {$voucher->code} has been {$status}.");
    }

    public function destroy(Voucher $voucher)
    {
        abort_if($voucher->seller_id !== $this->authenticatedUser()->id, 403);

        DB::transaction(function () use ($voucher): void {
            $this->audits->record($this->authenticatedUser(), $voucher, 'seller_voucher_deleted', $voucher->only(['code', 'type', 'value', 'used_count']), [], 'Seller removed a store voucher. Historical order discounts are retained.');
            $voucher->delete();
        });

        return back()->with('success', 'Voucher deleted successfully.');
    }
}
