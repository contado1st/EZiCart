<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationChannels;
use App\Models\Area;
use App\Models\DeliveryAttempt;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\ParcelTransfer;
use App\Models\Product;
use App\Models\RefundExecution;
use App\Models\SellerPayout;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\InventoryRestorationService;
use App\Services\OrderFinancialService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[TestWith(['admin', true, false])]
    #[TestWith(['ecommerce_admin', true, false])]
    #[TestWith(['logistics_admin', false, true])]
    #[TestWith(['buyer', false, false])]
    #[TestWith(['seller', false, false])]
    #[TestWith(['courier', false, false])]
    #[TestWith(['sorting_center', false, false])]
    public function test_administrator_workspace_permission_matrix(string $role, bool $commerce, bool $logistics): void
    {
        $actor = $this->user($role);
        $this->actingAs($actor)->get(route('admin.dashboard'))->assertStatus($commerce ? 200 : 403);
        $this->get(route('logistics-admin.dashboard'))->assertStatus($logistics ? 200 : 403);
        $seller = $this->user('seller');
        $hub = $this->user('sorting_center');
        $this->assertSame($commerce, $actor->canManageAccount($seller));
        $this->assertSame($logistics, $actor->canManageAccount($hub));
    }

    public function test_cross_domain_account_approvals_are_denied_and_own_domain_is_audited(): void
    {
        $seller = $this->user('seller');
        $hub = $this->user('sorting_center');
        $seller->forceFill(['status' => 'pending'])->save();
        $hub->forceFill(['status' => 'pending'])->save();
        $commerce = $this->user('ecommerce_admin');
        $logistics = $this->user('logistics_admin');
        $this->actingAs($commerce)->post(route('admin.registrations.approve', $hub))->assertForbidden();
        $this->flushSession();
        $this->actingAs($logistics)->post(route('logistics-admin.users.approve', $seller))->assertForbidden();
        $this->post(route('logistics-admin.users.approve', $hub))->assertRedirect();
        $this->assertDatabaseHas('administrative_audits', ['actor_id' => $logistics->id, 'entity_id' => $hub->id, 'action' => 'account_approved']);
        $this->assertSame('pending', $seller->fresh()->status);
    }

    public function test_expiration_restores_once_and_never_cancels_a_parcel_in_custody(): void
    {
        $this->freezeTime();
        $order = $this->order(['seller_response_due_at' => now()->subMinute()]);
        $product = $this->reservedItem($order);
        $order->seller->forceFill(['status' => 'suspended'])->save();
        $custody = $this->order(['status' => 'PREPARING', 'preparation_due_at' => now()->subDay(), 'seller_handover_at' => now()->subHour()]);
        $this->artisan('orders:expire-seller-deadlines')->assertSuccessful();
        $this->artisan('orders:expire-seller-deadlines')->assertSuccessful();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame('PREPARING', $custody->fresh()->status);
        $this->assertEquals(1, $product->fresh()->stock);
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'seller_deadline_expired')->count());
        $this->assertDatabaseHas('order_finances', ['order_id' => $order->id, 'due_cents' => 0, 'seller_payable_cents' => 0, 'remittance_status' => 'VOID']);
    }

    public function test_return_receipt_requires_inspection_before_restocking_and_cannot_be_repeated(): void
    {
        $order = $this->order(['status' => 'RETURNED_TO_SELLER', 'return_handed_to_seller_at' => now()]);
        $product = $this->reservedItem($order);
        $this->assertEquals(0, $product->stock);
        $this->actingAs($this->user('seller'))->post(route('seller.orders.return-inspection', $order), ['restock' => 1, 'reason' => 'Packaging and product intact.'])->assertForbidden();
        $this->flushSession();
        $this->actingAs($order->seller)->post(route('seller.orders.return-inspection', $order), ['restock' => 1, 'reason' => 'Packaging and product intact.'])->assertRedirect();
        $this->post(route('seller.orders.return-inspection', $order), ['restock' => 1, 'reason' => 'Repeat request rejected.'])->assertUnprocessable();
        app(InventoryRestorationService::class)->restore($order);
        $this->assertEquals(1, $product->fresh()->stock);
    }

    public function test_cod_shortage_cannot_be_verified_and_payout_approval_is_separate_from_settlement(): void
    {
        Storage::fake('private');
        $this->freezeTime();
        $rider = $this->user('courier');
        $order = $this->collectedOrder($rider);
        $logistics = $this->user('logistics_admin');
        $commerce = $this->user('ecommerce_admin');
        $this->actingAs($rider)->post(route('finance.remittance', $order), $this->receipt('SHORT', ['amount' => 100]))->assertRedirect();
        $this->assertDatabaseHas('order_finances', ['order_id' => $order->id, 'remittance_status' => 'DISCREPANCY']);
        $this->flushSession();
        $this->actingAs($logistics)->post(route('finance.remittance.verify', $order), ['reason' => 'Shortage cannot be verified.'])->assertUnprocessable();
        $this->flushSession();
        $this->actingAs($rider)->post(route('finance.remittance', $order), $this->receipt('CORRECTED', ['amount' => 150]))->assertRedirect();
        $this->flushSession();
        $this->actingAs($commerce)->post(route('finance.remittance.verify', $order), ['reason' => 'Wrong department denied.'])->assertForbidden();
        $this->flushSession();
        $this->actingAs($logistics)->post(route('finance.remittance.verify', $order), ['reason' => 'Cash counted against receipt.'])->assertRedirect();
        $order->forceFill(['status' => 'COMPLETED'])->save();
        $this->flushSession();
        $this->actingAs($order->seller)->post(route('finance.payout.request', $order))->assertRedirect();
        $this->post(route('finance.payout.request', $order))->assertRedirect();
        $this->assertDatabaseCount('seller_payouts', 1);
        $payout = SellerPayout::firstOrFail();
        $this->assertEquals(9000, $payout->amount_cents);
        $this->flushSession();
        $this->actingAs($logistics)->post(route('finance.payout.approve', $payout), ['reason' => 'Wrong department denied.'])->assertForbidden();
        $this->flushSession();
        $this->actingAs($commerce)->post(route('finance.payout.approve', $payout), ['reason' => 'Reconciled payout approved.'])->assertRedirect();
        $this->assertSame('APPROVED', $payout->fresh()->status);
        $this->assertNull($payout->fresh()->executed_at);
        $this->get(route('finance.index'))->assertOk()->assertSee('Record external payout settlement');
        $this->post(route('finance.payout.settle', $payout), ['reference' => 'BANK-PAYOUT', 'reason' => 'Missing transfer receipt.'])->assertSessionHasErrors('evidence');
        $this->post(route('finance.payout.settle', $payout), $this->receipt('BANK-PAYOUT'))->assertRedirect();
        $this->assertSame('SETTLED', $payout->fresh()->status);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'external_payout_recorded', 'actor_id' => $commerce->id]);
        $this->get(route('admin.reports.index'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['total_commission'] === 10.0 && (float) $stats['settled_payouts'] === 90.0);
    }

    public function test_damaged_return_inspection_prevents_later_restocking(): void
    {
        $order = $this->order(['status' => 'RETURNED_TO_SELLER']);
        $product = $this->reservedItem($order);
        $this->actingAs($order->seller)->post(route('seller.orders.return-inspection', $order), ['restock' => 0, 'reason' => 'Items damaged and not sellable.'])->assertRedirect();
        $this->expectException(HttpException::class);
        try {
            app(InventoryRestorationService::class)->restore($order);
        } finally {
            $this->assertEquals(0, $product->fresh()->stock);
            $this->assertFalse($order->fresh()->restock_approved);
        }
    }

    public function test_refund_approval_does_not_send_money_and_requires_verified_funds_and_return_before_execution(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier');
        $order = $this->collectedOrder($rider);
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Damaged Item', 'description' => 'The delivered item is damaged.', 'status' => 'PENDING']);
        $commerce = $this->user('ecommerce_admin');
        $this->actingAs($commerce)->patch(route('admin.disputes.resolve', $dispute), ['status' => 'REFUND_APPROVED', 'admin_notes' => 'Full return and refund authorized.'])->assertRedirect();
        $refund = RefundExecution::firstOrFail();
        $this->assertSame('AUTHORIZED', $refund->status);
        $this->assertNull($refund->executed_at);
        $this->get(route('finance.index'))->assertOk()->assertSee('Record external refund');
        $this->post(route('finance.refund.execute', $refund), $this->receipt('REFUND-ONE'))->assertUnprocessable();
        $this->flushSession();
        $this->actingAs($rider)->post(route('finance.remittance', $order), $this->receipt('CASH-RETURN', ['amount' => 150]))->assertRedirect();
        $this->flushSession();
        $this->actingAs($this->user('logistics_admin'))->post(route('finance.remittance.verify', $order), ['reason' => 'Receipt and cash verified.'])->assertRedirect();
        $order->forceFill(['status' => 'RETURNED_TO_SELLER', 'return_handed_to_seller_at' => now()])->save();
        $this->flushSession();
        $this->actingAs($commerce)->post(route('finance.refund.execute', $refund), $this->receipt('REFUND-ONE'))->assertRedirect();
        $this->assertSame('EXECUTED', $refund->fresh()->status);
        $this->flushSession();
        $this->actingAs($this->user('buyer'))->get(route('finance.evidence', ['refund', $refund->id]))->assertForbidden();
        $this->flushSession();
        $this->actingAs($order->buyer)->get(route('finance.evidence', ['refund', $refund->id]))->assertOk();
    }

    public function test_replacement_reserves_new_stock_once_and_has_no_additional_charge(): void
    {
        $order = $this->order(['status' => 'RETURNED_TO_SELLER']);
        $product = $this->reservedItem($order);
        $product->update(['stock' => 2]);
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Damaged Item', 'description' => 'Replace the damaged product.', 'status' => 'REPLACEMENT_APPROVED']);
        $this->actingAs($order->seller)->post(route('seller.replacements.store', $dispute))->assertRedirect();
        $this->post(route('seller.replacements.store', $dispute))->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(1, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', ['replacement_for_order_id' => $order->id, 'total_amount' => 0, 'shipping_fee' => 0]);
    }

    public function test_cross_hub_transfer_requires_both_scans_and_restricts_operators(): void
    {
        $source = $this->user('sorting_center');
        $destination = $this->user('sorting_center');
        $otherHub = $this->user('sorting_center');
        $area = Area::create(['name' => 'Destination', 'code' => 'destination', 'sorting_center_id' => $destination->id, 'is_active' => true]);
        $order = $this->order(['status' => 'SORTED', 'sorting_center_id' => $source->id, 'destination_area_id' => $area->id]);
        $this->actingAs($this->user('ecommerce_admin'))->post(route('logistics-admin.orders.transfer', $order), ['destination_hub_id' => $destination->id, 'reason' => 'Misrouted parcel transfer.'])->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->user('logistics_admin'))->post(route('logistics-admin.orders.transfer', $order), ['destination_hub_id' => $destination->id, 'reason' => 'Misrouted parcel transfer.'])->assertRedirect();
        $transfer = ParcelTransfer::firstOrFail();
        $this->flushSession();
        $this->actingAs($this->user('buyer'))->post(route('logistics.orders.sort', $order))->assertForbidden();
        $this->flushSession();
        $this->actingAs($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->flushSession();
        $this->actingAs($source)->post(route('parcel-transfers.release', $transfer), ['parcel_reference' => 'EZP:WRONG', 'transport_reference' => 'MANIFEST-ONE'])->assertUnprocessable();
        $this->post(route('parcel-transfers.release', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'transport_reference' => 'MANIFEST-ONE'])->assertRedirect();
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertEquals($source->id, $order->fresh()->sorting_center_id);
        $this->post(route('logistics.orders.sort', $order))->assertUnprocessable();
        DB::table('storage_locations')->insert(['hub_id' => $source->id, 'code' => 'TRANSFER-EXCEPTION', 'label' => 'Exception location', 'type' => 'EXCEPTION', 'capacity' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->post(route('logistics.storage.putaway'), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'location_reference' => 'EZL:TRANSFER-EXCEPTION'])->assertSessionHasErrors('parcel_reference');
        $this->assertDatabaseCount('parcel_placements', 0);
        $this->flushSession();
        $this->actingAs($otherHub)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertForbidden();
        $this->post(route('logistics.orders.sort', $order))->assertForbidden();
        $this->flushSession();
        $this->actingAs($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect();
        $this->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->assertSame('AT_SORTING_CENTER', $order->fresh()->status);
        $this->assertEquals($destination->id, $order->fresh()->sorting_center_id);
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'hub_transfer_received')->count());
    }

    public function test_parcel_aging_alert_is_sent_once_per_observed_stage_to_logistics_only(): void
    {
        $this->freezeTime();
        $hub = $this->user('sorting_center');
        $logistics = $this->user('logistics_admin');
        $commerce = $this->user('ecommerce_admin');
        $order = $this->order(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $hub->id, 'created_at' => now()->subDays(2)]);
        $this->artisan('logistics:alert-aging-parcels')->assertSuccessful();
        $this->artisan('logistics:alert-aging-parcels')->assertSuccessful();
        $this->assertDatabaseCount('operational_alerts', 1);
        $this->assertSame(1, $logistics->notifications()->count());
        $this->assertSame(1, $hub->notifications()->count());
        $this->assertSame(0, $commerce->notifications()->count());
        $this->assertSame(0, $order->buyer->notifications()->count());
        $this->actingAs($logistics)->get(route('logistics-admin.dashboard'))->assertOk()->assertViewHas('aging', 1);
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $hub->id, 'event_type' => 'physical_scan', 'status' => $order->status]);
        $this->get(route('logistics-admin.dashboard'))->assertOk()->assertViewHas('aging', 0);
    }

    public function test_variation_edits_preserve_ids_and_omitted_variations_are_not_deleted(): void
    {
        $order = $this->order();
        $product = $this->reservedItem($order);
        $variant = $product->variations()->create(['type' => 'Color', 'value' => 'Blue', 'price_adjustment' => 10, 'stock' => 3]);
        $payload = ['name' => $product->name, 'category' => $product->category, 'price' => 100, 'stock' => 0];
        $this->actingAs($order->seller)->put(route('seller.products.update', $product), $payload)->assertRedirect();
        $this->assertDatabaseHas('product_variations', ['id' => $variant->id, 'is_active' => true]);
        $this->put(route('seller.products.update', $product), [...$payload, 'variations' => [['id' => $variant->id, 'type' => 'Color', 'value' => 'Sky Blue', 'price_adjustment' => 15, 'stock' => 3]]])->assertRedirect();
        $this->assertDatabaseHas('product_variations', ['id' => $variant->id, 'value' => 'Sky Blue']);
    }

    public function test_external_notifications_are_queued_only_after_transaction_commit(): void
    {
        Queue::fake();
        $order = $this->order();
        DB::transaction(function () use ($order): void {
            app(TransactionAwareNotificationSender::class)->send($order->seller, new OrderWorkflowNotification($order, 'test_workflow', 'Committed workflow notification.'));
            Queue::assertNothingPushed();
        });
        Queue::assertPushed(DeliverNotificationChannels::class, 1);
        $this->assertSame(1, $order->seller->notifications()->count());
    }

    public function test_platform_promotions_preserve_seller_payable_and_require_funding_before_payout(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier');
        $order = $this->collectedOrder($rider, ['discount_amount' => 30, 'platform_discount_amount' => 30, 'total_amount' => 120, 'cod_collected_amount' => 120]);
        $this->actingAs($rider)->post(route('finance.remittance', $order), $this->receipt('SUBSIDY-CASH', ['amount' => 120]))->assertRedirect();
        $this->flushSession();
        $this->actingAs($this->user('logistics_admin'))->post(route('finance.remittance.verify', $order), ['reason' => 'Buyer COD cash verified.'])->assertRedirect();
        $order->forceFill(['status' => 'COMPLETED'])->save();
        $this->flushSession();
        $this->actingAs($order->seller)->post(route('finance.payout.request', $order))->assertUnprocessable();
        $this->assertEquals(9000, $order->finance->seller_payable_cents);
        $this->flushSession();
        $this->actingAs($this->user('ecommerce_admin'))->post(route('finance.subsidy', $order), $this->receipt('PLATFORM-FUNDING'))->assertRedirect();
        $this->flushSession();
        $this->actingAs($order->seller)->post(route('finance.payout.request', $order))->assertRedirect();
        $this->assertDatabaseHas('order_finances', ['order_id' => $order->id, 'funded_cents' => 3000]);
        $this->assertDatabaseHas('seller_payouts', ['order_id' => $order->id, 'amount_cents' => 9000]);
    }

    public function test_financial_pages_are_scoped_to_the_buyers_own_orders(): void
    {
        $own = $this->collectedOrder($this->user('courier'));
        $other = $this->collectedOrder($this->user('courier'));
        $this->actingAs($own->buyer)->get(route('finance.index'))->assertOk()->assertSee($own->order_number)->assertDontSee($other->order_number);
    }

    public function test_platform_voucher_management_is_commerce_only_and_ignores_submitted_seller_ownership(): void
    {
        $this->actingAs($this->user('ecommerce_admin'))->get(route('admin.vouchers.index'))->assertOk();
        $this->post(route('admin.vouchers.store'), ['code' => 'platform-ten', 'type' => 'fixed', 'value' => 10, 'min_spend' => 0, 'seller_id' => $this->user('seller')->id])->assertRedirect();
        $this->assertDatabaseHas('vouchers', ['code' => 'PLATFORM-TEN', 'seller_id' => null]);
        $this->flushSession();
        $this->actingAs($this->user('logistics_admin'))->post(route('admin.vouchers.store'), ['code' => 'FORBIDDEN', 'type' => 'fixed', 'value' => 10, 'min_spend' => 0])->assertForbidden();
    }

    public function test_legacy_deadlines_receive_a_fresh_grace_period_without_immediate_expiry(): void
    {
        $this->freezeTime();
        $order = $this->order(['created_at' => now()->subMonth()]);
        $this->artisan('orders:initialize-seller-deadlines')->assertSuccessful();
        $this->artisan('orders:initialize-seller-deadlines')->assertSuccessful();
        $this->artisan('orders:expire-seller-deadlines')->assertSuccessful();
        $this->assertSame('PLACED', $order->fresh()->status);
        // The database timestamp columns intentionally store second precision.
        $this->assertSame(now()->addHours(24)->format('Y-m-d H:i:s'), $order->fresh()->seller_response_due_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $order->seller->notifications()->count());
    }

    public function test_cli_provisioned_logistics_admin_can_sign_in_to_its_own_workspace(): void
    {
        $this->artisan('users:create-administrator', ['email' => 'control-tower@example.test', '--role' => 'logistics_admin'])
            ->expectsQuestion('Password (at least 12 characters)', 'Test-admin-password-123!')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'control-tower@example.test', 'canonical_email' => 'control-tower@example.test', 'role' => 'logistics_admin', 'status' => 'approved']);
        $this->post(route('login.post'), ['email' => 'control-tower@example.test', 'password' => 'Test-admin-password-123!'])->assertRedirect(route('logistics-admin.dashboard'));
    }

    public function test_repeated_seller_status_submission_returns_to_current_order_with_feedback(): void
    {
        $order = $this->order(['status' => 'READY_FOR_PICKUP']);
        $this->actingAs($order->seller);

        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'READY_FOR_PICKUP'])
            ->assertRedirectToRoute('seller.orders.show', $order)
            ->assertSessionHasErrors(['status' => 'This order cannot move to that status. Current order status: READY FOR PICKUP. Review the available action below.']);
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('seller.orders.show', $order))
            ->assertSee('Current order status: READY FOR PICKUP.');
        $this->assertSame('READY_FOR_PICKUP', $order->fresh()->status);
        $this->assertSame(0, ParcelTrackingEvent::where('order_id', $order->id)->count());
    }

    public function test_seller_status_json_rejections_still_return_422(): void
    {
        $order = $this->order(['status' => 'READY_FOR_PICKUP']);

        $this->actingAs($order->seller)->patchJson(route('seller.orders.updateStatus', $order), ['status' => 'READY_FOR_PICKUP'])
            ->assertUnprocessable()->assertJsonPath('message', 'This order cannot move to that status.');

        $this->assertSame('READY_FOR_PICKUP', $order->fresh()->status);
        $this->assertSame(0, ParcelTrackingEvent::where('order_id', $order->id)->count());
    }

    public function test_successful_seller_status_update_displays_confirmation_on_order_page(): void
    {
        $order = $this->order(['status' => 'PREPARING']);
        Queue::fake();

        $this->actingAs($order->seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'READY_FOR_PICKUP'])
            ->assertRedirectToRoute('seller.orders.show', $order)
            ->assertSessionHas('success', 'Order status updated to READY FOR PICKUP.');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('seller.orders.show', $order))
            ->assertSee('Order status updated to READY FOR PICKUP.');

        $this->assertSame('READY_FOR_PICKUP', $order->fresh()->status);
        $this->assertSame(1, ParcelTrackingEvent::where('order_id', $order->id)->where('event_type', 'ready_for_pickup')->count());
    }

    public function test_expired_seller_preparation_returns_actionable_error_without_changing_status(): void
    {
        $order = $this->order(['status' => 'PREPARING', 'preparation_due_at' => now()->subMinute()]);

        $this->actingAs($order->seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'READY_FOR_PICKUP'])
            ->assertRedirectToRoute('seller.orders.show', $order)
            ->assertSessionHasErrors(['status' => 'The seller preparation deadline has expired. Current order status: PREPARING. Review the available action below.']);

        $this->assertSame('PREPARING', $order->fresh()->status);
        $this->assertSame(0, ParcelTrackingEvent::where('order_id', $order->id)->count());
    }

    private function receipt(string $reference, array $extra = []): array
    {
        return [...$extra, 'reference' => $reference, 'reason' => 'Receipt checked against external payment.', 'evidence' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')];
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Test', 'last_name' => 'User', 'contact_no' => '09123456789']);
    }

    private function order(array $attributes = []): Order
    {
        return Order::forceCreate([...['order_number' => 'EZC-'.fake()->unique()->numerify('##########'), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'recipient_name' => 'Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => '1 Street', 'subtotal' => 100, 'discount_amount' => 0, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'status' => 'PLACED', 'payment_method' => 'COD'], ...$attributes]);
    }

    private function reservedItem(Order $order): Product
    {
        $product = Product::forceCreate(['user_id' => $order->seller_id, 'name' => 'Item', 'category' => 'Test', 'price' => 100, 'stock' => 0, 'compliance_status' => 'approved']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Item', 'unit_price' => 100, 'quantity' => 1, 'item_total' => 100]);

        return $product;
    }

    private function collectedOrder(User $rider, array $attributes = []): Order
    {
        $order = $this->order(['status' => 'DELIVERED', 'delivery_courier_id' => $rider->id, 'delivered_at' => now()->subDays(8), 'delivery_code_used_at' => now()->subDays(8), 'cod_collected_amount' => 150, ...$attributes]);
        DeliveryAttempt::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => 'delivery-proof.pdf', 'attempted_at' => now()->subDays(8)]);
        DB::transaction(fn () => app(OrderFinancialService::class)->recordCollection($order, $rider));

        return $order;
    }
}
