<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\BuyerAddressService;
use App\Services\PsgcAddressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuyerAddressController extends Controller
{
    public function __construct(private BuyerAddressService $addresses, private PsgcAddressService $psgc) {}

    public function index(): View
    {
        $buyer = $this->authenticatedUser();
        $this->addresses->importProfileAddress($buyer);
        $addresses = $buyer->addresses()->orderByDesc('is_default')->latest('id')->paginate(12);

        return view('buyer.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        return view('buyer.addresses.form', ['address' => null]);
    }

    public function edit(int $address): View
    {
        $address = $this->authenticatedUser()->addresses()->findOrFail($address);

        return view('buyer.addresses.form', compact('address'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedAddress($request);
        $buyer = $this->authenticatedUser();
        $this->addresses->importProfileAddress($buyer);
        $this->addresses->save($buyer, $data);

        return redirect()->route('buyer.addresses.index')->with('success', 'Your delivery address has been saved.');
    }

    public function update(Request $request, int $address): RedirectResponse
    {
        $this->authenticatedUser()->addresses()->findOrFail($address);
        $this->addresses->save($this->authenticatedUser(), $this->validatedAddress($request), $address);

        return redirect()->route('buyer.addresses.index')->with('success', 'Your delivery address has been updated.');
    }

    public function destroy(int $address): RedirectResponse
    {
        $this->addresses->delete($this->authenticatedUser(), $address);

        return redirect()->route('buyer.addresses.index')->with('success', 'The address was removed. Existing orders keep their original delivery details.');
    }

    public function makeDefault(int $address): RedirectResponse
    {
        $this->addresses->makeDefault($this->authenticatedUser(), $address);

        return redirect()->route('buyer.addresses.index')->with('success', 'Your default delivery address has been updated.');
    }

    private function validatedAddress(Request $request): array
    {
        $data = $request->validate([
            ...$this->psgc->validationRules(),
            'address_mode' => ['required', Rule::in(['psgc', 'manual'])],
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:255'],
            'recipient_contact' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        return [...$data, ...$this->psgc->resolve($data)];
    }
}
