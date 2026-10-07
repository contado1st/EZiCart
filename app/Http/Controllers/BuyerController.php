<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class BuyerController extends Controller
{
    public function dashboard(Request $request)
    {
        $statusTab = $request->query('tab', 'all');

        $query = auth()->user()->buyerOrders()
            ->with(['seller', 'items.product'])
            ->latest();

        if ($statusTab === 'to_ship') {
            $query->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING']);
        } elseif ($statusTab === 'to_receive') {
            $query->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY']);
        } elseif ($statusTab === 'delivered') {
            $query->where('status', 'DELIVERED');
        } elseif ($statusTab === 'completed') {
            $query->where('status', 'COMPLETED');
        } elseif ($statusTab === 'cancelled') {
            $query->whereIn('status', ['CANCELLED', 'DELIVERY_FAILED', 'RETURNED']);
        }

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all' => auth()->user()->buyerOrders()->count(),
            'to_ship' => auth()->user()->buyerOrders()->whereIn('status', ['PLACED', 'CONFIRMED', 'PREPARING'])->count(),
            'to_receive' => auth()->user()->buyerOrders()->whereIn('status', ['READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'])->count(),
            'delivered' => auth()->user()->buyerOrders()->where('status', 'DELIVERED')->count(),
            'completed' => auth()->user()->buyerOrders()->where('status', 'COMPLETED')->count(),
            'cancelled' => auth()->user()->buyerOrders()->whereIn('status', ['CANCELLED', 'DELIVERY_FAILED', 'RETURNED'])->count(),
        ];

        return view('buyer.dashboard', compact('orders', 'counts', 'statusTab'));
    }

    public function showOrder(Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);

        $order->load(['seller', 'items.product', 'reviews']);

        return view('buyer.orders.show', compact('order'));
    }

    public function confirmReceived(Order $order)
    {
        abort_if($order->buyer_id !== auth()->id(), 403);
        abort_if($order->status !== 'DELIVERED', 400);

        $order->update(['status' => 'COMPLETED']);

        return back()->with('success', 'Order marked as completed! Thank you for confirming.');
    }

    public function profile()
    {
        $user = auth()->user();

        return view('buyer.profile', compact('user'));
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

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profiles', 'public');
        }

        $user->save();

        return back()->with('success', 'Profile information updated successfully.');
    }

    public function addresses()
    {
        $user = auth()->user();

        // Auto-seed user default address if none exist yet
        if ($user->addresses()->count() === 0 && ! empty($user->province)) {
            $user->addresses()->create([
                'label' => 'Home',
                'recipient_name' => $user->first_name.' '.$user->last_name,
                'phone_number' => $user->contact_no ?? '09000000000',
                'province' => $user->province,
                'municipality' => $user->municipality,
                'barangay' => $user->barangay,
                'street_address' => $user->street_address,
                'is_default' => true,
            ]);
        }

        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();

        return view('buyer.addresses', compact('addresses'));
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

        $address = $user->addresses()->create([
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'phone_number' => $validated['phone_number'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'street_address' => $validated['street_address'],
            'is_default' => $isDefault,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'address' => $address,
            ]);
        }

        return back()->with('success', 'Address added successfully.');
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

        return back()->with('success', 'Address updated successfully.');
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

        return back()->with('success', 'Address removed successfully.');
    }

    public function setDefaultAddress(UserAddress $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);

        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', "Address '{$address->label}' set as your default delivery address.");
    }

    public function password()
    {
        return view('buyer.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The provided current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('success', 'Your password has been successfully changed.');
    }
}
