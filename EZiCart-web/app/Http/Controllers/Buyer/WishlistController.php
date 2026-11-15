<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductWishlistService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->wishlistedProducts()->with('seller:id,role,status,business_name')
            ->withVariantPrices()->withExists(['variations as has_variants',
                'variations as has_stocked_variants' => fn ($q) => $q->where('is_active', true)->where('stock', '>', 0)])
            ->orderByDesc('product_wishlists.created_at')->orderByDesc('products.id')->paginate(25)->withQueryString();
        $cards = $products->getCollection()->map(fn ($product) => ['product' => $product,
            'available' => $product->isListingVisible() && $product->stock > 0
                && (! $product->has_variants || $product->has_stocked_variants)
                && $product->seller?->role === 'seller' && $product->seller?->status === 'approved']);

        return response()->view('buyer.wishlist', compact('products', 'cards'))->header('Cache-Control', 'private, no-store');
    }

    // Authentication stores this GET as the login return path. Saving still requires an explicit POST.
    public function product(Product $product)
    {
        abort_unless($product->isListingVisible() && $product->seller?->role === 'seller' && $product->seller?->status === 'approved', 404);

        return redirect()->route('product.show', $product)->with('success', 'You can now save this product to your wishlist. Select Save to wishlist to confirm.');
    }

    public function store(Request $request, Product $product, ProductWishlistService $wishlist)
    {
        $data = $request->validate(['saved' => ['required', 'boolean']]);
        $wishlist->set($request->user(), $product, (bool) $data['saved']);

        return back()->with('success', $data['saved'] ? 'Product saved to your wishlist.' : 'Product removed from your wishlist.');
    }

    public function destroy(Request $request, Product $product, ProductWishlistService $wishlist)
    {
        $wishlist->set($request->user(), $product, false);

        return back()->with('success', 'Product removed from your wishlist.');
    }
}
