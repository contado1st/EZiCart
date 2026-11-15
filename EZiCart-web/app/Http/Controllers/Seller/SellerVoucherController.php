<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\SellerPromotion;
use App\Models\Voucher;
use App\Models\VoucherBudget;
use App\Services\AdministrativeAuditService;
use App\Services\ClaimedVoucherEditService;
use App\Services\VoucherFundingService;
use App\Services\VoucherScheduleRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerVoucherController extends Controller
{
    public function __construct(private AdministrativeAuditService $audits) {}

    public function index()
    {
        $vouchers = $this->authenticatedUser()->vouchers()->with(['seller', 'budget.reserveAccount'])->latest()->paginate(25);
        $availableCents = (int) FinancialAccount::where('key', 'user:'.$this->authenticatedUser()->id.':available')->where('frozen', false)->value('balance_cents');

        $voucherProducts = Product::where('user_id', $this->authenticatedUser()->id)->where('is_archived', false)->orderBy('name')->get(['id', 'name']);

        return view('seller.vouchers.index', compact('vouchers', 'availableCents', 'voucherProducts'));
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
            'visibility' => ['nullable', 'in:public,code_only'],
            'product_scoped' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array', 'max:100', 'required_if:product_scoped,1'],
            'product_ids.*' => ['required', 'integer', 'distinct'],
            ...app(VoucherScheduleRules::class)->fields(),
        ]);
        app(VoucherScheduleRules::class)->assertBounds($validated);

        if ($validated['type'] === 'percent' && $validated['value'] > 100) {
            return back()->with('error', 'Percentage discount cannot exceed 100%.');
        }

        DB::transaction(function () use ($validated): void {
            $ids = array_map('intval', $validated['product_ids'] ?? []);
            if (($validated['product_scoped'] ?? false) && (count($ids) === 0 || Product::where('user_id', $this->authenticatedUser()->id)->whereIn('id', $ids)->count() !== count($ids))) {
                throw ValidationException::withMessages(['product_ids' => 'Select products belonging to your own store.']);
            }
            if (($validated['product_scoped'] ?? false) && ($validated['discount_target'] ?? '') === 'shipping') {
                throw ValidationException::withMessages(['discount_target' => 'Selected-product vouchers apply to merchandise. Use an all-products voucher for shipping.']);
            }
            $voucher = $this->authenticatedUser()->vouchers()->create([
                'code' => strtoupper(trim($validated['code'])),
                'type' => $validated['type'],
                'value' => $validated['value'],
                'min_spend' => $validated['min_spend'] ?? 0.00,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'recipient_limit' => $validated['recipient_limit'] ?? null,
                'per_buyer_limit' => $validated['per_buyer_limit'] ?? 1,
                'expires_at' => $validated['expires_at'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'is_active' => true,
                'visibility' => $validated['visibility'] ?? 'public', 'product_scoped' => (bool) ($validated['product_scoped'] ?? false), 'terms_version' => 2,
                'discount_target' => $validated['discount_target'] ?? 'merchandise', 'max_discount' => $validated['max_discount'] ?? null,
            ]);
            if ($voucher->product_scoped) {
                $voucher->products()->sync($ids);
            }
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
                app(VoucherFundingService::class)->release($voucher, $this->authenticatedUser(), 'removed');

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

    public function updateLimits(Request $request, Voucher $voucher, ClaimedVoucherEditService $edits)
    {
        $data = $request->validate([
            'recipient_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_buyer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'usage_limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
            'value' => ['prohibited'], 'type' => ['prohibited'], 'min_spend' => ['prohibited'],
            'product_ids' => ['prohibited'], 'product_scoped' => ['prohibited'], 'visibility' => ['prohibited'],
            ...app(VoucherScheduleRules::class)->fields(),
        ]);
        $edits->update($request->user(), $voucher, $data);

        return back()->with('success', 'Voucher limits saved.');
    }
}
