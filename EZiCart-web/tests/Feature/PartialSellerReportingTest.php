<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\RefundExecution;
use App\Models\SellerPayout;
use App\Models\User;
use App\Services\FinancialLedgerService;
use App\Services\WalletFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PartialSellerReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0));
    }

    public function test_packing_list_and_counts_share_buyer_date_and_seller_scope(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer', ['first_name' => 'PackingBuyer']);
        foreach (['CONFIRMED', 'PREPARING', 'PLACED'] as $status) {
            $this->order($seller, ['buyer_id' => $buyer->id, 'status' => $status]);
        }
        $outside = $this->order($seller, ['buyer_id' => $buyer->id, 'status' => 'PREPARING', 'created_at' => '2026-11-13 23:59:59']);
        $otherShop = $this->order($this->user('seller'), ['buyer_id' => $buyer->id, 'status' => 'PREPARING']);
        $filters = ['q' => 'PackingBuyer', 'from_date' => '2026-11-14', 'to_date' => '2026-11-14', 'status' => 'PREPARING'];
        $response = $this->actingAs($seller)->get(route('seller.orders.index', $filters))->assertOk();
        $this->assertSame(2, $response->viewData('orders')->total());
        $this->assertSame(['all' => 3, 'placed' => 1, 'preparing' => 2, 'ready' => 0, 'completed' => 0], $response->viewData('counts'));
        $response->assertDontSee($outside->order_number)->assertDontSee($otherShop->order_number)->assertSee('q=PackingBuyer', false);
    }

    public function test_search_treats_sql_and_wildcard_characters_as_literal_text(): void
    {
        $seller = $this->user('seller');
        $literal = $this->order($seller, ['order_number' => 'PERCENT%_!REFERENCE']);
        $other = $this->order($seller, ['order_number' => 'PERCENTABCXREFERENCE']);
        $this->actingAs($seller)->get(route('seller.orders.index', ['q' => '%_!']))->assertOk()
            ->assertSee($literal->order_number)->assertDontSee($other->order_number)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        $this->get(route('seller.orders.index', ['q' => "' OR 1=1 --"]))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
    }

    #[TestWith(['status', 'UNKNOWN'])]
    #[TestWith(['q', ['invalid-array']])]
    #[TestWith(['from_date', 'invalid-date'])]
    #[TestWith(['to_date', '2026-11-01'])]
    public function test_order_filters_reject_invalid_input(string $field, mixed $value): void
    {
        $this->actingAs($this->user('seller'))->getJson(route('seller.orders.index', ['from_date' => '2026-11-14', $field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_financial_screen_exports_and_analytics_reconcile_without_rewriting_financial_records(): void
    {
        $seller = $this->user('seller');
        $normal = $this->financialOrder($seller);
        $full = $this->financialOrder($seller);
        $this->refund($full, 95000);
        $partial = $this->financialOrder($seller);
        $this->refund($partial, 10000);
        $cancelled = $this->financialOrder($seller, ['status' => 'CANCELLED']);
        $this->refund($cancelled, 95000);
        $this->financialOrder($seller, [], 'VOID');
        $authorized = $this->financialOrder($seller);
        $this->refund($authorized, 95000, 'AUTHORIZED');
        SellerPayout::create(['order_id' => $normal->id, 'seller_id' => $seller->id, 'amount_cents' => 84800, 'status' => 'SETTLED']);
        SellerPayout::create(['order_id' => $authorized->id, 'seller_id' => $seller->id, 'amount_cents' => 84800, 'status' => 'ON_HOLD']);
        $other = $this->financialOrder($this->user('seller'));
        $this->financialOrder($seller, ['created_at' => '2026-10-31 23:59:59']);
        $before = OrderFinance::orderBy('id')->get()->toArray();
        $filters = ['from_date' => '2026-11-01', 'to_date' => '2026-11-14'];
        $response = $this->actingAs($seller)->get(route('seller.reports.index', $filters))->assertOk()
            ->assertSee('Net seller sales')->assertDontSee('Net Merchant Profit')->assertDontSee($other->order_number);
        $stats = $response->viewData('stats');
        foreach (['gross' => 400000, 'seller_discount' => 32000, 'refund' => 200000, 'commission' => 14400, 'net' => 251600, 'completed_count' => 3, 'total_orders' => 6, 'settled_payout' => 84800, 'pending_payout' => 84800] as $key => $expected) {
            $this->assertSame($expected, $stats[$key], $key);
        }
        $csv = $this->get(route('sales-reports.index', [...$filters, 'format' => 'csv']))->assertOk()->streamedContent();
        $csvRows = array_map('str_getcsv', array_slice(explode("\n", trim($csv)), 1));
        $this->assertCount(6, $csvRows);
        $this->assertEquals($stats['net'] / 100, array_sum(array_column($csvRows, 13)));
        $this->assertStringNotContainsString($other->order_number, $csv);
        $this->get(route('sales-reports.index', [...$filters, 'format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $analytics = $this->get(route('analytics.index', ['from' => $filters['from_date'], 'to' => $filters['to_date']]))->assertOk()->viewData('report');
        $this->assertEquals($stats['net'] / 100, collect($analytics['metrics'])->firstWhere('key', 'net')['value']);
        $this->assertEquals(3, collect($analytics['metrics'])->firstWhere('key', 'completed')['value']);
        $this->assertSame($before, OrderFinance::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_report_pagination_keeps_full_totals_and_order_filter_links(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer', ['first_name' => 'PagedBuyer']);
        for ($i = 0; $i < 26; $i++) {
            $this->financialOrder($seller, ['buyer_id' => $buyer->id]);
        }
        $response = $this->actingAs($seller)->get(route('seller.reports.index', ['page' => 2]))->assertOk();
        $this->assertCount(1, $response->viewData('rows'));
        $this->assertSame(26 * 84800, $response->viewData('stats')['net']);
        $this->assertSame(26, $response->viewData('orders')->total());
        $this->get(route('seller.orders.index', ['q' => 'PagedBuyer', 'page' => 2]))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 26 && $orders->count() === 1)
            ->assertSee('q=PagedBuyer', false);
    }

    public function test_seller_report_empty_range_and_reversed_dates_are_explicit(): void
    {
        $seller = $this->user('seller');
        $this->financialOrder($seller);
        $this->actingAs($seller)->get(route('seller.reports.index', ['from_date' => '2026-01-01', 'to_date' => '2026-01-02']))
            ->assertOk()->assertSee('No matching orders')->assertViewHas('stats', fn ($stats) => $stats['total_orders'] === 0 && $stats['net'] === 0);
        $this->getJson(route('seller.reports.index', ['from_date' => '2026-11-14', 'to_date' => '2026-11-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('to_date');
    }

    public function test_seller_pages_require_login_and_refuse_buyer_role(): void
    {
        $this->get(route('seller.reports.index'))->assertRedirectToRoute('login');
        $this->get(route('seller.orders.index'))->assertRedirectToRoute('login');
        $this->actingAs($this->user('buyer'))->get(route('seller.reports.index'))->assertForbidden();
        $this->get(route('seller.orders.index'))->assertForbidden();
    }

    public function test_withdrawal_retry_reserves_once_and_rejects_changed_payload_for_same_key(): void
    {
        config(['financial.sandbox_enabled' => true]);
        $seller = $this->user('seller');
        $funding = app(WalletFundingService::class);
        $intent = $funding->create($seller, 50000, (string) Str::uuid());
        $funding->simulate($intent, $this->user('ecommerce_admin'), 'paid');
        $ledger = app(FinancialLedgerService::class);
        $key = (string) Str::uuid();
        $payload = ['wallet_form' => 'withdrawal', 'amount' => '100.00', 'destination' => 'Synthetic payout destination', 'request_key' => $key];
        $this->actingAs($seller)->from(route('finance.wallet'))->post(route('finance.wallet.withdraw'), [...$payload, 'amount' => 'invalid'])
            ->assertSessionHasErrors('amount');
        $this->get(route('finance.wallet'))->assertOk()->assertSee($key);
        $this->post(route('finance.wallet.withdraw'), $payload)->assertRedirect();
        $this->post(route('finance.wallet.withdraw'), $payload)->assertRedirect();
        $this->postJson(route('finance.wallet.withdraw'), [...$payload, 'amount' => '101.00'])->assertConflict();
        $this->assertDatabaseCount('wallet_withdrawals', 1);
        $this->assertSame(40000, $ledger->wallet($seller)->refresh()->balance_cents);
        $this->assertSame(10000, $ledger->wallet($seller, 'held')->refresh()->balance_cents);
        $this->assertTrue($ledger->reconcile()['balanced']);
    }

    private function user(string $role, array $data = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Report', 'last_name' => 'Tester', 'contact_no' => '09123456789', ...$data]);
    }

    private function order(User $seller, array $data = []): Order
    {
        return Order::forceCreate(['order_number' => 'P5-'.Str::random(12), 'seller_id' => $seller->id, 'buyer_id' => $this->user('buyer')->id,
            'recipient_name' => 'Report Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion',
            'street_address' => 'Synthetic report street', 'subtotal' => 1000, 'discount_amount' => 100, 'platform_discount_amount' => 20, 'commission_fee' => 72,
            'shipping_fee' => 50, 'total_amount' => 950, 'payment_method' => 'COD', 'status' => 'PLACED', ...$data]);
    }

    private function financialOrder(User $seller, array $data = [], string $remittance = 'VERIFIED'): Order
    {
        $order = $this->order($seller, ['status' => 'COMPLETED', 'delivered_at' => now()->subDays(8), ...$data]);
        OrderFinance::create(['order_id' => $order->id, 'due_cents' => 95000, 'commission_cents' => 7200, 'seller_payable_cents' => 84800, 'remittance_status' => $remittance]);

        return $order;
    }

    private function refund(Order $order, int $amount, string $status = 'EXECUTED'): void
    {
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Test return', 'description' => 'Synthetic report fixture', 'status' => 'resolved']);
        RefundExecution::create(['order_id' => $order->id, 'dispute_id' => $dispute->id, 'amount_cents' => $amount, 'status' => $status, 'authorized_by' => $this->user('ecommerce_admin')->id]);
    }
}
