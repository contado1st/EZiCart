<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $categories = [
            ['name' => "Men's Apparel", 'icon' => '👕'],
            ['name' => "Women's Apparel", 'icon' => '👚'],
            ['name' => 'Kids and Baby', 'icon' => '🧸'],
            ['name' => 'Pet Supplies', 'icon' => '🐕'],
            ['name' => 'Electronics', 'icon' => '📱'],
            ['name' => 'Home and Garden', 'icon' => '🏡'],
            ['name' => 'Health and Beauty', 'icon' => '✨'],
            ['name' => 'Sports and Outdoors', 'icon' => '⚽'],
        ];

        // Restrict to approved, non-archived products with inventory
        $query = Product::where('is_archived', false)
            ->where('status', 'approved')
            ->where('stock', '>', 0)
            ->with(['seller', 'images']);

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        // Search by keyword
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->query('search').'%')
                    ->orWhere('description', 'like', '%'.$request->query('search').'%');
            });
        }

        $products = $query->latest()->paginate(12)->withQueryString();

        return view('home', compact('categories', 'products'));
    }

    public function showProduct(Product $product)
    {
        // Only approved products are publicly visible, unless previewed by the product owner or admin
        $isOwnerOrAdmin = auth()->check() && (auth()->id() === $product->user_id || auth()->user()->role === 'admin');
        $isPubliclyAvailable = ($product->status === 'approved' && ! $product->is_archived && $product->stock > 0);

        abort_if(! $isPubliclyAvailable && ! $isOwnerOrAdmin, 404);

        $product->load(['seller', 'images', 'variations', 'activeVoucher', 'reviews.buyer']);

        return view('products.show', compact('product'));
    }
}
