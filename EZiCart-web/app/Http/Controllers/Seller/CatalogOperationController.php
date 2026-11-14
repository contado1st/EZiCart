<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SellerCatalogOperationService;
use Illuminate\Http\Request;

class CatalogOperationController extends Controller
{
    public function duplicate(Request $request, Product $product, SellerCatalogOperationService $service)
    {
        $data = $request->validate(['operation_key' => ['required', 'uuid']]);
        $draft = $service->duplicate($request->user(), $product, $data['operation_key']);

        return redirect()->route('seller.product-drafts.edit', $draft)->with('success', 'Independent private draft created. Review the copied details and enter new SKUs before submitting.');
    }

    public function bulk(Request $request, SellerCatalogOperationService $service)
    {
        $data = $request->validate(['operation_key' => ['required', 'uuid'], 'action' => ['required', 'in:deactivate,delete'], 'products' => ['required', 'array', 'min:1', 'max:100'], 'products.*' => ['required', 'integer', 'distinct']]);
        $results = $service->bulk($request->user(), array_map('intval', $data['products']), $data['action'], $data['operation_key']);

        return back()->with('success', 'Bulk action finished. Review the item results.')->with('catalog_results', $results);
    }
}
