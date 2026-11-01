<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesReportService
{
    public function filters(Request $request): array
    {
        $data = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'], 'to_date' => ['nullable', 'date_format:Y-m-d'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)], 'municipality' => ['nullable', 'string', 'max:120'],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
        ]);
        $data['from_date'] = $data['from_date'] ?? today()->startOfMonth()->toDateString();
        $data['to_date'] = $data['to_date'] ?? today()->toDateString();
        if ($data['from_date'] > $data['to_date']) {
            throw ValidationException::withMessages(['to_date' => 'The end date must not precede the start date.']);
        }

        return $data;
    }

    public function query(User $actor, array $filters)
    {
        abort_unless($actor->isCommerceAdmin() || $actor->role === 'seller', 403);

        return Order::query()->with(['seller', 'finance'])->withSum(['refundExecutions as executed_refund_cents' => fn ($q) => $q->where('status', 'EXECUTED')], 'amount_cents')
            ->when($actor->role === 'seller', fn ($q) => $q->where('seller_id', $actor->id))
            ->whereBetween('created_at', [$filters['from_date'].' 00:00:00', $filters['to_date'].' 23:59:59'])
            ->when(filled($filters['seller_id'] ?? null), fn ($q) => $q->where('seller_id', $filters['seller_id']))
            ->when(filled($filters['product_id'] ?? null), fn ($q) => $q->whereHas('items', fn ($items) => $items->where('product_id', $filters['product_id'])))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['municipality'] ?? null), fn ($q) => $q->where('municipality', $filters['municipality']));
    }

    public function row(Order $order): array
    {
        $refund = (int) ($order->executed_refund_cents ?? 0);
        $eligible = $order->status === 'COMPLETED' || ($refund > 0 && $order->delivered_at !== null);
        $gross = $eligible ? CheckoutPricingService::cents($order->subtotal) : 0;
        $discount = $eligible ? CheckoutPricingService::cents($order->discount_amount ?? 0) : 0;
        $platform = $eligible ? CheckoutPricingService::cents($order->platform_discount_amount ?? 0) : 0;
        $sellerDiscount = max(0, $discount - $platform);
        $merchandiseRefund = min(max(0, $gross - $discount), $refund);
        $platformReversal = $refund > 0 && $merchandiseRefund >= max(0, $gross - $discount) ? $platform : 0;
        $commission = $order->status === 'COMPLETED' && $refund === 0 && $order->finance?->remittance_status === 'VERIFIED' ? (int) $order->finance->commission_cents : 0;

        return [
            'order' => $order->order_number, 'placed' => $order->created_at->format('Y-m-d'),
            'seller' => $order->seller?->business_name ?? 'Seller', 'municipality' => $order->municipality, 'status' => $order->status,
            'gross' => $gross, 'seller_discount' => $sellerDiscount, 'platform_discount' => $platform,
            'shipping' => CheckoutPricingService::cents($order->shipping_fee ?? 0), 'refund' => $refund,
            'merchandise_refund' => $merchandiseRefund, 'platform_reversal' => $platformReversal, 'commission' => $commission,
            'net' => max(0, $gross - $sellerDiscount - $merchandiseRefund - $platformReversal - $commission),
        ];
    }
}
