<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    // 1. Buyer Registration Handler
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

        $path = null;
        if ($request->hasFile('id_upload')) {
            $path = $request->file('id_upload')->store('buyer_ids', 'public');
        }

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'id_upload_path' => $path,
            'password' => Hash::make($validated['password']),
            'role' => 'buyer',
            'status' => 'pending',
        ]);

        return redirect()->route('login')->with('success', 'Registration submitted! Please wait for administrator approval sent via email.');
    }

    public function showSellerRegisterForm()
    {
        return view('auth.register-seller');
    }

    // 2. Seller Registration Handler
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

        $idPath = null;
        if ($request->hasFile('id_upload')) {
            $idPath = $request->file('id_upload')->store('seller_ids', 'public');
        }

        $permitPath = null;
        if ($request->hasFile('business_permit')) {
            $permitPath = $request->file('business_permit')->store('seller_permits', 'public');
        }

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'business_name' => $validated['business_name'],
            'line_of_business' => $validated['line_of_business'],
            'id_upload_path' => $idPath,
            'business_permit_path' => $permitPath,
            'password' => Hash::make($validated['password']),
            'role' => 'seller',
            'status' => 'pending',
        ]);

        return redirect()->route('login')->with('success', 'Seller registration submitted! Please wait for administrator approval sent to your email.');
    }

    public function showCourierRegisterForm()
    {
        return view('auth.register-courier');
    }

    // 3. Courier Registration Handler
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

        $licensePath = null;
        if ($request->hasFile('id_upload')) {
            $licensePath = $request->file('id_upload')->store('courier_licenses', 'public');
        }

        $orCrPath = null;
        if ($request->hasFile('or_cr_upload')) {
            $orCrPath = $request->file('or_cr_upload')->store('courier_or_cr', 'public');
        }

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'vehicle_type' => $validated['vehicle_type'],
            'plate_number' => $validated['plate_number'],
            'id_upload_path' => $licensePath,
            'or_cr_upload_path' => $orCrPath,
            'password' => Hash::make($validated['password']),
            'role' => 'courier',
            'status' => 'pending',
        ]);

        return redirect()->route('login')->with('success', 'Registration submitted! Please wait for approval from the Logistic/Sorting Center sent to your email.');
    }

    public function logout()
    {
        auth()->logout();
        return redirect()->route('home');
    }
}