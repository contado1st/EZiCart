<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdministrativeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShopAvailabilityController extends Controller
{
    public function edit(Request $request)
    {
        return view('seller.availability', ['seller' => $request->user()]);
    }

    public function update(Request $request, AdministrativeAuditService $audits)
    {
        if ($request->input('shop_availability') === 'open') {
            $request->merge(['shop_unavailable_from' => null, 'shop_returns_at' => null]);
        }
        $data = $request->validate(['shop_availability' => ['required', 'in:open,closed,vacation,unavailable'],
            'shop_unavailable_from' => ['nullable', 'date'], 'shop_returns_at' => ['nullable', 'date', 'after:now', Rule::when(filled($request->input('shop_unavailable_from')), 'after:shop_unavailable_from')]]);
        if ($data['shop_availability'] === 'open') {
            $data = ['shop_availability' => 'open', 'shop_unavailable_from' => null, 'shop_returns_at' => null];
        }
        DB::transaction(function () use ($request, $data, $audits) {
            $seller = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $before = $seller->only(array_keys($data));
            $seller->forceFill($data)->save();
            $audits->record($seller, $seller, 'shop_availability_updated', $before, $data, 'Seller updated availability for new web orders.');
        });

        return back()->with('success', 'Shop availability saved. Existing orders continue through their current workflow.');
    }
}
