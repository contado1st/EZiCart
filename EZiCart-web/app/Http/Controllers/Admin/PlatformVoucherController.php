<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\AdministrativeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformVoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::query()->whereNull('seller_id')->latest()->paginate(20);

        return view('admin.vouchers', compact('vouchers'));
    }

    public function store(Request $request, AdministrativeAuditService $audits)
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code'], 'type' => ['required', 'in:fixed,percent'],
            'value' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', $request->input('type') === 'percent' ? 'max:100' : 'max:99999999'],
            'min_spend' => ['required', 'numeric', 'min:0', 'decimal:0,2'], 'max_discount' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'usage_limit' => ['nullable', 'integer', 'min:1'], 'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        DB::transaction(function () use ($request, $data, $audits): void {
            $voucher = Voucher::create([...$data, 'seller_id' => null, 'is_active' => true]);
            $audits->record($request->user(), $voucher, 'platform_voucher_created', [], $voucher->only(['code', 'type', 'value', 'min_spend', 'max_discount', 'usage_limit']), 'Platform-funded promotion created.');
        });

        return back()->with('success', 'Platform voucher created. Seller vouchers remain scoped to the seller.');
    }

    public function toggle(Request $request, Voucher $voucher, AdministrativeAuditService $audits)
    {
        abort_unless($voucher->seller_id === null, 404);
        DB::transaction(function () use ($request, $voucher, $audits): void {
            $locked = Voucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $previous = (bool) $locked->is_active;
            $locked->update(['is_active' => ! $previous]);
            $audits->record($request->user(), $locked, 'platform_voucher_toggled', ['is_active' => $previous], ['is_active' => ! $previous], 'Platform promotion availability changed.');
        });

        return back()->with('success', 'Platform voucher availability updated.');
    }
}
