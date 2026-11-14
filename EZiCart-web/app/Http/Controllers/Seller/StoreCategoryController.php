<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Services\StoreCategoryService;
use Illuminate\Http\Request;

class StoreCategoryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['product_id' => ['nullable', 'integer']]);
        $categories = StoreCategory::where('seller_id', $request->user()->id)->with('parent')->withCount('products')->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')->paginate(25)->withQueryString();
        $options = StoreCategory::where('seller_id', $request->user()->id)->orderBy('sort_order')->orderBy('id')->get();
        $selectedProduct = isset($data['product_id']) ? Product::where('user_id', $request->user()->id)->with('storeCategories')->findOrFail($data['product_id']) : null;

        return view('seller.categories.index', compact('categories', 'options', 'selectedProduct'));
    }

    private function data(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:50'], 'parent_id' => ['nullable', 'integer', 'min:1'], 'position' => ['required', 'integer', 'min:1', 'max:200'], 'is_visible' => ['required', 'boolean']]);
    }

    public function store(Request $request, StoreCategoryService $service)
    {
        $service->save($request->user(), $this->data($request));

        return back()->with('success', 'Store category created.');
    }

    public function update(Request $request, StoreCategory $category, StoreCategoryService $service)
    {
        $service->save($request->user(), $this->data($request), $category);

        return back()->with('success', 'Store category updated.');
    }

    public function preview(Request $request, StoreCategory $category, StoreCategoryService $service)
    {
        return view('seller.categories.delete', ['category' => $category, ...$service->preview($request->user(), $category)]);
    }

    public function destroy(Request $request, StoreCategory $category, StoreCategoryService $service)
    {
        $data = $request->validate(['preview_token' => ['required', 'string', 'size:64']]);
        $service->delete($request->user(), $category, $data['preview_token']);

        return redirect()->route('seller.categories.index')->with('success', 'Category removed. Products and other memberships are preserved.');
    }

    public function assign(Request $request, Product $product, StoreCategoryService $service)
    {
        $data = $request->validate(['categories' => ['nullable', 'array', 'max:3'], 'categories.*' => ['integer', 'distinct']]);
        $service->assign($request->user(), $product, array_map('intval', $data['categories'] ?? []));

        return back()->with('success', 'Product store categories saved.');
    }
}
