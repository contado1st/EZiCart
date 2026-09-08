<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Block access if account is still pending admin approval
            if ($user->status === 'pending') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Your account is pending administrator approval. Please wait for confirmation.',
                ]);
            }

            // Redirect user to their respective dashboard
            return match ($user->role) {
                'admin'   => redirect()->route('admin.registrations.index'),
                'seller'  => redirect()->route('seller.dashboard'),
                'courier' => redirect()->route('courier.dashboard'),
                default   => redirect()->route('buyer.dashboard'),
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

    // --- Registration Forms & Handlers ---

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:1',
            'sex'            => 'required|string',
            'email'          => 'required|string|email|max:255|unique:users',
            'contact_no'     => 'required|string|max:20',
            'birthday'       => 'required|date',
            'age'            => 'required|integer',
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'street_address' => 'required|string',
            'password'       => 'required|string|min:8|confirmed',
            'id_upload'      => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:4096',
        ]);

        $idPath = null;
        if ($request->hasFile('id_upload')) {
            $idPath = $request->file('id_upload')->store('ids', 'public');
        }

        User::create([
            'first_name'     => $validated['first_name'],
            'last_name'      => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex'            => $validated['sex'],
            'email'          => $validated['email'],
            'contact_no'     => $validated['contact_no'],
            'birthday'       => $validated['birthday'],
            'age'            => $validated['age'],
            'province'       => $validated['province'],
            'municipality'   => $validated['municipality'],
            'barangay'       => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'password'       => Hash::make($validated['password']),
            'role'           => 'buyer',
            'status'         => 'pending',
            'id_upload_path' => $idPath,
        ]);

        return redirect()->route('login')->with('success', 'Registration submitted. Please wait for admin approval.');
    }

    public function showSellerRegisterForm()
    {
        return view('auth.register-seller');
    }

    public function sellerRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'middle_initial'   => 'nullable|string|max:1',
            'sex'              => 'required|string',
            'email'            => 'required|string|email|max:255|unique:users',
            'contact_no'       => 'required|string|max:20',
            'birthday'         => 'required|date',
            'age'              => 'required|integer',
            'province'         => 'required|string',
            'municipality'     => 'required|string',
            'barangay'         => 'required|string',
            'street_address'   => 'required|string',
            'business_name'    => 'required|string|max:255',
            'line_of_business' => 'required|string|max:255',
            'password'         => 'required|string|min:8|confirmed',
            'id_upload'        => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'business_permit'  => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:4096',
        ]);

        $idPath = $request->hasFile('id_upload') ? $request->file('id_upload')->store('ids', 'public') : null;
        $permitPath = $request->hasFile('business_permit') ? $request->file('business_permit')->store('permits', 'public') : null;

        User::create([
            'first_name'           => $validated['first_name'],
            'last_name'            => $validated['last_name'],
            'middle_initial'       => $validated['middle_initial'] ?? null,
            'sex'                  => $validated['sex'],
            'email'                => $validated['email'],
            'contact_no'           => $validated['contact_no'],
            'birthday'             => $validated['birthday'],
            'age'                  => $validated['age'],
            'province'             => $validated['province'],
            'municipality'         => $validated['municipality'],
            'barangay'             => $validated['barangay'],
            'street_address'       => $validated['street_address'],
            'business_name'        => $validated['business_name'],
            'line_of_business'     => $validated['line_of_business'],
            'password'             => Hash::make($validated['password']),
            'role'                 => 'seller',
            'status'               => 'pending',
            'id_upload_path'       => $idPath,
            'business_permit_path' => $permitPath,
        ]);

        return redirect()->route('login')->with('success', 'Seller registration submitted. Please wait for admin approval.');
    }

    public function showCourierRegisterForm()
    {
        return view('auth.register-courier');
    }

    public function courierRegister(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:1',
            'sex'            => 'required|string',
            'email'          => 'required|string|email|max:255|unique:users',
            'contact_no'     => 'required|string|max:20',
            'birthday'       => 'required|date',
            'age'            => 'required|integer',
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'street_address' => 'required|string',
            'vehicle_type'   => 'required|string|max:100',
            'plate_number'   => 'required|string|max:50',
            'password'       => 'required|string|min:8|confirmed',
            'id_upload'      => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'or_cr_upload'   => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:4096',
        ]);

        $idPath = $request->hasFile('id_upload') ? $request->file('id_upload')->store('ids', 'public') : null;
        $orCrPath = $request->hasFile('or_cr_upload') ? $request->file('or_cr_upload')->store('or_cr', 'public') : null;

        User::create([
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'],
            'middle_initial'    => $validated['middle_initial'] ?? null,
            'sex'               => $validated['sex'],
            'email'             => $validated['email'],
            'contact_no'        => $validated['contact_no'],
            'birthday'          => $validated['birthday'],
            'age'               => $validated['age'],
            'province'          => $validated['province'],
            'municipality'      => $validated['municipality'],
            'barangay'          => $validated['barangay'],
            'street_address'    => $validated['street_address'],
            'vehicle_type'      => $validated['vehicle_type'],
            'plate_number'      => $validated['plate_number'],
            'password'          => Hash::make($validated['password']),
            'role'              => 'courier',
            'status'            => 'pending',
            'id_upload_path'    => $idPath,
            'or_cr_upload_path' => $orCrPath,
        ]);

        return redirect()->route('login')->with('success', 'Courier registration submitted. Please wait for admin approval.');
    }
}