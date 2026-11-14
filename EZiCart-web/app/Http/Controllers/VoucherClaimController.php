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
        $filters = $request->validate(['view' => ['nullable', 'in:available,saved']]);
        $view = $filters['view'] ?? 'available';
        $claims = DB::table('voucher_claims')->where('buyer_id', $request->user()->id)->get()->keyBy('voucher_id');
        $query = Voucher::with(['seller', 'budget.reserveAccount']);
        if ($view === 'saved') {
            $query->whereIn('id', $claims->keys());
        } else {
            $query->publiclyListed()->where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('seller_id')->orWhereHas('seller', fn ($s) => $s->where('role', 'seller')->where('status', 'approved')));
        }
        $vouchers = $query->latest()->paginate(20)->withQueryString();

        return view('buyer.vouchers', compact('vouchers', 'claims', 'view'));
    }

    public function store(Request $request, Voucher $voucher, VoucherClaimService $claims)
    {
        abort_if($voucher->visibility === 'code_only' && ! DB::table('voucher_claims')->where('voucher_id', $voucher->id)->where('buyer_id', $request->user()->id)->exists(), 404);
        $claims->claim($request->user(), $voucher);

        return back()->with('success', 'Voucher saved to your account. Use its code at checkout before it expires.');
    }
}
