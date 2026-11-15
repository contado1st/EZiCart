<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSale;
use App\Services\AdministrativeAuditService;
use App\Services\ProductSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSaleController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::where('user_id', $request->user()->id)->where('is_archived', false)->orderBy('name')->paginate(25, ['*'], 'products_page');
        $sales = ProductSale::where('seller_id', $request->user()->id)->with('product')->latest()->paginate(25, ['*'], 'sales_page');

        return view('seller.sales.index', compact('products', 'sales'));
    }

    private function data(Request $request, bool $edit): array
    {
        return $request->validate(['product_id' => ['required', 'integer'], 'kind' => ['required', 'in:percent,fixed'], 'value' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'],
            ...($edit ? ['version' => ['required', 'integer', 'min:1']] : ['operation_key' => ['required', 'uuid']])]);
    }

    public function store(Request $request, ProductSaleService $service)
    {
        $service->save($request->user(), $this->data($request, false));

        return back()->with('success', 'Sale scheduled. Future quotes use its price only while its schedule is active.');
    }

    public function update(Request $request, ProductSale $sale, ProductSaleService $service)
    {
        $service->save($request->user(), $this->data($request, true), $sale);

        return back()->with('success', 'Sale updated. Existing orders retain their paid/reserved price.');
    }

    public function end(Request $request, ProductSale $sale, ProductSaleService $service)
    {
        $service->end($request->user(), $sale);

        return back()->with('success', 'Sale ended. New checkouts use current catalog prices.');
    }

    public function limit(Request $request, Product $product, AdministrativeAuditService $audits)
    {
        $data = $request->validate(['purchase_limit' => ['nullable', 'integer', 'min:1', 'max:99999']]);
        DB::transaction(function () use ($request, $product, $data, $audits) {
            $locked = Product::whereKey($product->id)->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only('purchase_limit');
            $locked->forceFill(['purchase_limit' => $data['purchase_limit'] ?? null])->save();
            $audits->record($request->user(), $locked, 'product_purchase_limit', $before, $locked->only('purchase_limit'), 'Per-buyer lifetime cap, shared across all variants; existing orders are retained.');
        });

        return back()->with('success', 'Purchase limit updated. Blank means no lifetime cap.');
    }
}
