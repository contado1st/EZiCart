<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\BundleDeal;
use App\Models\FlashSale;
use App\Models\Product;
use App\Services\BundleDealService;
use App\Services\FlashSaleService;
use Illuminate\Http\Request;

class FlashBundleController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::where('user_id', $request->user()->id)->where('is_archived', false)->orderBy('name')->get(['id', 'name']);
        $slots = FlashSale::where('seller_id', $request->user()->id)->with('product')->latest()->paginate(25, ['*'], 'slots_page')->withQueryString();
        $deals = BundleDeal::where('seller_id', $request->user()->id)->with('products')->latest()->paginate(25, ['*'], 'deals_page')->withQueryString();

        return view('seller.sales.flash-bundles', compact('products', 'slots', 'deals'));
    }

    public function flash(Request $request, FlashSaleService $service)
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'], 'quantity_limit' => ['required', 'integer', 'min:1', 'max:99999'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'operation_key' => ['required', 'uuid']]);
        $service->create($request->user(), $data);

        return back()->with('success', 'Flash slot scheduled. Orders reserve both physical stock and limited flash units.');
    }

    public function bundle(Request $request, BundleDealService $service)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'kind' => ['required', 'in:percent,gift'], 'threshold' => ['required', 'integer', 'min:2', 'max:99'],
            'product_ids' => ['required', 'array', 'min:1', 'max:100'], 'product_ids.*' => ['required', 'integer', 'distinct'],
            'percent' => ['required_if:kind,percent', 'nullable', 'integer', 'min:1', 'max:99'], 'gift_product_id' => ['required_if:kind,gift', 'nullable', 'integer'], 'gift_quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'operation_key' => ['required', 'uuid']]);
        $service->create($request->user(), $data);

        return back()->with('success', 'Bundle scheduled. Qualifying items and any gift must be selected together at checkout.');
    }

    public function endFlash(Request $request, FlashSale $slot, FlashSaleService $service)
    {
        $service->end($request->user(), $slot);

        return back()->with('success', 'Flash offer ended; placed orders retain their price.');
    }

    public function endBundle(Request $request, BundleDeal $deal, BundleDealService $service)
    {
        $service->end($request->user(), $deal);

        return back()->with('success', 'Bundle ended; placed orders retain their price.');
    }
}
