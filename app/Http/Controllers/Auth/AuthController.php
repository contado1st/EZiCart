<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => $validated['email']]);

        return back()->with('status', 'If an account matches that email address, a password reset link has been sent.');
    }

    public function showResetPasswordForm(string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => request()->query('email')]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')->with('success', 'Password reset successfully. You can now sign in.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = $this->authenticatedUser();

            if ($user->status === 'pending') {
                Auth::logout();

                return back()->with('error', 'Your registration is still under review. Please wait for approval.');
            }

            if ($user->status === 'rejected') {
                Auth::logout();

                return back()->with('error', 'Your account registration was declined by platform administration.');
            }

            if ($user->status === 'suspended') {
                $reason = $user->suspension_reason ? " Reason: {$user->suspension_reason}" : '';
                Auth::logout();

                return back()->with('error', "Your account has been suspended by platform administration.{$reason}");
            }

            $request->session()->regenerate();

            return match ($user->role) {
                'seller' => redirect()->intended(route('seller.dashboard')),
                'admin' => redirect()->intended(route('admin.dashboard')),
                'courier' => redirect()->intended(route('courier.dashboard')),
                'sorting_center' => redirect()->intended(route('logistics.dashboard')),
                default => redirect()->intended(route('buyer.dashboard')),
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
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:2',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|string|email|max:255|unique:users',
            'contact_no' => 'required|string|max:20',
            'birthday' => 'required|date|before:today',
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street_address' => 'required|string|max:255',
            'id_document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/ids', 'private');
        $age = Carbon::parse($validated['birthday'])->age;

        User::query()->forceCreate([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $age,
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'id_path' => $idPath,
            'role' => 'buyer',
            'status' => 'pending',
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Registration submitted successfully! Please wait for administrator approval before logging in.');
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
            'middle_initial' => 'nullable|string|max:2',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|string|email|max:255|unique:users',
            'contact_no' => 'required|string|max:20',
            'birthday' => 'required|date|before:today',
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street_address' => 'required|string|max:255',
            'business_name' => 'required|string|max:150',
            'line_of_business' => 'required|string|max:255',
            'id_document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'business_permit' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/ids', 'private');
        $permitPath = $request->file('business_permit')->store('documents/permits', 'private');
        $age = Carbon::parse($validated['birthday'])->age;

        User::query()->forceCreate([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $age,
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'business_name' => $validated['business_name'],
            'line_of_business' => $validated['line_of_business'],
            'id_path' => $idPath,
            'permit_path' => $permitPath,
            'role' => 'seller',
            'status' => 'pending',
            'password' => Hash::make($validated['password']),
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
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:2',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|string|email|max:255|unique:users',
            'contact_no' => 'required|string|max:20',
            'birthday' => 'required|date|before:today',
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street_address' => 'required|string|max:255',
            'vehicle_type' => 'required|string|in:Motorcycle,Van,Truck,Bicycle',
            'plate_number' => 'required|string|max:20',
            'id_document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'or_cr_document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $licensePath = $request->file('id_document')->store('documents/licenses', 'private');
        $orCrPath = $request->file('or_cr_document')->store('documents/or_cr', 'private');
        $age = Carbon::parse($validated['birthday'])->age;

        User::query()->forceCreate([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $age,
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'vehicle_type' => $validated['vehicle_type'],
            'plate_number' => $validated['plate_number'],
            'license_path' => $licensePath,
            'or_cr_path' => $orCrPath,
            'role' => 'courier',
            'status' => 'pending',
            'password' => Hash::make($validated['password']),
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
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:2',
            'sex' => 'required|in:Male,Female,Other',
            'email' => 'required|string|email|max:255|unique:users',
            'contact_no' => 'required|string|max:20',
            'birthday' => 'required|date|before:today',
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street_address' => 'required|string|max:255',
            'business_name' => 'required|string|max:150',
            'id_document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'business_permit' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $idPath = $request->file('id_document')->store('documents/ids', 'private');
        $permitPath = $request->file('business_permit')->store('documents/permits', 'private');
        $age = Carbon::parse($validated['birthday'])->age;

        User::query()->forceCreate([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'birthday' => $validated['birthday'],
            'age' => $age,
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'business_name' => $validated['business_name'],
            'id_path' => $idPath,
            'permit_path' => $permitPath,
            'role' => 'sorting_center',
            'status' => 'pending',
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Sorting Center registration submitted! Awaiting Administrator review.');
    }
}
