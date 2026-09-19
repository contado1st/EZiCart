<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if ($user->status === 'pending') {
                Auth::logout();
                return back()->with('error', 'Your registration is still under review. Please wait for approval.');
            }

            if ($user->status === 'rejected') {
                Auth::logout();
                return back()->with('error', 'Your account registration was declined by platform administration.');
            }

            if ($user->status === 'suspended') {
                Auth::logout();
                return back()->with('error', 'Your account has been suspended by platform administration.');
            }

            $request->session()->regenerate();

            return match ($user->role) {
                'seller'         => redirect()->intended(route('seller.dashboard')),
                'admin'          => redirect()->intended(route('admin.dashboard')),
                'courier'        => redirect()->intended(route('courier.dashboard')),
                'logistics'      => redirect()->intended(route('logistics.dashboard')),
                default          => redirect()->intended(route('buyer.dashboard')),
            };
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex'            => 'required|string|max:10',
            'email'          => 'required|string|email|max:255|unique:users',
            'contact_no'     => 'required|string|max:20',
            'birthday'       => 'required|date|before:today',
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'street_address' => 'required|string',
            'id_document'    => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password'       => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/ids', 'public');
        $age = Carbon::parse($validated['birthday'])->age;

        User::create([
            'first_name'     => $validated['first_name'],
            'last_name'      => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex'            => $validated['sex'],
            'email'          => $validated['email'],
            'contact_no'     => $validated['contact_no'],
            'birthday'       => $validated['birthday'],
            'age'            => $age,
            'province'       => $validated['province'],
            'municipality'   => $validated['municipality'],
            'barangay'       => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'id_upload_path' => $idPath,
            'role'           => 'buyer',
            'status'         => 'approved',
            'password'       => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Registration submitted successfully! You can now log in.');
    }

    public function showSellerRegisterForm()
    {
        return view('auth.register-seller');
    }

    public function sellerRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:100',
            'last_name'        => 'required|string|max:100',
            'middle_initial'   => 'nullable|string|max:5',
            'sex'              => 'required|string|max:10',
            'email'            => 'required|string|email|max:255|unique:users',
            'contact_no'       => 'required|string|max:20',
            'birthday'         => 'required|date|before:today',
            'province'         => 'required|string',
            'municipality'     => 'required|string',
            'barangay'         => 'required|string',
            'street_address'   => 'required|string',
            'business_name'    => 'required|string|max:150',
            'line_of_business' => 'required|string|max:255',
            'id_document'      => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'business_permit'  => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/ids', 'public');
        $permitPath = $request->file('business_permit')->store('documents/permits', 'public');
        $age = Carbon::parse($validated['birthday'])->age;

        User::create([
            'first_name'           => $validated['first_name'],
            'last_name'            => $validated['last_name'],
            'middle_initial'       => $validated['middle_initial'] ?? null,
            'sex'                  => $validated['sex'],
            'email'                => $validated['email'],
            'contact_no'           => $validated['contact_no'],
            'birthday'             => $validated['birthday'],
            'age'                  => $age,
            'province'             => $validated['province'],
            'municipality'         => $validated['municipality'],
            'barangay'             => $validated['barangay'],
            'street_address'       => $validated['street_address'],
            'business_name'        => $validated['business_name'],
            'line_of_business'     => $validated['line_of_business'],
            'id_upload_path'       => $idPath,
            'business_permit_path' => $permitPath,
            'role'                 => 'seller',
            'status'               => 'pending',
            'password'             => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Merchant application submitted! Please await Administrator approval via email.');
    }

    public function showCourierRegisterForm()
    {
        return view('auth.register-courier');
    }

    public function courierRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex'            => 'required|string|max:10',
            'email'          => 'required|string|email|max:255|unique:users',
            'contact_no'     => 'required|string|max:20',
            'birthday'       => 'required|date|before:today',
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'street_address' => 'required|string',
            'vehicle_type'   => 'required|string|in:Motorcycle,Van,Truck,Bicycle',
            'plate_number'   => 'required|string|max:20',
            'id_document'    => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'or_cr_document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password'       => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/licenses', 'public');
        $orCrPath = $request->file('or_cr_document')->store('documents/or_cr', 'public');
        $age = Carbon::parse($validated['birthday'])->age;

        User::create([
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'],
            'middle_initial'    => $validated['middle_initial'] ?? null,
            'sex'               => $validated['sex'],
            'email'             => $validated['email'],
            'contact_no'        => $validated['contact_no'],
            'birthday'          => $validated['birthday'],
            'age'               => $age,
            'province'          => $validated['province'],
            'municipality'      => $validated['municipality'],
            'barangay'          => $validated['barangay'],
            'street_address'    => $validated['street_address'],
            'vehicle_type'      => $validated['vehicle_type'],
            'plate_number'      => $validated['plate_number'],
            'id_upload_path'    => $idPath,
            'or_cr_upload_path' => $orCrPath,
            'role'              => 'courier',
            'status'            => 'pending',
            'password'          => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Rider application submitted! Please wait for Logistics / Sorting Center approval.');
    }

    public function showSortingCenterRegisterForm()
    {
        return view('auth.register-sorting');
    }

    public function sortingCenterRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'middle_initial'  => 'nullable|string|max:5',
            'sex'             => 'required|string|max:10',
            'email'           => 'required|string|email|max:255|unique:users',
            'contact_no'      => 'required|string|max:20',
            'birthday'        => 'required|date|before:today',
            'province'        => 'required|string',
            'municipality'    => 'required|string',
            'barangay'        => 'required|string',
            'street_address'  => 'required|string',
            'business_name'   => 'required|string|max:150',
            'id_document'     => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'business_permit' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password'        => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/ids', 'public');
        $permitPath = $request->file('business_permit')->store('documents/permits', 'public');
        $age = Carbon::parse($validated['birthday'])->age;

        User::create([
            'first_name'           => $validated['first_name'],
            'last_name'            => $validated['last_name'],
            'middle_initial'       => $validated['middle_initial'] ?? null,
            'sex'                  => $validated['sex'],
            'email'                => $validated['email'],
            'contact_no'           => $validated['contact_no'],
            'birthday'             => $validated['birthday'],
            'age'                  => $age,
            'province'             => $validated['province'],
            'municipality'         => $validated['municipality'],
            'barangay'             => $validated['barangay'],
            'street_address'       => $validated['street_address'],
            'business_name'        => $validated['business_name'],
            'id_upload_path'       => $idPath,
            'business_permit_path' => $permitPath,
            'role'                 => 'logistics', // Mapped to 'logistics' in original enum
            'status'               => 'pending',
            'password'             => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Sorting Center registration submitted! Awaiting Administrator review.');
    }
}