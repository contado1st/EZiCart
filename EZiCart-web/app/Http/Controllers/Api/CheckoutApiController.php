<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BuyerAddressService;
use App\Services\OrderAreaService;
use App\Services\PlacePurchaseService;
use App\Services\PsgcAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutApiController extends Controller
{
    /**
     * Process order placement for mobile app
     */
    public function checkout(
        Request $request,
        PlacePurchaseService $purchases,
        OrderAreaService $areas,
        PsgcAddressService $psgc,
        BuyerAddressService $addressBook,
    ): JsonResponse {
        $validated = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'quote_hash' => ['required', 'string', 'size:64'],
            'payment_method' => ['required', 'in:COD'],
            'notes' => ['nullable', 'string', 'max:250'],
            'voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'saved_address_id' => ['nullable', 'integer'],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.product_id' => ['required', 'integer'],
            'cart.*.variation_id' => ['nullable', 'integer'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        /** @var User $buyer */
        $buyer = $request->user();

        // Resolve address details
        if (! empty($validated['saved_address_id'])) {
            $savedAddress = $buyer->addresses()->find($validated['saved_address_id']);
            if (! $savedAddress) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected delivery address not found.',
                ], 422);
            }

            $deliveryDetails = [
                'recipient_name' => $savedAddress->recipient_name,
                'recipient_contact' => $savedAddress->recipient_contact,
                'region' => $savedAddress->region,
                'province' => $savedAddress->province,
                'municipality' => $savedAddress->municipality,
                'barangay' => $savedAddress->barangay,
                'street_address' => $savedAddress->street_address ?? '',
                'house_number' => $savedAddress->house_number,
                'postal_code' => $savedAddress->postal_code,
            ];
            $location = [
                'province' => $savedAddress->province,
                'municipality' => $savedAddress->municipality,
            ];
        } else {
            $addressInput = $request->validate([
                'recipient_name' => ['required', 'string', 'max:255'],
                'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
                'region' => ['required', 'string', 'max:120'],
                'province' => ['required', 'string', 'max:120'],
                'municipality' => ['required', 'string', 'max:120'],
                'barangay' => ['required', 'string', 'max:120'],
                'street_address' => ['nullable', 'string', 'max:255'],
                'house_number' => ['nullable', 'string', 'max:50'],
                'postal_code' => ['nullable', 'digits:4'],
            ]);

            $deliveryDetails = [
                'recipient_name' => $addressInput['recipient_name'],
                'recipient_contact' => $addressInput['recipient_contact'],
                'region' => $addressInput['region'],
                'province' => $addressInput['province'],
                'municipality' => $addressInput['municipality'],
                'barangay' => $addressInput['barangay'],
                'street_address' => $addressInput['street_address'] ?? '',
                'house_number' => $addressInput['house_number'] ?? null,
                'postal_code' => $addressInput['postal_code'] ?? null,
            ];
            $location = [
                'province' => $addressInput['province'],
                'municipality' => $addressInput['municipality'],
            ];
        }

        $destination = $areas->findForAddress($location['province'], $location['municipality']);
        $finalAddress = [
            ...$deliveryDetails,
            'notes' => $validated['notes'] ?? null,
            'destination_area_id' => $destination?->id,
        ];

        // Prepare request hash
        $hashPayload = [
            'checkout_token' => $validated['checkout_token'],
            'quote_hash' => $validated['quote_hash'],
            'saved_address_id' => $validated['saved_address_id'] ?? null,
            'payment_method' => 'COD',
            'notes' => $validated['notes'] ?? null,
        ];
        ksort($hashPayload);
        $requestHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

        try {
            $purchase = $purchases->place(
                $buyer,
                $validated['checkout_token'],
                $requestHash,
                $validated['cart'],
                $validated['quote_hash'],
                $validated['voucher_id'] ?? null,
                $finalAddress
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to place order at this time: '.$e->getMessage(),
            ], 500);
        }

        $orders = $purchase->orders()->with('seller:id,business_name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully! Sellers have been notified to prepare your packages.',
            'purchase_id' => $purchase->id,
            'total_amount' => $purchase->total_cents / 100,
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'parcel_code' => $o->parcel_code,
                'status' => $o->status,
                'seller_name' => $o->seller->business_name ?? 'Merchant',
                'total_amount' => $o->total_cents / 100,
            ]),
        ], 201);
    }
}

