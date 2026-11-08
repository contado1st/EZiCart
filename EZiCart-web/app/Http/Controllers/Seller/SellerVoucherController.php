<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerPromotion;
use App\Models\Voucher;
use App\Models\VoucherBudget;
use App\Services\AdministrativeAuditService;
use App\Services\VoucherClaimService;
use App\Services\VoucherFundingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerVoucherController extends Controller
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function index()
    {
        $vouchers = $this->authenticatedUser()->vouchers()->with('budget.reserveAccount')->latest()->paginate(25);

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
            'value' => 'required|numeric|decimal:0,2|min:1|max:1000000',
            'min_spend' => 'nullable|numeric|decimal:0,2|min:0|max:1000000',
            'usage_limit' => 'required|integer|min:1|max:1000000',
            'discount_target' => ['nullable', 'in:merchandise,shipping'],
            'max_discount' => ['required_if:type,percent', 'nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'recipient_limit' => 'nullable|integer|min:1|max:1000000',
            'per_buyer_limit' => 'nullable|integer|min:1|max:1000',
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
                'recipient_limit' => $validated['recipient_limit'] ?? null,
                'per_buyer_limit' => $validated['per_buyer_limit'] ?? 1,
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => true,
                'discount_target' => $validated['discount_target'] ?? 'merchandise', 'max_discount' => $validated['max_discount'] ?? null,
            ]);
            app(VoucherFundingService::class)->reserve($voucher, $this->authenticatedUser());
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
            if ($previous) {
                app(VoucherFundingService::class)->release($locked, $this->authenticatedUser());
            } else {
                app(VoucherFundingService::class)->reserve($locked, $this->authenticatedUser());
            }
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
            if (VoucherBudget::where('voucher_id', $voucher->id)->exists()) {
                app(VoucherFundingService::class)->release($voucher, $this->authenticatedUser());

                return;
            }
            $this->audits->record($this->authenticatedUser(), $voucher, 'seller_voucher_deleted', $voucher->only(['code', 'type', 'value', 'used_count']), [], 'Seller removed a store voucher. Historical order discounts are retained.');
            if ($voucher->used_count > 0 || DB::table('voucher_claims')->where('voucher_id', $voucher->id)->exists()
                || SellerPromotion::where('voucher_id', $voucher->id)->exists()) {
                $voucher->update(['is_active' => false]);
            } else {
                $voucher->delete();
            }
        });

        return back()->with('success', 'Voucher removed from availability. Claimed-voucher history is retained.');
    }

    public function updateLimits(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->seller_id === $request->user()->id, 403);
        $data = $request->validate(['recipient_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'per_buyer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'], 'expires_at' => ['nullable', 'date', 'after:now']]);
        DB::transaction(function () use ($request, $voucher, $data) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            app(VoucherClaimService::class)->synchronizeHistory($locked);
            if (isset($data['recipient_limit']) && $data['recipient_limit'] < $locked->claimed_count) {
                throw ValidationException::withMessages(['recipient_limit' => 'The recipient limit cannot be lower than the existing claim count.']);
            }
            if (isset($data['per_buyer_limit']) && $data['per_buyer_limit'] < (int) DB::table('voucher_claims')->where('voucher_id', $locked->id)->max('redemption_count')) {
                throw ValidationException::withMessages(['per_buyer_limit' => 'The per-buyer limit cannot be lower than an existing redemption count.']);
            }
            $previous = $locked->only(array_keys($data));
            $locked->update($data);
            $this->audits->record($request->user(), $locked, 'voucher_recipient_limits_updated', $previous, $data, 'Seller updated voucher claim limits.');
        });

        return back()->with('success', 'Voucher limits saved.');
    }
}
