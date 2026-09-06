<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        return back()->withErrors(['email' => 'Invalid credentials or account pending admin approval.']);
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|email|unique:users,email',
            'contact_no' => 'required|numeric',
            'birthday' => 'required|date',
            'age' => 'required|integer|min:18',
            'province' => 'required|string',
            'municipality' => 'required|string',
            'barangay' => 'required|string',
            'street_address' => 'required|string',
            'id_upload' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($request->hasFile('id_upload')) {
            $path = $request->file('id_upload')->store('buyer_ids', 'public');
        }

        return redirect()->route('login')->with('success', 'Registration submitted! Please wait for administrator approval sent via email.');
    }

    public function showSellerRegisterForm()
    {
        return view('auth.register-seller');
    }

    public function sellerRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|email|unique:users,email',
            'contact_no' => 'required|numeric',
            'birthday' => 'required|date',
            'age' => 'required|integer|min:18',
            'province' => 'required|string',
            'municipality' => 'required|string',
            'barangay' => 'required|string',
            'street_address' => 'required|string',
            'business_name' => 'required|string|max:150',
            'line_of_business' => 'required|string',
            'id_upload' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'business_permit' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($request->hasFile('id_upload')) {
            $request->file('id_upload')->store('seller_ids', 'public');
        }

        if ($request->hasFile('business_permit')) {
            $request->file('business_permit')->store('seller_permits', 'public');
        }

        return redirect()->route('login')->with('success', 'Seller registration submitted! Please wait for administrator approval sent to your email.');
    }

    public function showCourierRegisterForm()
    {
        return view('auth.register-courier');
    }

    public function courierRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|email|unique:users,email',
            'contact_no' => 'required|numeric',
            'birthday' => 'required|date',
            'age' => 'required|integer|min:18',
            'province' => 'required|string',
            'municipality' => 'required|string',
            'barangay' => 'required|string',
            'street_address' => 'required|string',
            'vehicle_type' => 'required|string|in:Motorcycle,Van,Truck,Bicycle,Multicab',
            'plate_number' => 'required|string|max:20',
            'id_upload' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'or_cr_upload' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($request->hasFile('id_upload')) {
            $request->file('id_upload')->store('courier_licenses', 'public');
        }

        if ($request->hasFile('or_cr_upload')) {
            $request->file('or_cr_upload')->store('courier_or_cr', 'public');
        }

        return redirect()->route('login')->with('success', 'Registration submitted! Please wait for approval from the Logistic/Sorting Center sent to your email.');
    }

    public function logout()
    {
        auth()->logout();
        return redirect()->route('home');
    }
}