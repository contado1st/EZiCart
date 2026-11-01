<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnalyticsService
{
    public const ROLES = ['buyer', 'seller', 'courier', 'sorting_center', 'admin', 'ecommerce_admin', 'logistics_admin'];

    public function __construct(private SalesReportService $sales) {}

    public function filters(Request $request): array
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, self::ROLES, true), 403);
        foreach (['seller_id' => $actor->isCommerceAdmin(), 'rider_id' => $actor->isLogisticsAdmin() || $actor->role === 'sorting_center' || $actor->isCommerceAdmin()] as $field => $allowed) {
            if (! $allowed && $request->filled($field)) {
                throw ValidationException::withMessages([$field => 'This filter is unavailable for your role.']);
            }
        }
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'comparison' => ['nullable', Rule::in(['previous', 'year', 'none'])],
            'interval' => ['nullable', Rule::in(['day', 'week', 'month', 'quarter', 'year'])],
            'seller_id' => ['nullable', 'integer', 'min:1'], 'rider_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'], 'category' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'], 'barangay' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::in(['verified', 'collected', 'uncollected', 'refunded'])],
            'payment_method' => ['nullable', 'string', 'max:30'], 'voucher' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['from'] ??= today()->startOfMonth()->toDateString();
        $filters['to'] ??= today()->toDateString();
        $filters['comparison'] ??= 'previous';
        $filters['interval'] ??= 'day';
        $days = CarbonImmutable::parse($filters['from'])->diffInDays(CarbonImmutable::parse($filters['to']), false);
        if ($days < 0 || $days >= (int) config('analytics.maximum_days', 366)) {
            throw ValidationException::withMessages(['to' => 'Choose an end date on or after the start date, within '.config('analytics.maximum_days', 366).' days.']);
        }
        unset($filters['format'], $filters['page']);

        return $filters;
    }

    public function scope(User $actor): Builder
    {
        abort_unless(in_array($actor->role, self::ROLES, true), 403);
        $query = Order::query();

        return match ($actor->role) {
            'buyer' => $query->where('buyer_id', $actor->id),
            'seller' => $query->where('seller_id', $actor->id),
            'courier' => $query->where(fn ($q) => $q->where('pickup_courier_id', $actor->id)->orWhere('delivery_courier_id', $actor->id)),
            'sorting_center' => $query->where(fn ($q) => $q->where('sorting_center_id', $actor->id)->orWhereHas('trackingEvents', fn ($events) => $events->where('actor_id', $actor->id))),
            default => $query,
        };
    }

    private function orders(User $actor, array $filters, string $from, string $to): Collection
    {
        $query = $this->scope($actor)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when(filled($filters['seller_id'] ?? null), fn ($q) => $q->where('seller_id', $filters['seller_id']))
            ->when(filled($filters['rider_id'] ?? null), fn ($q) => $q->where(fn ($r) => $r->where('pickup_courier_id', $filters['rider_id'])->orWhere('delivery_courier_id', $filters['rider_id'])))
            ->when(filled($filters['product_id'] ?? null), fn ($q) => $q->whereHas('items', fn ($items) => $items->where('product_id', $filters['product_id'])))
            ->when(filled($filters['category'] ?? null), fn ($q) => $q->whereHas('items.product', fn ($p) => $p->where('category', $filters['category'])))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['municipality'] ?? null), fn ($q) => $q->where('municipality', $filters['municipality']))
            ->when(filled($filters['barangay'] ?? null), fn ($q) => $q->where('barangay', $filters['barangay']))
            ->when(filled($filters['payment_method'] ?? null), fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->when(filled($filters['voucher'] ?? null), fn ($q) => $q->where('voucher_code', $filters['voucher']));
        match ($filters['payment_status'] ?? null) {
            'verified' => $query->whereHas('finance', fn ($f) => $f->where('remittance_status', 'VERIFIED')),
            'collected' => $query->whereHas('finance', fn ($f) => $f->whereNotNull('collected_at')),
            'uncollected' => $query->where(fn ($q) => $q->whereDoesntHave('finance')->orWhereHas('finance', fn ($f) => $f->whereNull('collected_at'))),
            'refunded' => $query->whereHas('refundExecutions', fn ($r) => $r->where('status', 'EXECUTED')),
            default => null,
        };
        if ((clone $query)->count() > (int) config('analytics.maximum_orders', 10000)) {
            throw ValidationException::withMessages(['from' => 'This report exceeds '.number_format(config('analytics.maximum_orders', 10000)).' orders. Narrow the date range or add filters; no records have been omitted.']);
        }

        return $query->with(['seller:id,business_name,first_name,last_name', 'items.product:id,name,category', 'finance', 'payout', 'refundExecutions', 'pendingCancellation', 'dispute', 'deliveryAttempts', 'deliveryAssignments'])
            ->withSum(['refundExecutions as executed_refund_cents' => fn ($q) => $q->where('status', 'EXECUTED')], 'amount_cents')->orderBy('created_at')->orderBy('id')->get();
    }

    public function report(User $actor, array $filters): array
    {
        $orders = $this->orders($actor, $filters, $filters['from'], $filters['to']);
        $start = CarbonImmutable::parse($filters['from']);
        $end = CarbonImmutable::parse($filters['to']);
        $days = (int) $start->diffInDays($end) + 1;
        $comparison = match ($filters['comparison']) {
            'previous' => ['from' => $start->subDays($days)->toDateString(), 'to' => $start->subDay()->toDateString()],
            'year' => ['from' => $start->subYearNoOverflow()->toDateString(), 'to' => $end->subYearNoOverflow()->toDateString()],
            default => null,
        };
        $previous = $comparison ? $this->orders($actor, $filters, $comparison['from'], $comparison['to']) : collect();
        $current = $this->summarize($orders, $actor);
        $past = $this->summarize($previous, $actor);
        $commercial = $actor->isCommerceAdmin() || $actor->role === 'seller';
        $buyer = $actor->role === 'buyer';
        $title = match ($actor->role) {
            'buyer' => 'My shopping analytics', 'seller' => 'Store analytics', 'courier' => 'Rider performance',
            'sorting_center' => 'Sorting center analytics', 'logistics_admin' => 'Logistics network analytics', default => 'Marketplace analytics',
        };
        $metricKeys = $commercial
            ? ['orders', 'gross', 'net', 'commission', 'completed', 'units', 'aov', 'refund', 'seller_discount', 'platform_discount', 'shipping', 'pending_payout', 'settled_payout']
            : ($buyer ? ['orders', 'collected', 'refund', 'spend', 'completed', 'cancelled'] : ['orders', 'delivered', 'returned', 'failed_attempts', 'delivery_hours', 'shipping']);
        $definitions = $this->definitions();
        $metrics = [];
        foreach ($metricKeys as $key) {
            $metrics[] = ['key' => $key, ...$definitions[$key], 'value' => $current[$key], 'previous' => $comparison ? $past[$key] : null,
                'change' => $comparison && $past[$key] > 0 && $current[$key] !== null ? round(($current[$key] - $past[$key]) / $past[$key] * 100, 1) : null];
        }
        $trend = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $period = $this->period($day, $filters['interval']);
            $trend[$period] ??= ['period' => $period, 'orders' => 0, 'completed' => 0, 'net' => 0, 'delivered' => 0];
        }
        $records = [];
        $productRows = [];
        $categoryRows = [];
        $sellerRows = [];
        $voucherRows = [];
        $refundRows = [];
        $payoutRows = [];
        $customerOrders = [];
        foreach ($orders as $order) {
            $row = $this->sales->row($order);
            $period = $this->period(CarbonImmutable::instance($order->created_at), $filters['interval']);
            $trend[$period]['orders']++;
            $trend[$period]['completed'] += (int) ($order->status === OrderStatus::Completed->value);
            $trend[$period]['delivered'] += (int) ($order->delivered_at !== null);
            $trend[$period]['net'] += $commercial ? $row['net'] / 100 : 0;
            $record = ['Order' => $order->order_number, 'Placed' => $order->created_at->format('Y-m-d'), 'Status' => $order->status, 'Municipality' => $order->municipality];
            if ($commercial) {
                $record += ['Gross PHP' => $row['gross'] / 100, 'Seller discount PHP' => $row['seller_discount'] / 100, 'Platform discount PHP' => $row['platform_discount'] / 100,
                    'Shipping PHP' => $row['shipping'] / 100, 'Refund PHP' => $row['refund'] / 100, 'Commission PHP' => $row['commission'] / 100, 'Net seller sales PHP' => $row['net'] / 100];
            } elseif ($buyer) {
                $record += ['Recorded COD PHP' => ($order->finance?->collected_cents ?? 0) / 100, 'Refund PHP' => $row['refund'] / 100];
            }
            $records[] = $record;
            if (! $commercial) {
                continue;
            }
            $eligible = $order->status === 'COMPLETED' || ($row['refund'] > 0 && $order->delivered_at !== null);
            if ($eligible) {
                $customerOrders[$order->buyer_id] = ($customerOrders[$order->buyer_id] ?? 0) + 1;
                foreach ($order->items as $item) {
                    $id = $item->product_id ?? 'deleted-'.$item->product_name;
                    $productRows[$id] ??= ['Product' => $item->product_name, 'Units fulfilled' => 0, 'Gross PHP' => 0];
                    $productRows[$id]['Units fulfilled'] += $item->quantity;
                    $productRows[$id]['Gross PHP'] += CheckoutPricingService::cents($item->item_total) / 100;
                    $category = $item->product?->category ?: 'Uncategorized / deleted';
                    $categoryRows[$category] ??= ['Category' => $category, 'Units fulfilled' => 0, 'Gross PHP' => 0];
                    $categoryRows[$category]['Units fulfilled'] += $item->quantity;
                    $categoryRows[$category]['Gross PHP'] += CheckoutPricingService::cents($item->item_total) / 100;
                }
            }
            $sellerRows[$order->seller_id] ??= ['Seller' => $order->seller?->business_name ?: 'Seller #'.$order->seller_id, 'Orders' => 0, 'Net seller sales PHP' => 0, 'Commission PHP' => 0];
            $sellerRows[$order->seller_id]['Orders']++;
            $sellerRows[$order->seller_id]['Net seller sales PHP'] += $row['net'] / 100;
            $sellerRows[$order->seller_id]['Commission PHP'] += $row['commission'] / 100;
            $funding = CheckoutPricingService::cents($order->platform_discount_amount ?? 0) > 0 ? 'platform' : 'seller';
            foreach ([$funding => ['code' => $order->voucher_code, 'discount' => $funding === 'platform' ? $row['platform_discount'] : $row['seller_discount']]] as $funding => $voucher) {
                if (! $eligible || ! $voucher['code']) {
                    continue;
                }
                $id = $funding.':'.$voucher['code'];
                $voucherRows[$id] ??= ['Voucher' => $voucher['code'], 'Funding' => $funding, 'Recognized orders' => 0, 'Discount PHP' => 0];
                $voucherRows[$id]['Recognized orders']++;
                $voucherRows[$id]['Discount PHP'] += $voucher['discount'] / 100;
            }
            foreach ($order->refundExecutions as $refund) {
                $refundRows[] = ['Order' => $order->order_number, 'Refund ID' => $refund->id, 'Status' => $refund->status, 'Amount PHP' => $refund->amount_cents / 100, 'Updated' => (string) $refund->updated_at];
            }
            if ($order->payout) {
                $payoutRows[] = ['Order' => $order->order_number, 'Payout ID' => $order->payout->id, 'Status' => $order->payout->status, 'Amount PHP' => $order->payout->amount_cents / 100, 'Updated' => (string) $order->payout->updated_at];
            }
        }
        $sections = [];
        $add = static function ($key, $label, array $rows) use (&$sections) {
            $sections[] = ['key' => $key, 'title' => $label, 'rows' => array_values($rows)];
        };
        $statuses = $orders->countBy('status')->map(fn ($count, $status) => ['label' => Str::headline(strtolower($status)), 'value' => $count])->values()->all();
        $add('statuses', 'Order status distribution', $orders->countBy('status')->map(fn ($count, $status) => ['Status' => $status, 'Orders' => $count])->all());
        $add('locations', 'Orders by municipality', $orders->countBy(fn ($o) => $o->municipality ?: 'Not recorded')->sortDesc()->map(fn ($count, $place) => ['Municipality' => $place, 'Orders' => $count])->all());
        if ($commercial) {
            usort($productRows, fn ($a, $b) => $b['Gross PHP'] <=> $a['Gross PHP']);
            usort($categoryRows, fn ($a, $b) => $b['Gross PHP'] <=> $a['Gross PHP']);
            usort($sellerRows, fn ($a, $b) => $b['Net seller sales PHP'] <=> $a['Net seller sales PHP']);
            $add('products', 'Products ranked by gross merchandise value', $productRows);
            $add('categories', 'Categories ranked by gross merchandise value', $categoryRows);
            if ($actor->isCommerceAdmin()) {
                $add('sellers', 'Sellers ranked by net seller sales', $sellerRows);
            }
            $add('vouchers', 'Voucher funding and redemptions', $voucherRows);
            $add('refunds', 'Refund records for selected orders', $refundRows);
            $add('payouts', 'Payout records for selected orders', $payoutRows);
            $customers = count($customerOrders);
            $existing = $customers ? $this->scope($actor)->whereIn('buyer_id', array_keys($customerOrders))
                ->when(filled($filters['seller_id'] ?? null), fn ($q) => $q->where('seller_id', $filters['seller_id']))
                ->where('created_at', '<', $filters['from'].' 00:00:00')
                ->where(fn ($q) => $q->where('status', 'COMPLETED')->orWhere(fn ($q) => $q->whereNotNull('delivered_at')->whereHas('refundExecutions', fn ($r) => $r->where('status', 'EXECUTED'))))->distinct()->count('buyer_id') : 0;
            $add('customers', 'Customer purchase behavior (aggregate)', [
                ['Metric' => 'Qualifying purchasing customers', 'Value' => $customers],
                ['Metric' => 'First qualifying purchase in this store/domain', 'Value' => $customers - $existing],
                ['Metric' => 'Purchased before this period', 'Value' => $existing],
                ['Metric' => 'Multiple qualifying orders within this period', 'Value' => count(array_filter($customerOrders, fn ($count) => $count > 1))],
            ]);
            $inventory = Product::query()->where('is_archived', false)
                ->when($actor->role === 'seller', fn ($q) => $q->where('user_id', $actor->id))
                ->when(filled($filters['seller_id'] ?? null), fn ($q) => $q->where('user_id', $filters['seller_id']))
                ->when(filled($filters['product_id'] ?? null), fn ($q) => $q->whereKey($filters['product_id']))
                ->when(filled($filters['category'] ?? null), fn ($q) => $q->where('category', $filters['category']))
                ->where('stock', '<=', config('commerce.low_stock_threshold', 5))->orderBy('stock')->orderBy('id');
            if ((clone $inventory)->count() > config('analytics.maximum_orders', 10000)) {
                throw ValidationException::withMessages(['category' => 'Narrow the product or category filters to export the complete low-stock inventory snapshot.']);
            }
            $inventory = $inventory->get(['id', 'name', 'stock', 'compliance_status']);
            $add('inventory', 'Current low/out-of-stock products (not date-filtered)', $inventory->map(fn ($p) => ['Product ID' => $p->id, 'Product' => $p->name, 'Parent stock' => $p->stock, 'Compliance' => $p->compliance_status])->all());
        }
        $operational = [];
        foreach ($orders as $order) {
            foreach ($order->deliveryAttempts as $attempt) {
                if ($actor->role === 'courier' && $attempt->rider_id !== $actor->id) {
                    continue;
                }
                $operational[] = ['Order' => $order->order_number, 'Attempt' => $attempt->attempt_no, 'Outcome' => $attempt->outcome, 'Attempted' => (string) $attempt->attempted_at];
            }
        }
        if (! $buyer) {
            $add('attempts', 'Delivery attempts for selected orders', $operational);
        }
        if ($commercial || $buyer) {
            $orderNumbers = $orders->pluck('order_number', 'id');
            $requests = OrderCancellationRequest::query()->whereIn('order_id', $orders->modelKeys())->orderBy('id')->get(['id', 'order_id', 'status', 'created_at', 'resolved_at']);
            $add('cancellations', 'Cancellation requests (not completed cancellations)', $requests->map(fn ($r) => ['Order' => $orderNumbers[$r->order_id], 'Request ID' => $r->id, 'Status' => $r->status, 'Requested at' => (string) $r->created_at, 'Resolved at' => (string) $r->resolved_at])->all());
            $add('disputes', 'Dispute outcomes for selected orders', $orders->filter(fn ($o) => $o->dispute !== null)->map(fn ($o) => ['Order' => $o->order_number, 'Dispute ID' => $o->dispute->id, 'Status' => $o->dispute->status, 'Resolved at' => (string) $o->dispute->resolved_at])->all());
            $add('payments', 'Recorded COD and remittance ledger', $orders->filter(fn ($o) => $o->finance !== null)->map(fn ($o) => ['Order' => $o->order_number, 'Remittance status' => $o->finance->remittance_status, 'Recorded COD PHP' => ($o->finance->collected_cents ?? 0) / 100, 'Remitted PHP' => ($o->finance->remitted_cents ?? 0) / 100, 'Verified at' => (string) $o->finance->verified_at])->all());
        }
        if (in_array($actor->role, ['courier', 'sorting_center', 'logistics_admin'], true)) {
            $assignments = [];
            foreach ($orders as $order) {
                foreach ($order->deliveryAssignments as $assignment) {
                    if ($actor->role === 'courier' && $assignment->rider_id !== $actor->id) {
                        continue;
                    }
                    $assignments[] = ['Order' => $order->order_number, 'Assignment ID' => $assignment->id, 'Rider ID' => $assignment->rider_id, 'Status' => $assignment->status, 'Assigned at' => (string) $assignment->assigned_at, 'Accepted at' => (string) $assignment->accepted_at];
                }
            }
            $add('assignments', 'Delivery assignment acceptance', $assignments);
        }
        $add('orders', 'Source orders (current state)', $records);
        $availability = [
            ['Metric' => 'Visitor acquisition, device and conversion funnels', 'Reason' => 'Visitor/session event tracking is not installed. No rates are inferred from orders.'],
            ['Metric' => 'Advertising ROI, CAC, CPA and ROAS', 'Reason' => 'Campaign spend and attribution records are not available.'],
            ['Metric' => 'Profit, COGS and inventory turnover', 'Reason' => 'Product costs and historical inventory valuations are not recorded.'],
            ['Metric' => 'On-time delivery and website uptime', 'Reason' => 'Delivery SLA targets and performance instrumentation are not available.'],
            ['Metric' => 'Automated alert delivery and cohort forecasting', 'Reason' => 'This release provides on-page observations only, without background monitoring or forecasts.'],
        ];
        $insights = [];
        if ($current['orders'] >= config('analytics.minimum_insight_orders', 5) && config('analytics.cancellation_warning_percent', 20) <= $current['cancelled'] / $current['orders'] * 100) {
            $insights[] = ['Metric' => 'Cancellation share', 'Observation' => round($current['cancelled'] / $current['orders'] * 100, 1).'% of selected orders are cancelled. Review the source orders; cancellation requests are not counted as cancellations.'];
        }
        $missingFinance = $orders->whereNull('finance')->count();
        if (($commercial || $buyer) && $missingFinance) {
            $insights[] = ['Metric' => 'Financial data coverage', 'Observation' => $missingFinance.' selected orders have no finance ledger. Recorded COD and recognized commission may be incomplete.'];
        }
        $add('availability', 'Unavailable measurements', $availability);

        return ['title' => $title, 'role' => $actor->role, 'commercial' => $commercial, 'filters' => $filters, 'comparison' => $comparison,
            'generated_at' => now()->toIso8601String(), 'timezone' => config('app.timezone'), 'currency' => 'PHP',
            'metrics' => $metrics, 'trend' => array_values($trend), 'statuses' => $statuses, 'sections' => $sections, 'insights' => $insights,
            'notes' => [
                'Date basis: orders placed during the selected dates, using application timezone '.config('app.timezone').'. Current lifecycle state is shown; comparisons are not historical state snapshots.',
                'All charts and exports use this same authorized order cohort. Product/category filters select whole orders, including their other items. Empty periods contain genuine zero counts.',
                'Gross merchandise value is recognized on completed orders and delivered orders with executed refunds. Seller net sales = gross minus seller-funded discounts, merchandise refunds, applicable platform subsidy reversal and recognized commission. This is not profit.',
                'Commission is recognized only for completed, unrefunded orders with verified remittance. Shipping is separate from merchandise sales. Seller-funded and platform-funded discounts remain separate.',
                'Refunds and payouts include all currently recorded adjustments attached to the selected orders, even when processed outside the order-placement period. Adjustment rows show their update timestamps. Pending cancellation requests are not cancellations.',
                'Units fulfilled and product/category gross values precede returned-unit adjustments and order discounts. Categories use current product records; historical category snapshots are unavailable.',
                'Delivery duration uses only valid pickup-to-delivery timestamp pairs. Orders without valid pairs are excluded, not treated as zero-duration or on-time deliveries. Rider scope covers current pickup/delivery assignments.',
                'Current parent-product low stock is a separate inventory snapshot, not historical stock movement; variation-level availability is not inferred from parent stock.',
            ]];
    }

    private function summarize(Collection $orders, User $actor): array
    {
        $totals = array_fill_keys(['orders', 'gross', 'net', 'commission', 'completed', 'units', 'aov', 'refund', 'seller_discount', 'platform_discount', 'pending_payout', 'settled_payout', 'cancelled', 'delivered', 'returned', 'failed_attempts', 'shipping', 'collected', 'spend'], 0);
        $durations = [];
        $qualifying = 0;
        foreach ($orders as $order) {
            $row = $this->sales->row($order);
            $totals['orders']++;
            foreach (['gross', 'net', 'commission', 'refund', 'seller_discount', 'platform_discount', 'shipping'] as $key) {
                $totals[$key] += $row[$key] / 100;
            }
            $totals['completed'] += (int) ($order->status === 'COMPLETED');
            $totals['cancelled'] += (int) ($order->status === 'CANCELLED');
            $totals['delivered'] += (int) ($order->delivered_at !== null);
            $totals['returned'] += (int) ($order->status === 'RETURNED_TO_SELLER');
            $totals['failed_attempts'] += $order->deliveryAttempts->when($actor->role === 'courier', fn ($attempts) => $attempts->where('rider_id', $actor->id))->where('outcome', 'failed')->count();
            $totals['collected'] += ($order->finance?->collected_cents ?? 0) / 100;
            if ($order->status === 'COMPLETED' || ($row['refund'] > 0 && $order->delivered_at !== null)) {
                $totals['units'] += $order->items->sum('quantity');
                $qualifying++;
            }
            if ($order->payout) {
                $totals[$order->payout->status === 'SETTLED' ? 'settled_payout' : 'pending_payout'] += in_array($order->payout->status, ['REQUESTED', 'APPROVED', 'ON_HOLD', 'SETTLED'], true) ? $order->payout->amount_cents / 100 : 0;
            }
            if ($order->picked_up_at && $order->delivered_at && $order->delivered_at >= $order->picked_up_at) {
                $durations[] = $order->picked_up_at->diffInSeconds($order->delivered_at) / 3600;
            }
        }
        $totals['aov'] = $qualifying ? round($totals['gross'] / $qualifying, 2) : null;
        $totals['delivery_hours'] = count($durations) ? round(array_sum($durations) / count($durations), 2) : null;
        $totals['spend'] = max(0, round($totals['collected'] - $totals['refund'], 2));

        return $totals;
    }

    private function period(CarbonImmutable $date, string $interval): string
    {
        return match ($interval) {
            'week' => $date->startOfWeek()->format('Y-m-d'), 'month' => $date->format('Y-m'),
            'quarter' => $date->year.'-Q'.$date->quarter, 'year' => $date->format('Y'), default => $date->format('Y-m-d'),
        };
    }

    private function definitions(): array
    {
        $definitions = [];
        foreach (['orders' => 'Orders placed', 'completed' => 'Completed orders', 'units' => 'Units fulfilled (before returns)', 'cancelled' => 'Cancelled orders', 'delivered' => 'Orders with recorded delivery', 'returned' => 'Returned to seller', 'failed_attempts' => 'Failed delivery attempts'] as $key => $label) {
            $definitions[$key] = ['label' => $label, 'unit' => 'count'];
        }
        foreach (['gross' => 'Gross merchandise value', 'net' => 'Net seller sales', 'commission' => 'Recognized commission', 'aov' => 'Average recognized order value', 'refund' => 'Executed refunds', 'seller_discount' => 'Seller-funded discounts', 'platform_discount' => 'Platform-funded discounts', 'pending_payout' => 'Pending recorded payouts', 'settled_payout' => 'Settled recorded payouts', 'shipping' => 'Shipping charges (all orders)', 'collected' => 'Recorded COD paid', 'spend' => 'Net recorded spend'] as $key => $label) {
            $definitions[$key] = ['label' => $label, 'unit' => 'money'];
        }
        $definitions['delivery_hours'] = ['label' => 'Average pickup-to-delivery', 'unit' => 'hours'];

        return $definitions;
    }
}
