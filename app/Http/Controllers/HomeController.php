<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $categories = [
            ['name' => 'Beauty', 'icon' => '💄'],
            ['name' => 'Home & Living', 'icon' => '🏠'],
            ['name' => 'Fashion', 'icon' => '👗'],
            ['name' => 'Electronics', 'icon' => '🎧'],
            ['name' => 'Groceries', 'icon' => '🛒'],
        ];

        $query = Product::where('is_archived', false)
            ->where('stock', '>', 0)
            ->with('seller');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        // Search by keyword
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query('search') . '%')
                  ->orWhere('description', 'like', '%' . $request->query('search') . '%');
            });
        }

        $products = $query->latest()->paginate(12)->withQueryString();

        return view('home', compact('categories', 'products'));
    }

    public function showProduct(Product $product)
    {
        abort_if($product->is_archived || $product->stock <= 0, 404);

        $product->load('seller');

        return view('products.show', compact('product'));
    }
}