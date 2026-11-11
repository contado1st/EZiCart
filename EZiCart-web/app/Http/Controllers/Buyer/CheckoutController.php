<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Voucher;
use App\Services\BuyerAddressService;
use App\Services\BuyerCartService;
use App\Services\CheckoutPricingService;
use App\Services\OrderAreaService;
use App\Services\PsgcAddressService;
use App\Services\VoucherClaimService;
use App\Services\WebCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request, CheckoutPricingService $pricing, BuyerAddressService $addressBook)
    {
        $cart = $this->checkoutItems($request);
        if ($cart === []) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }
        $user = $this->authenticatedUser();
        $addressBook->importProfileAddress($user);
        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $oldInput = $request->old();
        $choice = array_key_exists('saved_address_id', $oldInput)
            ? ($oldInput['saved_address_id'] ?: 'new')
            : $request->query('address_id');
        $selectedAddress = $choice === 'new' ? null : $addresses->firstWhere('is_default', true);
        if ($choice !== null && $choice !== 'new') {
            abort_unless(is_scalar($choice) && ctype_digit((string) $choice), 404);
            $selectedAddress = $user->addresses()->findOrFail((int) $choice);
        }

        $request->validate(['payment_method' => ['nullable', 'in:COD,WALLET']]);
        $paymentMethod = $request->query('payment_method', 'COD') ?: 'COD';
        $appliedVoucher = $request->session()->get('applied_voucher');
        $voucher = $appliedVoucher ? Voucher::find($appliedVoucher['id']) : null;
        try {
            app(WebCheckoutService::class)->assertShopsOpen($cart);
            if ($voucher) {
                app(VoucherClaimService::class)->assertUsable($request->user(), $voucher);
            }
            $quote = $pricing->quote($cart, $voucher, false, ['address' => $selectedAddress?->toArray() ?? $request->session()->get('checkout_shipping_context') ?? $user->only(['province', 'municipality', 'barangay']), 'payment_method' => $paymentMethod]);
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
        if ($voucher) {
            $appliedVoucher = ['id' => $voucher->id, 'code' => $voucher->code];
            $request->session()->put('applied_voucher', $appliedVoucher);
        }
        $cart = array_map(fn ($line) => array_diff_key($line, array_flip(['product', 'variation'])), $quote['lines']);
        $checkoutMode = $request->session()->get('checkout_context.mode', 'cart');
        $request->session()->put('checkout_context', ['mode' => $checkoutMode, 'items' => array_map(fn ($line) => Arr::only($line, ['product_id', 'variation_id', 'quantity']), $cart)]);
        $token = $request->session()->get('checkout_token', (string) Str::uuid());
        $request->session()->put(['checkout_token' => $token, 'checkout_quote_hash' => $quote['hash']]);
        $subtotal = $quote['subtotal_cents'] / 100;
        $discount = $quote['discount_cents'] / 100;
        $shippingFee = $quote['shipping_cents'] / 100;
        $total = $quote['total_cents'] / 100;

        return view('checkout.index', compact('cart', 'subtotal', 'discount', 'shippingFee', 'total', 'user', 'appliedVoucher', 'quote', 'token', 'addresses', 'selectedAddress', 'checkoutMode', 'paymentMethod'));
    }

    public function applyVoucher(Request $request, CheckoutPricingService $pricing): RedirectResponse
    {
        $validated = $request->validate(['voucher_code' => ['required', 'string', 'max:50']]);
        $voucher = Voucher::query()->where('code', Str::upper(trim($validated['voucher_code'])))->first();
        if (! $voucher) {
            return back()->withErrors(['voucher_code' => 'Voucher code not found.']);
        }
        $items = $this->checkoutItems($request);
        app(WebCheckoutService::class)->assertShopsOpen($items);
        $pricing->quote($items, $voucher);
        app(VoucherClaimService::class)->claim($request->user(), $voucher);
        app(VoucherClaimService::class)->assertUsable($request->user(), $voucher);
        $request->session()->put('applied_voucher', ['id' => $voucher->id, 'code' => $voucher->code]);

        return redirect()->route('checkout.index')->with('success', 'Voucher applied. Review your updated total.');
    }

    public function removeVoucher(Request $request): RedirectResponse
    {
        $request->session()->forget('applied_voucher');

        return redirect()->route('checkout.index');
    }

    public function process(Request $request, WebCheckoutService $purchases, OrderAreaService $areas, PsgcAddressService $psgc): RedirectResponse
    {
        $validated = $request->validate([
            'checkout_token' => ['required', 'uuid'], 'quote_hash' => ['required', 'string', 'size:64'],
            'saved_address_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:COD,WALLET'], 'notes' => ['nullable', 'string', 'max:200'],
        ]);
        if (empty($validated['saved_address_id'])) {
            $validated = [...$validated, ...$request->validate([
                'recipient_name' => ['required', 'string', 'max:255'],
                'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
                ...$psgc->validationRules(),
                'province' => ['required_without:province_code', 'nullable', 'string', 'max:120'],
                'municipality' => ['required_without:municipality_code', 'nullable', 'string', 'max:120'],
            ])];
        }
        $buyer = $this->authenticatedUser();
        ksort($validated);
        $requestHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR));
        $existing = Purchase::query()->where('buyer_id', $buyer->id)->where('checkout_token', $validated['checkout_token'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->request_hash, $requestHash), 422, 'This checkout token was already used with different details.');

            return redirect()->route('buyer.dashboard')->with('success', 'Your original purchase is already recorded.');
        }
        abort_unless($request->session()->get('checkout_token') === $validated['checkout_token'] && $request->session()->get('checkout_quote_hash') === $validated['quote_hash'], 422, 'Review checkout before placing your order.');
        // A saved address is always loaded through the buyer's relationship. Client address fields are ignored.
        if (! empty($validated['saved_address_id'])) {
            $savedAddress = $buyer->addresses()->findOrFail($validated['saved_address_id']);
            $deliveryDetails = $savedAddress->only([...BuyerAddressService::LOCATION_FIELDS, 'recipient_name', 'recipient_contact']);
            $deliveryDetails['street_address'] = (string) $savedAddress->street_address;
            $deliveryDetails['barangay'] = (string) $savedAddress->barangay;
            validator($deliveryDetails, [
                'recipient_name' => ['required', 'string', 'max:255'],
                'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
                'province' => ['required', 'string', 'max:120'],
                'municipality' => ['required', 'string', 'max:120'],
            ])->validate();
            $location = $savedAddress->only(['region', 'province', 'municipality', 'barangay']);
        } else {
            $location = $psgc->resolve($validated);
            $deliveryDetails = [
                ...$location, 'recipient_name' => $validated['recipient_name'], 'recipient_contact' => $validated['recipient_contact'],
                'street_address' => $validated['street_address'] ?? '', 'house_number' => $validated['house_number'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
            ];
        }
        $destination = $areas->findForAddress($location['province'], $location['municipality']);
        $address = [
            ...$deliveryDetails, 'notes' => $validated['notes'] ?? null,
            'destination_area_id' => $destination?->id,
        ];
        try {
            $items = $this->checkoutItems($request);
            $request->session()->put('checkout_shipping_context', $address);
            $place = fn () => $purchases->place($buyer, $validated['checkout_token'], $requestHash, $items, $validated['quote_hash'], $request->session()->get('applied_voucher.id'), $address, $validated['payment_method']);
            if ($request->session()->get('checkout_context.mode') === 'buy_now') {
                $place();
            } else {
                app(BuyerCartService::class)->complete($buyer, $validated['checkout_token'], $items, $place);
            }
        } catch (ValidationException $exception) {
            return redirect()->route('checkout.index')->withInput()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', "We couldn't place your order right now. Please try again.");
        }
        $request->session()->forget(['checkout_shipping_context', 'checkout_context', 'applied_voucher', 'checkout_token', 'checkout_quote_hash']);
        $request->session()->put('cart', app(BuyerCartService::class)->display($buyer));

        return redirect()->route('buyer.dashboard')->with('success', 'Order placed successfully. Your seller will prepare the shipment.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $productId = $request->session()->get('checkout_context.mode') === 'buy_now' ? collect($request->session()->get('checkout_context.items', []))->first()['product_id'] ?? null : null;
        $request->session()->forget(['checkout_shipping_context', 'checkout_context', 'applied_voucher', 'checkout_token', 'checkout_quote_hash']);

        return $productId ? redirect()->route('product.show', $productId)->with('success', 'Buy Now checkout cancelled. Your cart is unchanged.') : redirect()->route('cart.index');
    }

    private function checkoutItems(Request $request): array
    {
        return $request->session()->get('checkout_context.items', app(BuyerCartService::class)->read($request->user()));
    }
}
