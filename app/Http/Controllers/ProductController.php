<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        // Fetch only the logged-in seller's products
        $products = auth()->user()->products()->latest()->get();
        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            // Save to storage/app/public/products
            $imagePath = $request->file('image')->store('products', 'public');
        }

        auth()->user()->products()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image_path' => $imagePath,
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Product added successfully!');
    }
    
    // (We will implement edit/update/destroy in the next step)
}