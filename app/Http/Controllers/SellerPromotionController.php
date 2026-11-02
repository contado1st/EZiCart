<?php

namespace App\Http\Controllers;

use App\Models\SellerPromotion;
use App\Models\User;
use App\Services\AdministrativeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerPromotionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'seller' || $request->user()->isCommerceAdmin(), 403);
        $promotions = SellerPromotion::with('seller')->when($request->user()->role === 'seller', fn ($q) => $q->where('seller_id', $request->user()->id))->latest()->paginate(20);
        $products = $request->user()->products()->where('compliance_status', 'approved')->where('is_archived', false)->get();
        $vouchers = $request->user()->vouchers()->where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();

        return view('seller.promotions', compact('promotions', 'products', 'vouchers'));
    }

    public function store(Request $request, AdministrativeAuditService $audits)
    {
        abort_unless($request->user()->role === 'seller', 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:100'], 'message' => ['required', 'string', 'max:1000'],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('user_id', $request->user()->id)->where('compliance_status', 'approved')->where('is_archived', false)],
            'voucher_id' => ['nullable', Rule::exists('vouchers', 'id')->where('seller_id', $request->user()->id)->where('is_active', true)],
            'starts_at' => ['required', 'date', 'after_or_equal:today'], 'expires_at' => ['required', 'date', 'after:starts_at', 'after:now']]);
        DB::transaction(function () use ($request, $data, $audits) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (SellerPromotion::where('seller_id', $request->user()->id)->where('created_at', '>=', now()->startOfDay())->count() >= config('web-commerce.promotion_seller_daily_limit')) {
                throw ValidationException::withMessages(['title' => 'You can create one promotional campaign per day.']);
            }
            $promotion = SellerPromotion::create($data + ['seller_id' => $request->user()->id]);
            $audits->record($request->user(), $promotion, 'seller_promotion_created', [], $data, 'Promotion scheduled for opted-in shop followers only.');
        });

        return back()->with('success', 'Promotion scheduled for eligible followers. The scheduler delivers in-app updates while the campaign is active.');
    }

    public function cancel(Request $request, SellerPromotion $promotion, AdministrativeAuditService $audits)
    {
        abort_unless($request->user()->id === $promotion->seller_id || $request->user()->isCommerceAdmin(), 403);
        DB::transaction(function () use ($request, $promotion, $audits) {
            $promotion = SellerPromotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $promotion->update(['cancelled_at' => now()]);
            $audits->record($request->user(), $promotion, 'seller_promotion_cancelled', [], ['cancelled_at' => now()], 'Future deliveries stopped by seller or marketplace administration.');
        });

        return back()->with('success', 'Future promotional deliveries have been stopped.');
    }

    public function preferences(Request $request)
    {
        abort_unless($request->user()->role === 'buyer', 403);
        $data = $request->validate(['seller_promotions_enabled' => ['required', 'boolean']]);
        $request->user()->forceFill($data)->save();

        return back()->with('success', 'Promotion preferences saved.');
    }
}
