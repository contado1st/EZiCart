<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscoveryController extends Controller
{
    public function index()
    {
        return redirect()->to(route('home').'#marketplace-for-you');
    }

    public function reset(Request $request)
    {
        DB::table('product_interactions')->where('user_id', $request->user()->id)->delete();

        return redirect()->to(route('home').'#marketplace-for-you')
            ->with('success', 'Browsing history cleared. Recommendations can still use your completed purchases.');
    }

    public function stock(Request $request, StockSubscriptionService $subscriptions)
    {
        $watched = DB::table('stock_subscriptions')->where('user_id', $request->user()->id)->pluck('product_id');
        $products = Product::with(['seller', 'variations'])->whereIn('id', $watched)->latest()->paginate(24);
        $states = $products->getCollection()->mapWithKeys(fn ($product) => [$product->id => $subscriptions->state($product)]);

        return view('buyer.stock', compact('products', 'states'));
    }

    public function watch(Request $request, Product $product, StockSubscriptionService $subscriptions)
    {
        $subscriptions->watch($request->user(), $product);

        return back()->with('success', 'Stock alerts enabled. We will notify you when availability changes.');
    }

    public function unwatch(Request $request, Product $product)
    {
        DB::table('stock_subscriptions')->where('user_id', $request->user()->id)->where('product_id', $product->id)->delete();

        return back()->with('success', 'Stock alerts removed.');
    }
}
