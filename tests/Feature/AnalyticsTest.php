<?php

namespace Tests\Feature;

use App\Models\DeliveryAttempt;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\OrderFinance;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundExecution;
use App\Models\User;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(12, 0));
    }

    public function test_each_module_gets_an_authorized_analytics_page(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $hub = $this->user('sorting_center');
        $order = $this->order(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'delivery_courier_id' => $rider->id, 'sorting_center_id' => $hub->id]);
        $other = $this->order(['order_number' => 'OTHER-PRIVATE-ORDER']);
        foreach ([$buyer, $seller, $rider, $hub, $this->user('ecommerce_admin'), $this->user('logistics_admin'), $this->user('admin')] as $actor) {
            $response = $this->signIn($actor)->get(route('analytics.index'))->assertOk()->assertSee($order->order_number)->assertSee('Excel workbook')->assertSee('data-analytics-charts', false);
            if (! $actor->isCommerceAdmin() && ! $actor->isLogisticsAdmin()) {
                $response->assertDontSee($other->order_number);
            }
            if (in_array($actor->role, ['courier', 'sorting_center', 'logistics_admin'], true)) {
                $response->assertViewHas('report', fn ($report) => ! in_array('commission', array_column($report['metrics'], 'key'), true) && ! in_array('sellers', array_column($report['sections'], 'key'), true));
            }
        }
    }

    public function test_filters_comparison_and_empty_buckets_match_exports(): void
    {
        $seller = $this->user('seller');
        $this->order(['seller_id' => $seller->id, 'created_at' => '2026-10-15 10:00:00', 'municipality' => 'Majayjay', 'voucher_code' => 'STORE']);
        $this->order(['seller_id' => $seller->id, 'created_at' => '2026-10-12 10:00:00', 'municipality' => 'Majayjay', 'voucher_code' => 'STORE']);
        $this->order(['seller_id' => $seller->id, 'created_at' => '2026-10-15 10:00:00', 'municipality' => 'Other town']);
        $filters = ['from' => '2026-10-14', 'to' => '2026-10-16', 'municipality' => 'Majayjay', 'voucher' => 'STORE'];
        $response = $this->signIn($seller)->get(route('analytics.index', $filters))->assertOk();
        $report = $response->viewData('report');
        $metric = collect($report['metrics'])->firstWhere('key', 'orders');
        $this->assertEquals(1, $metric['value']);
        $this->assertEquals(1, $metric['previous']);
        $this->assertSame([0, 1, 0], array_column($report['trend'], 'orders'));
        $csv = $this->get(route('analytics.export', [...$filters, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10-14', $csv);
        $this->assertStringNotContainsString('Other town', $csv);
        $this->getJson(route('analytics.index', ['seller_id' => $seller->id]))->assertJsonValidationErrors('seller_id');
        $this->getJson(route('analytics.index', ['from' => '2026-10-20', 'to' => '2026-10-01']))->assertJsonValidationErrors('to');
        $this->getJson(route('analytics.export', ['format' => 'html']))->assertJsonValidationErrors('format');
    }

    public function test_financial_metrics_reconcile_with_existing_sales_accounting(): void
    {
        $seller = $this->user('seller');
        $admin = $this->user('ecommerce_admin');
        $order = $this->order(['seller_id' => $seller->id, 'status' => 'COMPLETED', 'subtotal' => 100, 'discount_amount' => 20, 'platform_discount_amount' => 10, 'delivered_at' => now(), 'voucher_code' => 'PLATFORM']);
        OrderFinance::create(['order_id' => $order->id, 'due_cents' => 13000, 'commission_cents' => 1000, 'seller_payable_cents' => 8000, 'remittance_status' => 'VERIFIED', 'collected_cents' => 13000]);
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $seller->id, 'reason' => 'Damaged', 'description' => 'Damaged item.', 'status' => 'resolved']);
        RefundExecution::create(['order_id' => $order->id, 'dispute_id' => $dispute->id, 'amount_cents' => 1000, 'status' => 'EXECUTED', 'authorized_by' => $admin->id]);
        $this->order(['seller_id' => $seller->id, 'status' => 'CANCELLED', 'subtotal' => 9999]);
        $report = $this->signIn($seller)->get(route('analytics.index'))->assertOk()->viewData('report');
        $source = $order->fresh()->load('finance')->loadSum(['refundExecutions as executed_refund_cents' => fn ($q) => $q->where('status', 'EXECUTED')], 'amount_cents');
        $expected = app(SalesReportService::class)->row($source);
        foreach (['gross', 'net', 'commission', 'refund', 'seller_discount', 'platform_discount'] as $key) {
            $this->assertEquals($expected[$key] / 100, collect($report['metrics'])->firstWhere('key', $key)['value']);
        }
        $vouchers = collect($report['sections'])->firstWhere('key', 'vouchers')['rows'];
        $this->assertSame('platform', $vouchers[0]['Funding']);
        $this->assertEquals(1, collect($report['metrics'])->firstWhere('key', 'completed')['value']);
    }

    public function test_spreadsheet_and_pdf_are_real_scoped_downloads_and_exports_are_audited(): void
    {
        $seller = $this->user('seller');
        $mine = $this->order(['seller_id' => $seller->id, 'order_number' => '=FORMULA-LIKE-REFERENCE']);
        $other = $this->order(['order_number' => 'OTHER-SELLER-SECRET']);
        $this->signIn($seller);
        $xlsx = $this->get(route('analytics.export', ['format' => 'xlsx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->getContent();
        $path = tempnam(sys_get_temp_dir(), 'analytics-test-');
        try {
            file_put_contents($path, $xlsx);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
            $xml = '';
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getFromIndex($index);
                $this->assertNotFalse(simplexml_load_string($entry));
                $xml .= $entry;
            }
            $zip->close();
            $this->assertStringContainsString($mine->order_number, $xml);
            $this->assertStringNotContainsString($other->order_number, $xml);
            $this->assertStringNotContainsString('<f>', $xml);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $pdf = $this->get(route('analytics.export', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString($mine->order_number, $pdf);
        $this->assertStringNotContainsString($other->order_number, $pdf);
        $this->assertStringContainsString('Orders placed over time', $pdf);
        $csv = $this->get(route('analytics.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=FORMULA-LIKE-REFERENCE", $csv);
        $this->assertDatabaseCount('administrative_audits', 3);
    }

    public function test_rider_attempts_exclude_other_riders_and_invalid_durations_are_not_zero(): void
    {
        $rider = $this->user('courier');
        $other = $this->user('courier');
        $order = $this->order(['delivery_courier_id' => $rider->id, 'picked_up_at' => now(), 'delivered_at' => now()->subHour()]);
        DeliveryAttempt::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'failed', 'attempted_at' => now()]);
        DeliveryAttempt::create(['order_id' => $order->id, 'rider_id' => $other->id, 'attempt_no' => 2, 'outcome' => 'failed', 'attempted_at' => now()]);
        $report = $this->signIn($rider)->get(route('analytics.index'))->assertOk()->viewData('report');
        $this->assertEquals(1, collect($report['metrics'])->firstWhere('key', 'failed_attempts')['value']);
        $this->assertNull(collect($report['metrics'])->firstWhere('key', 'delivery_hours')['value']);
        $this->assertCount(1, collect($report['sections'])->firstWhere('key', 'attempts')['rows']);
    }

    public function test_customer_history_respects_the_selected_seller_and_product_rankings_use_eligible_units(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $admin = $this->user('ecommerce_admin');
        $this->order(['buyer_id' => $buyer->id, 'status' => 'COMPLETED', 'created_at' => '2026-09-15 12:00:00']);
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Ranked product', 'category' => 'Bags', 'stock' => 2, 'price' => 100, 'is_archived' => false]);
        $order = $this->order(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'COMPLETED']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'item_total' => 100]);
        $report = $this->signIn($admin)->get(route('analytics.index', ['seller_id' => $seller->id, 'category' => 'Bags']))->assertOk()->viewData('report');
        $customers = collect($report['sections'])->firstWhere('key', 'customers')['rows'];
        $this->assertSame(1, $customers[1]['Value']);
        $this->assertSame(0, $customers[2]['Value']);
        $products = collect($report['sections'])->firstWhere('key', 'products')['rows'];
        $this->assertSame('Ranked product', $products[0]['Product']);
        $this->assertEquals(1, $products[0]['Units fulfilled']);
        $this->assertEquals(100, $products[0]['Gross PHP']);
    }

    public function test_pending_cancellation_is_reported_separately_from_cancelled_orders(): void
    {
        $buyer = $this->user('buyer');
        $order = $this->order(['buyer_id' => $buyer->id]);
        OrderCancellationRequest::create(['order_id' => $order->id, 'active_order_id' => $order->id, 'requested_by' => $buyer->id, 'status' => 'PENDING', 'reason' => 'Wrong item', 'respond_by' => now()->addDay()]);
        $report = $this->signIn($buyer)->get(route('analytics.index'))->assertOk()->viewData('report');
        $this->assertEquals(0, collect($report['metrics'])->firstWhere('key', 'cancelled')['value']);
        $requests = collect($report['sections'])->firstWhere('key', 'cancellations')['rows'];
        $this->assertSame('PENDING', $requests[0]['Status']);
        $this->assertSame($order->order_number, $requests[0]['Order']);
    }

    public function test_analytics_require_login_and_reports_do_not_silently_truncate(): void
    {
        $this->get(route('analytics.index'))->assertRedirectToRoute('login');
        $this->get(route('analytics.export', ['format' => 'pdf']))->assertRedirectToRoute('login');
        $admin = $this->user('ecommerce_admin');
        $this->signIn($admin)->get(route('analytics.index'))->assertOk()->assertSee('No matching records');
        config(['analytics.maximum_orders' => 1]);
        $this->order();
        $this->order();
        $this->getJson(route('analytics.index'))->assertJsonValidationErrors('from');
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Analytics', 'last_name' => 'User', 'business_name' => 'Shop '.Str::random(6), 'contact_no' => '09123456789']);
    }

    private function order(array $data = []): Order
    {
        return Order::forceCreate(array_replace(['order_number' => 'AN-'.Str::random(10), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'recipient_name' => 'Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Street', 'subtotal' => 100, 'total_amount' => 150, 'shipping_fee' => 50, 'payment_method' => 'COD', 'status' => 'PLACED'], $data));
    }
}
