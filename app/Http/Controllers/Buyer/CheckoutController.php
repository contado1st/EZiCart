<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use App\Services\OrderAreaService;
use App\Services\PlacePurchaseService;
use App\Services\PsgcAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request, CheckoutPricingService $pricing)
    {
        $cart = $request->session()->get('cart', []);
        if ($cart === []) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }
        $appliedVoucher = $request->session()->get('applied_voucher');
        $voucher = $appliedVoucher ? Voucher::find($appliedVoucher['id']) : null;
        try {
            $quote = $pricing->quote($cart, $voucher);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['voucher_code'])) {
                $request->session()->forget('applied_voucher');

                return redirect()->route('checkout.index')->with('error', $exception->errors()['voucher_code'][0]);
            }

            return redirect()->route('cart.index')->with('error', collect($exception->errors())->flatten()->first());
        }
        if ($appliedVoucher && ! $voucher) {
            $request->session()->forget('applied_voucher');
            $appliedVoucher = null;
        }
        $cart = array_map(fn ($line) => array_diff_key($line, array_flip(['product', 'variation'])), $quote['lines']);
        $request->session()->put('cart', $cart);
        $token = $request->session()->get('checkout_token', (string) Str::uuid());
        $request->session()->put(['checkout_token' => $token, 'checkout_quote_hash' => $quote['hash']]);
        $subtotal = $quote['subtotal_cents'] / 100;
        $discount = $quote['discount_cents'] / 100;
        $shippingFee = $quote['shipping_cents'] / 100;
        $total = $quote['total_cents'] / 100;
        $user = $this->authenticatedUser();

        return view('checkout.index', compact('cart', 'subtotal', 'discount', 'shippingFee', 'total', 'user', 'appliedVoucher', 'quote', 'token'));
    }

    public function applyVoucher(Request $request, CheckoutPricingService $pricing): RedirectResponse
    {
        $validated = $request->validate(['voucher_code' => ['required', 'string', 'max:50']]);
        $voucher = Voucher::query()->where('code', Str::upper(trim($validated['voucher_code'])))->first();
        if (! $voucher) {
            return back()->withErrors(['voucher_code' => 'Voucher code not found.']);
        }
        $pricing->quote($request->session()->get('cart', []), $voucher);
        $request->session()->put('applied_voucher', ['id' => $voucher->id, 'code' => $voucher->code]);

        return redirect()->route('checkout.index')->with('success', 'Voucher applied. Review your updated total.');
    }

    public function removeVoucher(Request $request): RedirectResponse
    {
        $request->session()->forget('applied_voucher');

        return redirect()->route('checkout.index');
    }

    public function process(Request $request, PlacePurchaseService $purchases, OrderAreaService $areas, PsgcAddressService $psgc): RedirectResponse
    {
        $validated = $request->validate([
            'checkout_token' => ['required', 'uuid'], 'quote_hash' => ['required', 'string', 'size:64'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            ...$psgc->validationRules(),
            'province' => ['required_without:province_code', 'nullable', 'string', 'max:120'],
            'municipality' => ['required_without:municipality_code', 'nullable', 'string', 'max:120'],
            'payment_method' => ['required', 'in:COD'], 'notes' => ['nullable', 'string', 'max:200'],
        ]);
        $buyer = $this->authenticatedUser();
        ksort($validated);
        $requestHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR));
        $existing = Purchase::query()->where('buyer_id', $buyer->id)->where('checkout_token', $validated['checkout_token'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->request_hash, $requestHash), 422, 'This checkout token was already used with different details.');

            return redirect()->route('buyer.dashboard')->with('success', 'Your original purchase is already recorded.');
        }
        abort_unless($request->session()->get('checkout_token') === $validated['checkout_token'] && $request->session()->get('checkout_quote_hash') === $validated['quote_hash'], 422, 'Review checkout before placing your order.');
        $location = $psgc->resolve($validated);
        $destination = $areas->findForAddress($location['province'], $location['municipality']);
        $address = [
            ...$location, 'recipient_name' => $validated['recipient_name'], 'recipient_contact' => $validated['recipient_contact'],
            'street_address' => $validated['street_address'] ?? '', 'house_number' => $validated['house_number'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null, 'notes' => $validated['notes'] ?? null,
            'destination_area_id' => $destination?->id,
        ];
        try {
            $purchases->place($buyer, $validated['checkout_token'], $requestHash, $request->session()->get('cart', []), $validated['quote_hash'], $request->session()->get('applied_voucher.id'), $address);
        } catch (ValidationException $exception) {
            return redirect()->route('checkout.index')->withInput()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', "We couldn't place your order right now. Please try again.");
        }
        $request->session()->forget(['cart', 'applied_voucher', 'checkout_token', 'checkout_quote_hash']);

        return redirect()->route('buyer.dashboard')->with('success', 'Purchase placed successfully. Each seller will prepare their shipment.');
    }
}
