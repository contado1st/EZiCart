<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\VoucherClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherClaimController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = Voucher::with('seller')->where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('seller_id')->orWhereHas('seller', fn ($s) => $s->where('role', 'seller')->where('status', 'approved')))->latest()->paginate(20);
        $claims = DB::table('voucher_claims')->where('buyer_id', $request->user()->id)->get()->keyBy('voucher_id');

        return view('buyer.vouchers', compact('vouchers', 'claims'));
    }

    public function store(Request $request, Voucher $voucher, VoucherClaimService $claims)
    {
        $claims->claim($request->user(), $voucher);

        return back()->with('success', 'Voucher saved to your account. Use its code at checkout before it expires.');
    }
}
