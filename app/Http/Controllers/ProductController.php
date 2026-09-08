<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
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
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        auth()->user()->products()->create([
            'name'        => $validated['name'],
            'description' => $validated['description'],
            'category'    => $validated['category'],
            'price'       => $validated['price'],
            'stock'       => $validated['stock'],
            'image_path'  => $imagePath,
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        // Ensure seller only edits their own products
        abort_if($product->user_id !== auth()->id(), 403);

        return view('seller.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_archived' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            // Remove previous image if one exists
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }
            $product->image_path = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'name'        => $validated['name'],
            'description' => $validated['description'],
            'category'    => $validated['category'],
            'price'       => $validated['price'],
            'stock'       => $validated['stock'],
            'image_path'  => $product->image_path,
            'is_archived' => $request->has('is_archived'),
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        abort_if($product->user_id !== auth()->id(), 403);

        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()->route('seller.products.index')->with('success', 'Product deleted successfully.');
    }
}