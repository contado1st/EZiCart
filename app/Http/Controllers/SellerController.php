<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SellerController extends Controller
{
    public function dashboard()
    {
        $seller = auth()->user();

        // Calculate real Net Sales (Subtotal minus platform commission)
        $netSales = (float) ($seller->sellerOrders()
            ->whereIn('status', ['DELIVERED', 'COMPLETED'])
            ->selectRaw('SUM(subtotal - commission_fee) as total')
            ->value('total') ?? 0.00);

        // Average seller rating across their products
        $allReviews = Review::whereHas('product', fn ($q) => $q->where('user_id', $seller->id));
        $avgRating = round((float) ($allReviews->avg('rating') ?? 5.0), 1);

        $stats = [
            'total_products' => $seller->products()->where('is_archived', false)->count(),
            'pending_products' => $seller->products()->where('status', 'pending')->count(),
            'total_orders' => $seller->sellerOrders()->count(),
            'pending_orders' => $seller->sellerOrders()->where('status', 'PLACED')->count(),
            'total_sales' => $netSales,
            'estimated_profit' => max(0, $netSales),
            'rating' => $avgRating > 0 ? $avgRating : 5.0,
            'review_count' => $allReviews->count(),
        ];

        $recentOrders = $seller->sellerOrders()
            ->with(['buyer', 'items.product'])
            ->latest()
            ->take(5)
            ->get();

        return view('seller.dashboard', compact('stats', 'recentOrders'));
    }

    public function feedback()
    {
        $seller = auth()->user();

        $reviews = Review::whereHas('product', fn ($q) => $q->where('user_id', $seller->id))
            ->with(['product', 'buyer'])
            ->latest()
            ->paginate(15);

        $avgRating = round((float) (Review::whereHas('product', fn ($q) => $q->where('user_id', $seller->id))->avg('rating') ?? 5.0), 1);
        $totalReviews = Review::whereHas('product', fn ($q) => $q->where('user_id', $seller->id))->count();

        return view('seller.feedback', compact('reviews', 'avgRating', 'totalReviews'));
    }

    public function profile()
    {
        $user = auth()->user();

        return view('seller.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'contact_no' => 'required|string|max:20',
            'sex' => 'required|string|in:Male,Female,Other',
            'birthday' => 'required|date|before:today',
            'business_name' => 'required|string|max:150',
            'line_of_business' => 'required|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        $age = Carbon::parse($validated['birthday'])->age;
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->middle_initial = $validated['middle_initial'] ?? null;
        $user->contact_no = $validated['contact_no'];
        $user->sex = $validated['sex'];
        $user->birthday = $validated['birthday'];
        $user->age = $age;
        $user->business_name = $validated['business_name'];
        $user->line_of_business = $validated['line_of_business'];

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profiles', 'public');
        }

        $user->save();

        return back()->with('success', 'Store and merchant profile details updated.');
    }

    public function addresses()
    {
        $user = auth()->user();

        // Auto-seed seller pickup address if none exist
        if ($user->addresses()->count() === 0 && ! empty($user->province)) {
            $user->addresses()->create([
                'label' => 'Warehouse',
                'recipient_name' => $user->business_name ?? ($user->first_name.' '.$user->last_name),
                'phone_number' => $user->contact_no ?? '09000000000',
                'province' => $user->province,
                'municipality' => $user->municipality,
                'barangay' => $user->barangay,
                'street_address' => $user->street_address,
                'is_default' => true,
            ]);
        }

        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();

        return view('seller.addresses', compact('addresses'));
    }

    public function storeAddress(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'phone_number' => 'required|string|max:20',
            'province' => 'required|string',
            'municipality' => 'required|string',
            'barangay' => 'required|string',
            'street_address' => 'required|string',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default') || $user->addresses()->count() === 0;

        if ($isDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create([
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'phone_number' => $validated['phone_number'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'is_default' => $isDefault,
        ]);

        return back()->with('success', 'Pickup/warehouse address added.');
    }

    public function updateAddress(Request $request, UserAddress $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'phone_number' => 'required|string|max:20',
            'province' => 'required|string',
            'municipality' => 'required|string',
            'barangay' => 'required|string',
            'street_address' => 'required|string',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            auth()->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update([
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'phone_number' => $validated['phone_number'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'is_default' => $isDefault || $address->is_default,
        ]);

        return back()->with('success', 'Warehouse address updated.');
    }

    public function destroyAddress(UserAddress $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $first = auth()->user()->addresses()->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return back()->with('success', 'Address removed.');
    }

    public function setDefaultAddress(UserAddress $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);

        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', "Address '{$address->label}' set as default pickup location.");
    }

    public function password()
    {
        return view('seller.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The provided current password does not match.']);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
