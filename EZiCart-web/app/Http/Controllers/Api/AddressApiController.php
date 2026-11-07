<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BuyerAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressApiController extends Controller
{
    /**
     * Get list of saved delivery addresses
     */
    public function index(Request $request, BuyerAddressService $addresses): JsonResponse
    {
        $buyer = $request->user();
        $addresses->importProfileAddress($buyer);

        $list = $buyer->addresses()->orderByDesc('is_default')->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    /**
     * Create a new address
     */
    public function store(Request $request, BuyerAddressService $addresses): JsonResponse
    {
        $buyer = $request->user();
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:255'],
            'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'region' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'barangay' => ['required', 'string', 'max:120'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'digits:4'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $validated['address_mode'] = 'manual';
        $address = $addresses->save($buyer, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Delivery address saved successfully.',
            'data' => $address,
        ], 201);
    }

    /**
     * Update an address
     */
    public function update(Request $request, int $id, BuyerAddressService $addresses): JsonResponse
    {
        $buyer = $request->user();
        $existing = $buyer->addresses()->find($id);

        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Address not found.'], 404);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:255'],
            'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'region' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'barangay' => ['required', 'string', 'max:120'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'digits:4'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $validated['address_mode'] = 'manual';
        $address = $addresses->save($buyer, $validated, $id);

        return response()->json([
            'success' => true,
            'message' => 'Delivery address updated successfully.',
            'data' => $address,
        ]);
    }

    /**
     * Delete an address
     */
    public function destroy(int $id, Request $request, BuyerAddressService $addresses): JsonResponse
    {
        $buyer = $request->user();
        $existing = $buyer->addresses()->find($id);

        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Address not found.'], 404);
        }

        $addresses->delete($buyer, $id);

        return response()->json([
            'success' => true,
            'message' => 'Address removed from your address book.',
        ]);
    }

    /**
     * Set address as default
     */
    public function makeDefault(int $id, Request $request, BuyerAddressService $addresses): JsonResponse
    {
        $buyer = $request->user();
        $existing = $buyer->addresses()->find($id);

        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Address not found.'], 404);
        }

        $addresses->makeDefault($buyer, $id);

        return response()->json([
            'success' => true,
            'message' => 'Default delivery address updated.',
        ]);
    }
}

