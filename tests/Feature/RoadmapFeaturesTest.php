<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DeliveryAssignment;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundExecution;
use App\Models\User;
use App\Services\AdministrativeAuditService;
use App\Services\CheckoutPricingService;
use App\Services\DirectMessagingService;
use App\Services\OrderTransitionService;
use App\Services\SalesReportService;
use App\Services\StockSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoadmapFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function actingAsUser(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    public function test_browsing_personalizes_recommendations_without_exposing_hidden_products(): void
    {
        $buyer = $this->user('buyer');
        $product = $this->product(['category' => 'Books']);
        $other = $this->product(['name' => 'New phone', 'category' => 'Electronics', 'created_at' => now()->addMinute()]);
        $hidden = $this->product(['name' => 'Hidden book', 'category' => 'Books', 'compliance_status' => 'pending_review']);
        $this->actingAsUser($buyer)->get(route('product.show', $product))->assertOk();
        $this->get(route('buyer.for-you'))->assertOk()->assertViewHas('products', fn ($products) => $products->first()->id === $product->id && ! $products->contains('id', $hidden->id));
        $this->assertDatabaseHas('product_interactions', ['user_id' => $buyer->id, 'product_id' => $product->id]);
        $this->delete(route('buyer.for-you.reset'))->assertRedirect();
        $this->assertDatabaseMissing('product_interactions', ['user_id' => $buyer->id]);
        $this->actingAsUser($this->user('seller'))->get(route('buyer.for-you'))->assertForbidden();
    }

    public function test_stock_alerts_follow_changes_once_and_are_scoped_to_buyer(): void
    {
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $product = $this->product(['stock' => 10]);
        $this->actingAsUser($buyer)->post(route('buyer.stock.watch', $product))->assertRedirect();
        $this->post(route('buyer.stock.watch', $product))->assertRedirect();
        $this->assertDatabaseCount('stock_subscriptions', 1);
        $product->update(['stock' => 2]);
        $subscriptions = app(StockSubscriptionService::class);
        $id = DB::table('stock_subscriptions')->value('id');
        $subscriptions->check($id);
        $subscriptions->check($id);
        $this->assertSame(1, $buyer->notifications()->count());
        $product->update(['stock' => 0]);
        $subscriptions->check($id);
        $product->update(['stock' => 12]);
        $subscriptions->check($id);
        $this->assertSame(3, $buyer->notifications()->count());
        $this->actingAsUser($other)->get(route('buyer.stock.index'))->assertOk()->assertDontSee($product->name);
        $this->delete(route('buyer.stock.unwatch', $product))->assertRedirect();
        $this->assertDatabaseCount('stock_subscriptions', 1);
        $this->actingAsUser($buyer)->delete(route('buyer.stock.unwatch', $product))->assertRedirect();
        $this->assertDatabaseCount('stock_subscriptions', 0);
    }

    public function test_chat_inbox_only_contains_own_orders(): void
    {
        $order = $this->order();
        $other = $this->order();
        $conversation = app(DirectMessagingService::class)->start($order->buyer, $order->seller);
        $this->actingAsUser($order->buyer)->get(route('chat.index'))->assertOk()->assertViewHas('threads', fn ($threads) => $threads->count() === 1 && $threads->first()->id === $conversation->id)->assertDontSee($other->order_number);
        $this->actingAsUser($this->user('courier'))->get(route('chat.index'))->assertOk()->assertDontSee($order->order_number)->assertDontSee($other->order_number);
    }

    public function test_cancellation_requires_seller_review_and_restores_stock_once(): void
    {
        $order = $this->order();
        $product = $this->reserved($order);
        $this->actingAsUser($order->buyer)->post(route('buyer.orders.cancel', $order), ['reason' => 'Ordered the wrong item.'])->assertRedirect();
        $this->assertSame('PLACED', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseHas('order_cancellation_requests', ['order_id' => $order->id, 'status' => 'PENDING']);
        $this->post(route('buyer.orders.cancel', $order))->assertUnprocessable();
        $this->actingAsUser($this->user('seller'))->post(route('seller.orders.cancellation', $order), ['decision' => 'approve', 'response' => 'Approved as requested.'])->assertForbidden();
        $this->actingAsUser($order->seller)->post(route('seller.orders.cancellation', $order), ['decision' => 'approve', 'response' => 'Approved as requested.'])->assertRedirect();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->stock);
        $this->post(route('seller.orders.cancellation', $order), ['decision' => 'approve', 'response' => 'Approved as requested.'])->assertUnprocessable();
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_rejection_resumes_fulfillment_and_pending_request_blocks_transitions(): void
    {
        $order = $this->order();
        $this->reserved($order);
        $this->actingAsUser($order->buyer)->post(route('buyer.orders.cancel', $order))->assertRedirect();
        $this->actingAsUser($order->seller)->patchJson(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertUnprocessable();
        $this->postJson(route('seller.orders.cancel', $order), ['reason' => 'Direct cancellation must use approval.'])->assertUnprocessable();
        $this->get(route('seller.orders.show', $order))->assertOk()->assertSee('Approve cancellation')->assertDontSee('id="seller-cancel-reason"', false);
        $this->post(route('seller.orders.cancellation', $order), ['decision' => 'reject', 'response' => 'Buyer requested fulfillment by chat.'])->assertRedirect();
        $this->assertDatabaseHas('order_cancellation_requests', ['status' => 'REJECTED', 'active_order_id' => null]);
        app(OrderTransitionService::class)->transition($order, $order->seller, OrderStatus::Confirmed, 'confirmed');
        $this->assertSame('CONFIRMED', $order->fresh()->status);
    }

    public function test_cancellation_automation_observes_24_hour_deadline_and_custody_guard(): void
    {
        $order = $this->order();
        $product = $this->reserved($order);
        $this->actingAsUser($order->buyer)->post(route('buyer.orders.cancel', $order))->assertRedirect();
        $this->travel(23)->hours();
        $this->artisan('commerce:process-automation')->assertSuccessful();
        $this->assertSame('PLACED', $order->fresh()->status);
        $this->travel(2)->hours();
        $this->artisan('commerce:process-automation')->assertSuccessful();
        $this->artisan('commerce:process-automation')->assertSuccessful();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'cancellation_approved', 'actor_id' => null]);
        $physical = $this->order(['seller_handover_at' => now()]);
        $this->actingAsUser($physical->buyer)->post(route('buyer.orders.cancel', $physical))->assertUnprocessable();
    }

    public function test_rider_can_accept_before_deadline_and_expired_assignment_returns_to_dispatch(): void
    {
        $rider = $this->user('courier');
        $accepted = $this->assigned($rider);
        $expired = $this->assigned($rider);
        $this->actingAsUser($rider)->post(route('courier.orders.accept-assignment', $accepted))->assertRedirect();
        $this->assertNotNull($accepted->deliveryAssignments()->first()->accepted_at);
        $this->travel(16)->minutes();
        $this->post(route('courier.orders.accept-assignment', $expired))->assertUnprocessable();
        $this->artisan('commerce:process-automation')->assertSuccessful();
        $this->assertSame('ASSIGNED_TO_RIDER', $accepted->fresh()->status);
        $this->assertSame('SORTED', $expired->fresh()->status);
        $this->assertNull($expired->fresh()->delivery_courier_id);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $expired->id, 'active_order_id' => null]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $expired->id, 'event_type' => 'rider_acceptance_expired']);
    }

    public function test_rider_cannot_accept_another_riders_assignment(): void
    {
        $order = $this->assigned($this->user('courier'));
        $this->actingAsUser($this->user('courier'))->post(route('courier.orders.accept-assignment', $order))->assertForbidden();
    }

    public function test_coverage_has_one_primary_per_barangay_and_enforces_hub_and_area_ownership(): void
    {
        $hub = $this->user('sorting_center');
        $otherHub = $this->user('sorting_center');
        $area = Area::create(['name' => 'Town', 'code' => 'town', 'is_active' => true, 'sorting_center_id' => $hub->id]);
        $mapping = AreaMunicipality::create(['area_id' => $area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
        $rider = $this->user('courier');
        $backup = $this->user('courier');
        foreach ([$rider, $backup] as $user) {
            $user->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        }
        $data = ['area_municipality_id' => $mapping->id, 'barangay' => 'Poblacion', 'primary_rider_id' => $rider->id, 'backup_rider_id' => $backup->id, 'reason' => 'Set primary coverage.'];
        $this->actingAsUser($otherHub)->post(route('coverage.store'), $data)->assertForbidden();
        $this->actingAsUser($hub)->post(route('coverage.store'), $data)->assertRedirect();
        $this->post(route('coverage.store'), array_replace($data, ['barangay' => ' POBLACION ', 'primary_rider_id' => $backup->id, 'backup_rider_id' => $rider->id]))->assertRedirect();
        $this->assertDatabaseCount('barangay_rider_assignments', 1);
        $this->assertDatabaseHas('barangay_rider_assignments', ['primary_rider_id' => $backup->id]);
        $this->patch(route('coverage.vehicle', $rider), ['vehicle_category' => 'four_wheel', 'vehicle_description' => 'Delivery van', 'reason' => 'Updated vehicle category.'])->assertRedirect();
        $this->assertSame('four_wheel', $rider->fresh()->vehicle_category);
    }

    public function test_shipping_fee_is_authorized_validated_and_used_by_new_quotes(): void
    {
        $admin = $this->user('logistics_admin');
        $this->actingAsUser($this->user('buyer'))->patch(route('coverage.shipping'), ['shipping_fee' => 60, 'reason' => 'New standard fee.'])->assertForbidden();
        $this->actingAsUser($admin)->patchJson(route('coverage.shipping'), ['shipping_fee' => 81, 'reason' => 'New standard fee.'])->assertJsonValidationErrors('shipping_fee');
        $this->patch(route('coverage.shipping'), ['shipping_fee' => 65, 'reason' => 'New standard fee.'])->assertRedirect();
        $product = $this->product();
        $quote = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'quantity' => 1]]);
        $this->assertSame(6500, $quote['shipping_cents']);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'standard_shipping_fee_updated']);
    }

    public function test_sales_csv_pdf_filters_and_seller_isolation(): void
    {
        $order = $this->order(['status' => 'COMPLETED']);
        $other = $this->order(['status' => 'COMPLETED']);
        $this->actingAsUser($order->seller)->get(route('sales-reports.index'))->assertOk()->assertSee($order->order_number)->assertDontSee($other->order_number);
        $csv = $this->get(route('sales-reports.index', ['format' => 'csv']))->assertOk()->assertDownload('ezicart-sales.csv')->streamedContent();
        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringNotContainsString($other->order_number, $csv);
        $pdf = $this->get(route('sales-reports.index', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->getJson(route('sales-reports.index', ['from_date' => '2026-02-02', 'to_date' => '2026-01-01']))->assertJsonValidationErrors('to_date');
        $this->actingAsUser($this->user('buyer'))->get(route('sales-reports.index'))->assertForbidden();
    }

    public function test_sales_accounting_distinguishes_platform_and_seller_discounts(): void
    {
        $order = $this->order(['status' => 'COMPLETED', 'subtotal' => 100, 'discount_amount' => 20, 'platform_discount_amount' => 15]);
        $row = app(SalesReportService::class)->row($order->load('seller', 'finance'));
        $this->assertSame(10000, $row['gross']);
        $this->assertSame(500, $row['seller_discount']);
        $this->assertSame(1500, $row['platform_discount']);
        $this->assertSame(9500, $row['net']);
        $order->forceFill(['status' => 'CANCELLED'])->save();
        $this->assertSame(0, app(SalesReportService::class)->row($order)['net']);
    }

    public function test_activity_search_preserves_administration_domain_permissions(): void
    {
        $commerce = $this->user('ecommerce_admin');
        $logistics = $this->user('logistics_admin');
        $audits = app(AdministrativeAuditService::class);
        $audits->record($commerce, $commerce, 'commerce_marker', [], ['value' => 'Commerce detail'], 'Commerce operation.');
        $audits->record($logistics, $logistics, 'logistics_marker', [], ['value' => 'Logistics detail'], 'Logistics operation.');
        $this->actingAsUser($commerce)->get(route('activity-logs.index'))->assertOk()->assertSee('commerce_marker')->assertDontSee('logistics_marker');
        $this->actingAsUser($logistics)->get(route('activity-logs.index', ['action' => 'logistics_marker', 'actor_id' => $logistics->id]))->assertOk()->assertSee('logistics_marker')->assertDontSee('commerce_marker');
        $this->actingAsUser($this->user('seller'))->get(route('activity-logs.index'))->assertForbidden();
    }

    public function test_activity_changes_show_readable_differences_and_preserve_recorded_data(): void
    {
        $admin = $this->user('ecommerce_admin');
        $before = ['status' => 'PREPARING', 'nullable_field' => null, 'active' => false, 'unchanged' => 'same', 'nested' => ['a' => 1, 'b' => 2]];
        $after = ['status' => 'READY_FOR_PICKUP', 'active' => true, 'new_field' => '<script>unsafe()</script>', 'unchanged' => 'same', 'nested' => ['b' => 2, 'a' => 1]];
        app(AdministrativeAuditService::class)->record($admin, $admin, 'ready_for_pickup', $before, $after, 'Seller updated order fulfillment status.');
        $audit = DB::table('administrative_audits')->first();

        $this->actingAsUser($admin)->get(route('activity-logs.index'))->assertOk()
            ->assertSee('View changes')->assertSee('Preparing')->assertSee('Ready For Pickup')
            ->assertSee('Recorded reason')->assertSee('Only in before')->assertSee('Only in after')
            ->assertSee('Not set (null)')->assertSee('Not recorded')->assertSee('2 unchanged fields')
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false)
            ->assertSee('View recorded snapshots (JSON)');
        $this->assertSame($audit->previous_values, DB::table('administrative_audits')->value('previous_values'));
        $this->assertSame($audit->new_values, DB::table('administrative_audits')->value('new_values'));
    }

    public function test_full_refund_reverses_platform_subsidy_in_sales_report(): void
    {
        $order = $this->order(['status' => 'RETURNED_TO_SELLER', 'subtotal' => 100, 'discount_amount' => 20, 'platform_discount_amount' => 15, 'delivered_at' => now()->subDays(3)]);
        $admin = $this->user('ecommerce_admin');
        $dispute = Dispute::forceCreate(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Damaged', 'description' => 'Damaged item returned.', 'status' => 'REFUND_APPROVED']);
        RefundExecution::forceCreate(['order_id' => $order->id, 'dispute_id' => $dispute->id, 'amount_cents' => 13000, 'status' => 'EXECUTED', 'authorized_by' => $admin->id, 'executed_by' => $admin->id, 'executed_at' => now()]);
        $this->actingAsUser($order->seller)->get(route('sales-reports.index'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()['merchandise_refund'] === 8000 && $rows->first()['platform_reversal'] === 1500 && $rows->first()['net'] === 0);
    }

    public function test_dispatch_requires_a_reason_for_using_non_primary_barangay_rider(): void
    {
        $hub = $this->user('sorting_center');
        $area = Area::create(['name' => 'Local town', 'code' => 'local', 'is_active' => true, 'sorting_center_id' => $hub->id]);
        $mapping = AreaMunicipality::create(['area_id' => $area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
        $primary = $this->user('courier');
        $backup = $this->user('courier');
        foreach ([$primary, $backup] as $rider) {
            $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        }
        DB::table('barangay_rider_assignments')->insert(['area_municipality_id' => $mapping->id, 'barangay' => 'Poblacion', 'barangay_normalized' => 'poblacion', 'primary_rider_id' => $primary->id, 'backup_rider_id' => $backup->id, 'created_at' => now(), 'updated_at' => now()]);
        $order = $this->order(['status' => 'SORTED', 'destination_area_id' => $area->id, 'sorting_center_id' => $hub->id]);
        $this->actingAsUser($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $backup->id])->assertUnprocessable();
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $backup->id, 'coverage_override_reason' => 'Primary rider is unavailable today.'])->assertRedirect();
        $this->assertSame($backup->id, $order->fresh()->delivery_courier_id);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $backup->id, 'status' => 'active']);
    }

    public function test_new_feature_pages_require_login(): void
    {
        foreach (['buyer.for-you', 'buyer.stock.index', 'chat.index', 'sales-reports.index', 'activity-logs.index', 'coverage.index'] as $route) {
            $this->get(route($route))->assertRedirectToRoute('login');
        }
    }

    public function test_seller_voucher_actions_are_audited_and_duplicate_codes_normalized(): void
    {
        $seller = $this->user('seller');
        $data = ['code' => 'storedeal', 'type' => 'fixed', 'value' => 5];
        $this->actingAsUser($seller)->post(route('seller.vouchers.store'), $data)->assertRedirect();
        $this->assertDatabaseHas('administrative_audits', ['action' => 'seller_voucher_created', 'actor_id' => $seller->id]);
        $this->postJson(route('seller.vouchers.store'), $data)->assertJsonValidationErrors('code');
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Roadmap', 'last_name' => 'User', 'business_name' => 'Roadmap Shop', 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay']);
    }

    private function product(array $data = []): Product
    {
        return Product::forceCreate(array_replace(['user_id' => $this->user('seller')->id, 'name' => 'Item '.Str::random(8), 'category' => 'Test', 'description' => 'Product description', 'price' => 100, 'stock' => 10, 'compliance_status' => 'approved', 'is_archived' => false], $data));
    }

    private function order(array $data = []): Order
    {
        return Order::forceCreate(array_replace(['order_number' => 'ROAD-'.Str::random(12), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'recipient_name' => 'Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Street', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'commission_fee' => 10, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'PLACED'], $data));
    }

    private function reserved(Order $order): Product
    {
        $product = $this->product(['user_id' => $order->seller_id, 'stock' => 0]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'item_total' => 100]);

        return $product;
    }

    private function assigned(User $rider): Order
    {
        $order = $this->order(['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $rider->id, 'assigned_at' => now(), 'sorting_center_id' => $this->user('sorting_center')->id]);
        DeliveryAssignment::create(['order_id' => $order->id, 'active_order_id' => $order->id, 'rider_id' => $rider->id, 'status' => 'active', 'assigned_at' => now()]);

        return $order;
    }
}
