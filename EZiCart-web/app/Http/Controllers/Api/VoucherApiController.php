<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\VoucherClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherApiController extends Controller
{
    /**
     * Get active vouchers with claim status for current buyer
     */
    public function index(Request $request): JsonResponse
    {
        $buyer = $request->user();

        $vouchers = Voucher::with('seller:id,business_name,first_name,last_name')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('seller_id')->orWhereHas('seller', fn ($s) => $s->where('role', 'seller')->where('status', 'approved')))
            ->latest('id')
            ->get();

        $claimedVoucherIds = DB::table('voucher_claims')
            ->where('buyer_id', $buyer->id)
            ->pluck('voucher_id')
            ->flip()
            ->all();

        $data = $vouchers->map(function (Voucher $v) use ($claimedVoucherIds) {
            $isClaimed = isset($claimedVoucherIds[$v->id]);
            $isAvailable = true;

            if ($v->usage_limit !== null && $v->used_count >= $v->usage_limit) {
                $isAvailable = false;
            }

            return [
                'id' => $v->id,
                'code' => $v->code,
                'type' => $v->type,
                'value' => (float) $v->value,
                'min_spend' => (float) ($v->min_spend ?? 0),
                'max_discount' => $v->max_discount !== null ? (float) $v->max_discount : null,
                'expires_at' => $v->expires_at ? $v->expires_at->toIso8601String() : null,
                'is_claimed' => $isClaimed,
                'is_available' => $isAvailable,
                'seller_id' => $v->seller_id,
                'seller_name' => $v->seller_id ? ($v->seller->business_name ?? 'Seller') : 'EZiCart Platform',
                'description' => $v->type === 'percent'
                    ? "{$v->value}% off min. spend ₱".number_format($v->min_spend, 2)
                    : "₱".number_format($v->value, 2)." off min. spend ₱".number_format($v->min_spend, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Claim a voucher
     */
    public function claim(int $id, Request $request, VoucherClaimService $claims): JsonResponse
    {
        $buyer = $request->user();
        $voucher = Voucher::find($id);

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found.',
            ], 404);
        }

        try {
            $claims->claim($buyer, $voucher);

            return response()->json([
                'success' => true,
                'message' => "Voucher {$voucher->code} claimed successfully! You can apply it at checkout.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

