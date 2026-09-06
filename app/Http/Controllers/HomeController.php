<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $categories = [
            ['name' => 'Beauty', 'icon' => '💄'],
            ['name' => 'Home & Living', 'icon' => '🏠'],
            ['name' => 'Fashion', 'icon' => '👗'],
            ['name' => 'Electronics', 'icon' => '🎧'],
            ['name' => 'Groceries', 'icon' => '🛒'],
        ];

        $freshPicks = [
            ['id' => 1, 'name' => 'Reusable Water Bottle', 'category' => 'Home & Living', 'price' => 299.00, 'image' => null],
            ['id' => 2, 'name' => 'Everyday Canvas Tote', 'category' => 'Fashion', 'price' => 349.00, 'image' => null],
            ['id' => 3, 'name' => 'Wireless Mini Speaker', 'category' => 'Electronics', 'price' => 899.00, 'image' => null],
            ['id' => 4, 'name' => 'Organic Face Serum', 'category' => 'Beauty', 'price' => 450.00, 'image' => null],
        ];

        return view('home', compact('categories', 'freshPicks'));
    }
}