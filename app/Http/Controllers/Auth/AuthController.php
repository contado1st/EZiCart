<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use App\Services\NumverifyPhoneValidator;
use App\Services\SmsOtpSender;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly NumverifyPhoneValidator $phoneValidator,
        private readonly SmsOtpSender $smsOtpSender,
    ) {}

    private const PHILIPPINE_REGIONS = [
        'National Capital Region (NCR)',
        'Cordillera Administrative Region (CAR)',
        'Region I (Ilocos)',
        'Region II (Cagayan Valley)',
        'Region III (Central Luzon)',
        'Region IV-A (CALABARZON)',
        'MIMAROPA',
        'Region V (Bicol)',
        'Region VI (Western Visayas)',
        'Region VII (Central Visayas)',
        'Region VIII (Eastern Visayas)',
        'Region IX (Zamboanga Peninsula)',
        'Region X (Northern Mindanao)',
        'Region XI (Davao)',
        'Region XII (SOCCSKSARGEN)',
        'Region XIII (Caraga)',
        'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
    ];

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($validated['email']));
        Password::sendResetLink(['email' => $email]);

        return back()->with('status', 'If an account matches that email address, a password reset link has been sent.');
    }

    public function showResetPasswordForm(string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => request()->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
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

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $account = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$credentials['email']])->first();
        if (! $account || ! Auth::attempt(['email' => $account->email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        $user = $request->user();
        if ($user->status !== 'approved') {
            if ($user->status === 'rejected' && in_array($user->role, ['seller', 'sorting_center'], true)) {
                $request->session()->regenerate();

                return redirect()->route('application.resubmit')
                    ->with('error', $this->accountStateMessage($user));
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($user->status === 'unverified') {
                return redirect()->route('verification.notice', ['email' => $user->email])
                    ->with('error', $this->accountStateMessage($user));
            }

            return back()->with('error', $this->accountStateMessage($user));
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

    public function logout(Request $request): RedirectResponse
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

    public function register(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $validated = $request->validate($this->identityRules() + [
            'birthday' => ['required', 'date', 'before_or_equal:today', 'before:-18 years'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ]);
        $this->ensureNumverifyAccepts($validated['contact_no']);

        $user = $this->createVerifiedAccount($validated, [
            'birthday' => $validated['birthday'],
            'age' => Carbon::parse($validated['birthday'])->age,
        ], 'buyer');

        return $this->beginVerification($user);
    }

    public function showSellerRegisterForm()
    {
        return view('auth.register-seller', ['regions' => self::PHILIPPINE_REGIONS]);
    }

    public function sellerRegister(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $validated = $request->validate($this->identityRules() + [
            'shop_name' => ['required', 'string', 'min:3', 'max:30', 'unique:users,shop_name'],
            'line_of_business' => ['required', 'string', 'max:100'],
            'seller_type' => ['required', 'in:Individual,Sole Proprietorship,Corporation'],
            'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/', 'unique:users,tin'],
            'region' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'digits:4'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'dti_certificate' => ['required_if:seller_type,Sole Proprietorship', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bir_form_2303' => ['required_if:seller_type,Sole Proprietorship,Corporation', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'sec_certificate' => ['required_if:seller_type,Corporation', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ]);
        $this->ensureNumverifyAccepts($validated['contact_no']);

        $paths = [
            'id_path' => $request->file('id_document')->store('documents/ids', 'private'),
            'permit_path' => $request->file('dti_certificate')?->store('documents/permits', 'private'),
            'bir_certificate_path' => $request->file('bir_form_2303')?->store('documents/bir', 'private'),
            'sec_certificate_path' => $request->file('sec_certificate')?->store('documents/sec', 'private'),
        ];

        $user = $this->createVerifiedAccount($validated, $paths + [
            'business_name' => $validated['shop_name'],
            'shop_name' => $validated['shop_name'],
            'line_of_business' => $validated['line_of_business'],
            'seller_type' => $validated['seller_type'],
            'tin' => $validated['tin'],
            'region' => $validated['region'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'postal_code' => $validated['postal_code'],
        ], 'seller');

        return $this->beginVerification($user);
    }

    public function showSortingCenterRegisterForm()
    {
        return view('auth.register-sorting', ['regions' => self::PHILIPPINE_REGIONS]);
    }

    public function sortingCenterRegister(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $request->merge([
            'canonical_company_name' => Str::lower(trim((string) $request->input('business_name'))),
            'sec_registration_no' => Str::upper(trim((string) $request->input('sec_registration_no'))),
        ]);
        $validated = $request->validate($this->identityRules() + [
            'business_name' => ['required', 'string', 'min:3', 'max:150'],
            'canonical_company_name' => ['required', 'string', 'max:150', Rule::unique('users', 'canonical_company_name')],
            'sec_registration_no' => ['required', 'regex:/^[A-Za-z]{2}\d{9}$/', 'unique:users,sec_registration_no'],
            'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/', 'unique:users,tin'],
            'region' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'digits:4'],
            'contact_position' => ['required', 'string', 'max:100'],
            'service_areas' => ['required', 'array', 'min:1'],
            'service_areas.*' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
            'sec_certificate' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bir_form_2303' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'business_permit' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'representative_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ]);
        $this->ensureNumverifyAccepts($validated['contact_no']);

        $paths = [
            'id_path' => $request->file('representative_id')->store('documents/ids', 'private'),
            'sec_certificate_path' => $request->file('sec_certificate')->store('documents/sec', 'private'),
            'bir_certificate_path' => $request->file('bir_form_2303')->store('documents/bir', 'private'),
            'permit_path' => $request->file('business_permit')->store('documents/permits', 'private'),
            'contact_position' => $validated['contact_position'],
            'service_areas' => array_values($validated['service_areas']),
            'canonical_company_name' => $validated['canonical_company_name'],
            'sec_registration_no' => Str::upper($validated['sec_registration_no']),
            'tin' => $validated['tin'],
            'region' => $validated['region'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'postal_code' => $validated['postal_code'],
        ];

        $user = $this->createVerifiedAccount($validated, $paths, 'sorting_center');

        return $this->beginVerification($user);
    }

    public function showApplicationResubmission(Request $request)
    {
        $applicant = $request->user();
        abort_unless(
            $applicant !== null
                && in_array($applicant->role, ['seller', 'sorting_center'], true)
                && $applicant->status === 'rejected',
            403,
        );

        $view = $applicant->role === 'seller' ? 'auth.register-seller' : 'auth.register-sorting';

        return view($view, [
            'applicant' => $applicant,
            'regions' => self::PHILIPPINE_REGIONS,
            'resubmitting' => true,
        ]);
    }

    public function resubmitApplication(Request $request): RedirectResponse
    {
        $applicant = $request->user();
        abort_unless(
            $applicant !== null
                && in_array($applicant->role, ['seller', 'sorting_center'], true)
                && $applicant->status === 'rejected',
            403,
        );

        $this->normalizeIdentity($request);
        $commonRules = $this->identityRules($applicant);

        if ($applicant->role === 'sorting_center') {
            $request->merge([
                'canonical_company_name' => Str::lower(trim((string) $request->input('business_name'))),
                'sec_registration_no' => Str::upper(trim((string) $request->input('sec_registration_no'))),
            ]);
        }

        if ($applicant->role === 'seller') {
            $validated = $request->validate($commonRules + [
                'shop_name' => ['required', 'string', 'min:3', 'max:30', Rule::unique('users', 'shop_name')->ignore($applicant->id)],
                'line_of_business' => ['required', 'string', 'max:100'],
                'seller_type' => ['required', 'in:Individual,Sole Proprietorship,Corporation'],
                'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/', Rule::unique('users', 'tin')->ignore($applicant->id)],
                'region' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
                'province' => ['required', 'string', 'max:100'],
                'municipality' => ['required', 'string', 'max:100'],
                'barangay' => ['required', 'string', 'max:100'],
                'street_address' => ['required', 'string', 'max:255'],
                'postal_code' => ['required', 'digits:4'],
                'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'dti_certificate' => ['required_if:seller_type,Sole Proprietorship', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'bir_form_2303' => ['required_if:seller_type,Sole Proprietorship,Corporation', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'sec_certificate' => ['required_if:seller_type,Corporation', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'terms' => ['accepted'],
            ]);
            $this->ensureNumverifyAccepts($validated['contact_no']);

            $newPaths = [
                'id_path' => $request->file('id_document')->store('documents/ids', 'private'),
                'permit_path' => $request->file('dti_certificate')?->store('documents/permits', 'private'),
                'bir_certificate_path' => $request->file('bir_form_2303')?->store('documents/bir', 'private'),
                'sec_certificate_path' => $request->file('sec_certificate')?->store('documents/sec', 'private'),
            ];
            $details = [
                'business_name' => $validated['shop_name'],
                'shop_name' => $validated['shop_name'],
                'line_of_business' => $validated['line_of_business'],
                'seller_type' => $validated['seller_type'],
                'tin' => $validated['tin'],
                'region' => $validated['region'],
                'province' => $validated['province'],
                'municipality' => $validated['municipality'],
                'barangay' => $validated['barangay'],
                'street_address' => $validated['street_address'],
                'postal_code' => $validated['postal_code'],
            ];
        } else {
            $validated = $request->validate($commonRules + [
                'business_name' => ['required', 'string', 'min:3', 'max:150'],
                'canonical_company_name' => ['required', 'string', 'max:150', Rule::unique('users', 'canonical_company_name')->ignore($applicant->id)],
                'sec_registration_no' => ['required', 'regex:/^[A-Za-z]{2}\d{9}$/', Rule::unique('users', 'sec_registration_no')->ignore($applicant->id)],
                'tin' => ['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/', Rule::unique('users', 'tin')->ignore($applicant->id)],
                'region' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
                'province' => ['required', 'string', 'max:100'],
                'municipality' => ['required', 'string', 'max:100'],
                'barangay' => ['required', 'string', 'max:100'],
                'street_address' => ['required', 'string', 'max:255'],
                'postal_code' => ['required', 'digits:4'],
                'contact_position' => ['required', 'string', 'max:100'],
                'service_areas' => ['required', 'array', 'min:1'],
                'service_areas.*' => ['required', 'string', Rule::in(self::PHILIPPINE_REGIONS)],
                'sec_certificate' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'bir_form_2303' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'business_permit' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'representative_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'terms' => ['accepted'],
            ]);
            $this->ensureNumverifyAccepts($validated['contact_no']);

            $newPaths = [
                'id_path' => $request->file('representative_id')->store('documents/ids', 'private'),
                'sec_certificate_path' => $request->file('sec_certificate')->store('documents/sec', 'private'),
                'bir_certificate_path' => $request->file('bir_form_2303')->store('documents/bir', 'private'),
                'permit_path' => $request->file('business_permit')->store('documents/permits', 'private'),
            ];
            $details = [
                'business_name' => $validated['business_name'],
                'canonical_company_name' => $validated['canonical_company_name'],
                'sec_registration_no' => Str::upper($validated['sec_registration_no']),
                'tin' => $validated['tin'],
                'contact_position' => $validated['contact_position'],
                'service_areas' => array_values($validated['service_areas']),
                'region' => $validated['region'],
                'province' => $validated['province'],
                'municipality' => $validated['municipality'],
                'barangay' => $validated['barangay'],
                'street_address' => $validated['street_address'],
                'postal_code' => $validated['postal_code'],
            ];
        }

        $oldPaths = array_filter([
            $applicant->id_path,
            $applicant->permit_path,
            $applicant->bir_certificate_path,
            $applicant->sec_certificate_path,
        ]);

        DB::transaction(function () use ($applicant, $validated, $details, $newPaths): void {
            $applicant->forceFill(array_merge([
                'first_name' => trim($validated['first_name']),
                'middle_name' => filled($validated['middle_name'] ?? null) ? trim($validated['middle_name']) : null,
                'last_name' => trim($validated['last_name']),
                'suffix' => filled($validated['suffix'] ?? null) ? trim($validated['suffix']) : null,
                'email' => $validated['email'],
                'canonical_email' => $validated['email'],
                'contact_no' => $validated['contact_no'],
                'canonical_mobile' => $validated['contact_no'],
                'terms_accepted_at' => now(),
                'terms_version' => '2026-10',
                'privacy_accepted_at' => now(),
                'privacy_version' => '2026-10',
                'status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => null,
            ], $details, $newPaths))->save();
        });

        foreach ($oldPaths as $path) {
            if (! in_array($path, $newPaths, true)) {
                Storage::disk('private')->delete($path);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your corrected application was resubmitted and is waiting for admin review.');
    }

    public function showEmailVerification(Request $request)
    {
        return view('auth.verify-email', ['email' => $request->query('email', session('verification_email'))]);
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->first();
        if (! $user || $user->status !== 'unverified') {
            throw ValidationException::withMessages(['code' => 'This verification request is no longer active. Request a new code or sign in.']);
        }

        $verified = DB::transaction(function () use ($user, $validated): bool {
            $record = EmailVerificationCode::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $record || $record->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'This code has expired. Request a new one to continue.']);
            }
            if ($record->attempts >= 6) {
                throw ValidationException::withMessages(['code' => 'Too many attempts. Request a new code to continue.']);
            }
            if (! Hash::check($validated['code'], $record->code_hash)) {
                $record->increment('attempts');

                return false;
            }

            $user->forceFill([
                'status' => $user->role === 'buyer' ? 'approved' : 'pending',
                ...($record->channel === 'phone' ? ['phone_verified_at' => now()] : ['email_verified_at' => now()]),
            ])->save();
            $record->delete();

            return true;
        });

        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'That code does not match. Check your selected delivery method and try again.']);
        }

        return redirect()->route('login')->with('success', $user->role === 'buyer'
            ? 'Your account is verified. You can now sign in.'
            : 'Your account is verified. Your application is now waiting for admin review.');
    }

    public function resendVerificationCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'phone'])],
        ]);
        $email = Str::lower(trim($validated['email']));
        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->where('status', 'unverified')->first();

        if ($user) {
            $record = EmailVerificationCode::query()->firstOrNew(['user_id' => $user->id]);
            if ($validated['channel'] === 'phone' && ! $this->smsOtpSender->isConfigured()) {
                return back()->withErrors(['channel' => 'Phone code delivery is not configured yet. Choose email or try again later.'])->withInput(['email' => $email, 'channel' => 'phone']);
            }
            if ($record->exists && $record->channel === $validated['channel'] && $record->last_sent_at?->gt(now()->subSeconds(60))) {
                return back()->withErrors(['channel' => 'Wait one minute before requesting another code with this method.'])->withInput(['email' => $email, 'channel' => $validated['channel']]);
            }
            try {
                $this->issueVerificationCode($user, $record, $validated['channel']);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['channel' => 'We could not send a code. Check delivery settings or choose another method.'])
                    ->withInput(['email' => $email, 'channel' => $validated['channel']]);
            }
        }

        return back()->with('status', 'If an unverified EZiCart account matches, a new code has been sent using the selected method.');
    }

    private function identityRules(?User $account = null): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/\A[\p{L}\p{M}]+(?:[ ]+[\p{L}\p{M}]+)*\z/u'],
            'middle_name' => ['nullable', 'string', 'min:1', 'max:50', 'regex:/\A[\p{L}\p{M}]+(?:[ ]+[\p{L}\p{M}]+)*\z/u'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/\A[\p{L}\p{M}]+(?:[ ]+[\p{L}\p{M}]+)*\z/u'],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/\A[\p{L}\p{M}\p{N} .-]*\z/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account?->id), Rule::unique('users', 'canonical_email')->ignore($account?->id)],
            'contact_no' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'canonical_mobile')->ignore($account?->id)],
        ];
    }

    private function normalizeIdentity(Request $request): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => trim((string) $request->input($field))]);
            }
        }

        $email = Str::lower(trim((string) $request->input('email')));
        $phoneInput = trim((string) $request->input('contact_no'));
        $phone = $phoneInput;

        if (preg_match('/^\+?[0-9\s]+$/u', $phoneInput)) {
            $digits = preg_replace('/\s+/', '', $phoneInput) ?? '';
            $digits = str_starts_with($digits, '+') ? substr($digits, 1) : $digits;

            if (str_starts_with($digits, '63')) {
                $nationalNumber = substr($digits, 2);
                $nationalNumber = str_starts_with($nationalNumber, '0') ? substr($nationalNumber, 1) : $nationalNumber;

                if (preg_match('/^9\d{9}$/', $nationalNumber)) {
                    $phone = '0'.$nationalNumber;
                }
            } elseif (preg_match('/^09\d{9}$/', $digits)) {
                $phone = $digits;
            } elseif (preg_match('/^9\d{9}$/', $digits)) {
                $phone = '0'.$digits;
            }
        }

        $request->merge(['email' => $email, 'contact_no' => $phone]);
    }

    private function ensureNumverifyAccepts(string $phoneNumber): void
    {
        $result = $this->phoneValidator->validatePhilippineMobile($phoneNumber);

        if ($result === null) {
            throw ValidationException::withMessages([
                'contact_no' => 'Phone number verification is temporarily unavailable. Please try again later.',
            ]);
        }

        if (! $result) {
            throw ValidationException::withMessages([
                'contact_no' => 'Enter a valid Philippine mobile number.',
            ]);
        }
    }

    private function createVerifiedAccount(array $validated, array $details, string $role): User
    {
        try {
            $user = DB::transaction(function () use ($validated, $details, $role): User {
                $duplicateEmail = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->exists();
                if ($duplicateEmail) {
                    throw ValidationException::withMessages(['email' => 'This email address is already linked to an account.']);
                }

                $duplicatePhone = User::query()->where('canonical_mobile', $validated['contact_no'])
                    ->orWhere('contact_no', $validated['contact_no'])
                    ->exists();
                if ($duplicatePhone) {
                    throw ValidationException::withMessages(['contact_no' => 'This mobile number is already linked to an account.']);
                }

                $user = User::query()->forceCreate(array_merge([
                    'first_name' => trim($validated['first_name']),
                    'middle_name' => filled($validated['middle_name'] ?? null) ? trim($validated['middle_name']) : null,
                    'last_name' => trim($validated['last_name']),
                    'suffix' => filled($validated['suffix'] ?? null) ? trim($validated['suffix']) : null,
                    'email' => $validated['email'],
                    'canonical_email' => $validated['email'],
                    'contact_no' => $validated['contact_no'],
                    'canonical_mobile' => $validated['contact_no'],
                    'password' => Hash::make($validated['password']),
                    'role' => $role,
                    'status' => 'unverified',
                    'terms_accepted_at' => now(),
                    'terms_version' => '2026-10',
                    'privacy_accepted_at' => now(),
                    'privacy_version' => '2026-10',
                ], $details));

                return $user;
            });
        } catch (QueryException $exception) {
            if (User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$validated['email']])->exists()) {
                throw ValidationException::withMessages(['email' => 'This email address is already linked to an account.']);
            }

            if (User::query()->where('canonical_mobile', $validated['contact_no'])->orWhere('contact_no', $validated['contact_no'])->exists()) {
                throw ValidationException::withMessages(['contact_no' => 'This mobile number is already linked to an account.']);
            }

            foreach (['shop_name', 'tin', 'sec_registration_no', 'canonical_company_name'] as $field) {
                if (filled($details[$field] ?? null) && User::query()->where($field, $details[$field])->exists()) {
                    throw ValidationException::withMessages([$field => 'This value is already linked to an account.']);
                }
            }

            throw $exception;
        }

        return $user;
    }

    private function beginVerification(User $user): RedirectResponse
    {
        return redirect()->route('verification.notice', ['email' => $user->email])
            ->with('verification_email', $user->email)
            ->with('success', 'Your account has been created. Choose where to receive your six-digit code.');
    }

    private function issueVerificationCode(User $user, EmailVerificationCode $record, string $channel): void
    {
        $code = (string) random_int(100000, 999999);
        $record->fill([
            'channel' => $channel,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
        ])->save();
        if ($channel === 'phone') {
            $this->smsOtpSender->send($user->contact_no, $code);

            return;
        }

        $user->notify(new EmailVerificationCodeNotification($code));
    }

    private function accountStateMessage(User $user): string
    {
        return match ($user->status) {
            'unverified' => 'Verify your account before signing in. Request a new code from the verification page.',
            'pending', 'pending_approval' => 'Your application is under review. EZiCart will notify you after the decision.',
            'pending_activation' => 'Activate your rider account using the latest link sent to your email before signing in.',
            'rejected' => 'Your application was not approved. '.($user->rejection_reason ?: 'Contact support for next steps.'),
            'suspended' => 'Your account is suspended. '.($user->suspension_reason ?: 'Contact support for assistance.'),
            default => 'This account is not eligible to sign in yet. Contact support for assistance.',
        };
    }
}
