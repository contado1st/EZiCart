<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\RefundExecution;
use App\Models\ReturnInspectionItem;
use App\Models\SellerPayout;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\DemoWalletFundingService;
use App\Services\FinancialLedgerService;
use App\Services\InventoryRestorationService;
use App\Services\OrderFinancialService;
use App\Services\OrderWalletService;
use App\Services\ReturnInspectionService;
use App\Services\ReturnWorkflowService;
use App\Services\RiderBadgeService;
use App\Services\WalletFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReturnWorkflowRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private User $admin;

    private User $hub;

    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['financial.sandbox_enabled' => true]);
        Notification::fake();
        Storage::fake('private');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $this->admin = $this->user('ecommerce_admin');
        $this->hub = $this->user('sorting_center');
        $this->rider = $this->user('courier');
    }

    public function test_physical_receipt_precedes_mixed_inspection_and_does_not_itself_restock(): void
    {
        [$order, $product, $item] = $this->parcel(['status' => 'RETURN_IN_TRANSIT']);
        $variation = ProductVariation::create(['product_id' => $product->id, 'type' => 'Color', 'value' => 'Blue', 'stock' => 1]);
        $item->update(['variation_id' => $variation->id]);
        $second = $order->items()->create(['product_id' => $product->id, 'product_name' => 'Damaged units', 'quantity' => 2, 'unit_price' => 100, 'item_total' => 200]);
        $badge = app(RiderBadgeService::class)->ensure($this->rider);
        $this->signIn($this->seller)->post(route('seller.orders.confirmReturn', $order), ['rider_badge' => 'EZR:'.$badge])->assertUnprocessable();
        $this->receive($order);
        $this->assertTrue(app(ReturnWorkflowService::class)->receiptConfirmed($order->fresh()));
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('return_inspection_items', 0);
        $this->signIn($this->seller)->post(route('seller.orders.return-inspection', $order), ['quantities' => [$item->id => 2, $second->id => 0], 'reason' => 'Two sellable; remaining units damaged.'])->assertRedirect();
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertEquals(3, $variation->fresh()->stock);
        $this->assertDatabaseHas('return_inspection_items', ['order_item_id' => $item->id, 'expected_quantity' => 3, 'restock_quantity' => 2, 'excluded_quantity' => 1]);
        $this->assertDatabaseHas('return_inspection_items', ['order_item_id' => $second->id, 'restock_quantity' => 0, 'excluded_quantity' => 2]);
        $this->assertNotNull($order->fresh()->inventory_restored_at);
        $this->post(route('seller.orders.return-inspection', $order), ['restock' => 1, 'reason' => 'Duplicate rejected.'])->assertUnprocessable();
        app(InventoryRestorationService::class)->restore($order);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertDatabaseCount('return_inspection_items', 2);
        $this->assertDatabaseCount('refund_executions', 0);
        Notification::assertSentTo([$this->buyer, $this->seller, $this->hub], OrderWorkflowNotification::class, fn ($notification) => $notification->eventType === 'return_inspected');
    }

    #[TestWith(['missing'])]
    #[TestWith(['foreign'])]
    #[TestWith(['negative'])]
    #[TestWith(['fractional'])]
    #[TestWith(['excess'])]
    #[TestWith(['both'])]
    #[TestWith(['blank_reason'])]
    public function test_invalid_inspection_is_atomic(string $case): void
    {
        [$order, $product, $item] = $this->parcel();
        $quantities = [$item->id => 1];
        $data = ['quantities' => $quantities, 'reason' => 'Inspected on receipt.'];
        if ($case === 'missing') {
            $data['quantities'] = [];
        }
        if ($case === 'foreign') {
            $data['quantities'] = [$this->parcel()[2]->id => 1];
        }
        if ($case === 'negative') {
            $data['quantities'][$item->id] = -1;
        }
        if ($case === 'fractional') {
            $data['quantities'][$item->id] = 1.5;
        }
        if ($case === 'excess') {
            $data['quantities'][$item->id] = 4;
        }
        if ($case === 'both') {
            $data['restock'] = 1;
        }
        if ($case === 'blank_reason') {
            $data['reason'] = '       ';
        }
        $this->signIn($this->seller)->postJson(route('seller.orders.return-inspection', $order), $data)->assertUnprocessable();
        $this->assertDatabaseCount('return_inspection_items', 0);
        $this->assertNull($order->fresh()->restock_inspected_at);
        $this->assertSame(2, $product->fresh()->stock);
    }

    #[TestWith([0, 2])]
    #[TestWith([1, 5])]
    public function test_legacy_binary_inspection_is_preserved_with_item_history(int $restock, int $stock): void
    {
        [$order, $product, $item] = $this->parcel();
        $this->signIn($this->seller)->post(route('seller.orders.return-inspection', $order), ['restock' => $restock, 'reason' => 'Legacy inspection result.'])->assertRedirect();
        $this->assertSame($stock, $product->fresh()->stock);
        $this->assertDatabaseHas('return_inspection_items', ['order_item_id' => $item->id, 'restock_quantity' => $restock * 3]);
        if (! $restock) {
            $this->assertNull($order->fresh()->inventory_restored_at);
            $this->expectException(HttpException::class);
            app(InventoryRestorationService::class)->restore($order);
        }
    }

    public function test_missing_catalog_units_can_be_excluded_but_cannot_be_replaced_with_other_stock(): void
    {
        [$order, $product, $item] = $this->parcel();
        $item->update(['product_id' => null]);
        $this->signIn($this->seller)->postJson(route('seller.orders.return-inspection', $order), ['quantities' => [$item->id => 1], 'reason' => 'Original item unavailable.'])->assertUnprocessable();
        $this->postJson(route('seller.orders.return-inspection', $order), ['quantities' => [$item->id => 0], 'reason' => 'Original item unavailable; excluded.'])->assertRedirect();
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseHas('return_inspection_items', ['order_item_id' => $item->id, 'excluded_quantity' => 3]);
    }

    public function test_recorded_inspection_is_immutable_and_reason_is_scoped_to_seller_and_commerce(): void
    {
        [$order, , $item] = $this->parcel();
        app(ReturnInspectionService::class)->inspect($order, $this->seller, ['quantities' => [$item->id => 0], 'reason' => '  Private inspection diagnosis.  ']);
        $line = ReturnInspectionItem::firstOrFail();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $line->update(['restock_quantity' => 3]) : $line->delete();
                $this->fail('Inspection history was changed.');
            } catch (\LogicException) {
                $this->assertSame(0, $line->fresh()->restock_quantity);
            }
        }
        $this->signIn($this->seller)->get(route('returns.workflow', $order))->assertOk()->assertSee('Private inspection diagnosis.');
        $this->signIn($this->buyer)->get(route('returns.workflow', $order))->assertOk()->assertDontSee('Private inspection diagnosis.')->assertDontSee('name="quantities[', false);
        $this->signIn($this->hub)->get(route('returns.workflow', $order))->assertOk()->assertDontSee('Private inspection diagnosis.');
    }

    #[TestWith(['seller'])]
    #[TestWith(['buyer'])]
    #[TestWith(['sorting_center'])]
    #[TestWith(['courier'])]
    public function test_unrelated_accounts_cannot_view_or_inspect_the_return(string $role): void
    {
        [$order] = $this->parcel();
        $this->signIn($this->user($role))->get(route('returns.workflow', $order))->assertNotFound();
        $this->post(route('seller.orders.return-inspection', $order), ['restock' => 1, 'reason' => 'Unrelated inspection denied.'])->assertForbidden();
        $this->assertDatabaseCount('return_inspection_items', 0);
    }

    public function test_page_read_is_private_and_does_not_create_financial_or_inspection_records(): void
    {
        [$order] = $this->parcel();
        $before = $this->counts();
        $response = $this->signIn($this->admin)->get(route('returns.workflow', $order))->assertOk()->assertDontSee('Authorize RTS wallet refund');
        $response->assertSee('dashboard-workspace');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $this->counts());
        $order->forceFill(['restock_inspected_at' => now(), 'restock_inspected_by' => $this->seller->id, 'restock_approved' => false])->save();
        $this->get(route('returns.workflow', $order))->assertOk()->assertSee('Legacy inspection recorded without a per-item breakdown');
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_wallet_rts_authorization_holds_existing_payout_and_execution_credits_exactly_once(bool $internalDemo): void
    {
        [$order, $product, $item] = $this->paidReturn($internalDemo);
        $payout = SellerPayout::create(['order_id' => $order->id, 'seller_id' => $this->seller->id, 'amount_cents' => 29000, 'status' => 'REQUESTED', 'requested_by' => $this->seller->id]);
        app(ReturnInspectionService::class)->inspect($order, $this->seller, ['quantities' => [$item->id => 0], 'reason' => 'Damaged units excluded from stock.']);
        $ledger = app(FinancialLedgerService::class);
        $before = LedgerTransaction::count();
        $this->signIn($this->admin)->get(route('finance.index'))->assertOk()->assertSee(route('returns.workflow', $order), false);
        $this->get(route('returns.workflow', $order))->assertOk()->assertSee('Authorize RTS wallet refund');
        $this->post(route('returns.authorize-wallet', $order), ['reason' => 'Verified undelivered wallet return.'])->assertRedirect();
        $refund = RefundExecution::firstOrFail();
        $this->assertNull($refund->dispute_id);
        $this->assertEquals($order->id, $refund->rts_order_id);
        $this->assertSame('AUTHORIZED', $refund->status);
        $this->assertSame('ON_HOLD', $payout->fresh()->status);
        $this->assertSame($before, LedgerTransaction::count());
        $this->assertSame(5000, $ledger->wallet($this->buyer)->balance_cents);
        $this->assertDatabaseCount('disputes', 0);
        $auditCount = DB::table('administrative_audits')->count();
        $this->post(route('returns.authorize-wallet', $order), ['reason' => 'Repeated authorization request.'])->assertRedirect();
        $this->assertDatabaseCount('refund_executions', 1);
        $this->assertSame($auditCount, DB::table('administrative_audits')->count());
        $this->get(route('returns.workflow', $order))->assertOk()->assertSee('Credit approved wallet refund')->assertDontSee('Authorize RTS wallet refund');
        $this->post(route('finance.refund.wallet', $refund), ['reason' => 'Execute reviewed full wallet refund.'])->assertRedirect();
        $this->postJson(route('finance.refund.wallet', $refund), ['reason' => 'Duplicate execution denied.'])->assertUnprocessable();
        $this->assertSame(40000, $ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame(0, $ledger->wallet($this->seller, 'pending')->balance_cents);
        $this->assertSame(0, $ledger->wallet($this->seller)->balance_cents);
        $this->assertSame(1, LedgerTransaction::where('type', 'refund_credit')->count());
        $this->assertSame('EXECUTED', $refund->fresh()->status);
        $this->assertNull($order->finance()->first()->collected_cents);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertTrue($ledger->reconcile()['balanced']);
        $this->assertArrayNotHasKey('rts_order_id', $refund->toArray());
        $this->assertArrayNotHasKey('authorization_reason', $refund->toArray());
        $this->signIn($this->seller)->postJson(route('finance.payout.request', $order))->assertUnprocessable();
        $this->signIn($this->buyer)->get(route('buyer.orders.show', $order))->assertOk()->assertSee('View return progress');
    }

    #[TestWith(['receipt'])]
    #[TestWith(['cod'])]
    #[TestWith(['external'])]
    #[TestWith(['funding'])]
    #[TestWith(['amount'])]
    #[TestWith(['delivered'])]
    #[TestWith(['frozen'])]
    #[TestWith(['settled'])]
    #[TestWith(['partial_refund'])]
    public function test_rts_authorization_rejects_unverified_or_conflicting_state_without_posting(string $case): void
    {
        [$order] = $this->paidReturn();
        if ($case === 'receipt') {
            DB::table('scan_events')->where('order_id', $order->id)->where('station', 'seller_return_receipt')->delete();
        }
        if ($case === 'cod') {
            $order->forceFill(['payment_method' => 'COD'])->save();
        }
        if ($case === 'external') {
            $order->forceFill(['payment_method' => 'GCASH'])->save();
        }
        if ($case === 'funding') {
            $order->finance()->update(['ledger_funded_at' => null]);
        }
        if ($case === 'amount') {
            $order->finance()->update(['due_cents' => 1]);
        }
        if ($case === 'delivered') {
            $order->forceFill(['delivered_at' => now()->subDay()])->save();
        }
        if ($case === 'frozen') {
            app(FinancialLedgerService::class)->wallet($this->buyer)->update(['frozen' => true]);
        }
        if ($case === 'settled') {
            SellerPayout::create(['order_id' => $order->id, 'seller_id' => $this->seller->id, 'amount_cents' => 29000, 'status' => 'SETTLED', 'requested_by' => $this->seller->id]);
        }
        if ($case === 'partial_refund') {
            RefundExecution::create(['order_id' => $order->id, 'rts_order_id' => $order->id, 'amount_cents' => 1, 'status' => 'AUTHORIZED', 'authorized_by' => $this->admin->id]);
        }
        $before = $this->counts();
        $this->signIn($this->admin)->postJson(route('returns.authorize-wallet', $order), ['reason' => 'Invalid state must require review.'])->assertUnprocessable();
        $this->assertSame($before, $this->counts());
    }

    public function test_rts_execution_rechecks_changed_payment_hold_and_receipt(): void
    {
        [$order] = $this->paidReturn();
        $refund = app(OrderFinancialService::class)->authorizeRtsWalletRefund($order, $this->admin, 'Verified original receipt and funding.');
        $wallet = app(FinancialLedgerService::class)->wallet($this->buyer);
        $wallet->update(['frozen' => true]);
        $before = LedgerTransaction::count();
        $this->signIn($this->admin)->postJson(route('finance.refund.wallet', $refund), ['reason' => 'Account changed since authorization.'])->assertUnprocessable();
        $wallet->update(['frozen' => false]);
        DB::table('scan_events')->where('order_id', $order->id)->where('station', 'seller_return_receipt')->delete();
        $this->postJson(route('finance.refund.wallet', $refund), ['reason' => 'Receipt evidence no longer available.'])->assertUnprocessable();
        $this->assertSame($before, LedgerTransaction::count());
        $this->assertSame('AUTHORIZED', $refund->fresh()->status);
    }

    public function test_only_commerce_admin_can_authorize_and_old_dispute_writer_cannot_duplicate_rts(): void
    {
        [$order] = $this->paidReturn();
        foreach ([$this->seller, $this->buyer, $this->hub, $this->user('logistics_admin')] as $actor) {
            $this->signIn($actor)->postJson(route('returns.authorize-wallet', $order), ['reason' => 'Wrong authorization role.'])->assertForbidden();
        }
        $refund = app(OrderFinancialService::class)->authorizeRtsWalletRefund($order, $this->admin, 'Verified undelivered return.');
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'reason' => 'Historical dispute decision', 'description' => 'Existing dispute fixture.', 'status' => 'REFUND_APPROVED']);
        try {
            app(OrderFinancialService::class)->authorizeRefund($dispute, $this->admin);
            $this->fail('Second authorization allowed.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('refund_executions', 1);
        $this->assertEquals($refund->id, app(OrderFinancialService::class)->authorizeRtsWalletRefund($order, $this->admin, 'Existing authorization retained.')->id);
    }

    public function test_existing_full_dispute_authorization_is_reused_without_fabricating_another_record(): void
    {
        [$order] = $this->paidReturn();
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'reason' => 'Historical decision', 'description' => 'Existing dispute fixture.', 'status' => 'REFUND_APPROVED']);
        $original = app(OrderFinancialService::class)->authorizeRefund($dispute, $this->admin);
        $repeat = app(OrderFinancialService::class)->authorizeRtsWalletRefund($order, $this->admin, 'Do not duplicate existing authorization.');
        $this->assertSame($original->id, $repeat->id);
        $this->assertNull($repeat->rts_order_id);
        $this->assertDatabaseCount('refund_executions', 1);
    }

    public function test_inspection_rejects_a_variation_from_another_product_without_changing_either_stock(): void
    {
        [$order, $product, $item] = $this->parcel();
        $otherProduct = $this->parcel()[1];
        $option = ProductVariation::create(['product_id' => $otherProduct->id, 'type' => 'Color', 'value' => 'Red', 'stock' => 8]);
        $item->update(['variation_id' => $option->id]);
        $this->signIn($this->seller)->postJson(route('seller.orders.return-inspection', $order), ['quantities' => [$item->id => 1], 'reason' => 'Invalid historical option linkage.'])->assertUnprocessable()->assertJsonValidationErrors('quantities.'.$item->id);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(2, $otherProduct->fresh()->stock);
        $this->assertEquals(8, $option->fresh()->stock);
        $this->assertDatabaseCount('return_inspection_items', 0);
    }

    public function test_inspection_rechecks_the_sellers_current_status_instead_of_trusting_a_stale_actor(): void
    {
        [$order, $product] = $this->parcel();
        User::whereKey($this->seller->id)->update(['status' => 'suspended']);
        try {
            app(ReturnInspectionService::class)->inspect($order, $this->seller, ['restock' => 1, 'reason' => 'Stale approved seller instance.']);
            $this->fail('Suspended seller inspection accepted.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('return_inspection_items', 0);
    }

    public function test_guests_cannot_view_or_submit_a_return_workflow(): void
    {
        [$order] = $this->parcel();
        $this->get(route('returns.workflow', $order))->assertRedirect();
        $this->postJson(route('returns.authorize-wallet', $order), ['reason' => 'Guest authorization rejected.'])->assertUnauthorized();
        $this->assertDatabaseCount('refund_executions', 0);
    }

    public function test_long_inspection_keeps_all_quantities_in_one_atomic_form_in_groups_of_25(): void
    {
        [$order, $product] = $this->parcel();
        for ($i = 0; $i < 25; $i++) {
            $order->items()->create(['product_id' => $product->id, 'product_name' => 'Unit '.$i, 'quantity' => 1, 'unit_price' => 100, 'item_total' => 100]);
        }
        $response = $this->signIn($this->seller)->get(route('returns.workflow', $order))->assertOk()->assertSee('Items 1–25 of 26')->assertSee('Items 26–26 of 26');
        $this->assertSame(26, substr_count($response->getContent(), 'name="quantities['));
        $data = $order->items()->get()->mapWithKeys(fn ($item) => [$item->id => 0])->all();
        $data[$order->items()->latest('id')->value('id')] = 1;
        $this->post(route('seller.orders.return-inspection', $order), ['quantities' => $data, 'reason' => '<script>alert("inspection")</script>'])->assertRedirect();
        $this->assertDatabaseCount('return_inspection_items', 26);
        $this->assertSame(3, $product->fresh()->stock);
        $this->get(route('returns.workflow', $order))->assertOk()->assertSee('Items 1–25 of 26')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("inspection")</script>', false);
    }

    private function counts(): array
    {
        return collect(['order_finances', 'financial_accounts', 'ledger_transactions', 'refund_executions', 'return_inspection_items', 'administrative_audits'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    private function paidReturn(bool $internalDemo = false): array
    {
        if ($internalDemo) {
            $funding = app(DemoWalletFundingService::class);
            $intent = $funding->request($this->buyer, 40000, (string) Str::uuid(), 'demo_credit', ['reason' => 'Internal demo RTS funding.']);
            $funding->review($intent, $this->admin, 'approve', 'Independent RTS demo credit review.');
        } else {
            $funding = app(WalletFundingService::class);
            $funding->simulate($funding->create($this->buyer, 40000, (string) Str::uuid()), $this->admin, 'paid');
        }
        $parcel = $this->parcel(['status' => 'RETURN_IN_TRANSIT', 'payment_method' => 'WALLET', 'financial_version' => 1]);
        app(OrderWalletService::class)->pay($parcel[0], $this->buyer);
        $this->receive($parcel[0]);
        $parcel[0]->refresh();

        return $parcel;
    }

    private function receive(Order $order): void
    {
        $this->signIn($this->rider)->post(route('courier.orders.confirmReturnDelivery', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'method' => 'manual'])->assertRedirect();
        $badge = app(RiderBadgeService::class)->ensure($this->rider);
        $this->signIn($this->seller)->post(route('seller.orders.confirmReturn', $order), ['rider_badge' => 'EZR:WRONG', 'method' => 'manual'])->assertSessionHasErrors('rider_badge');
        $this->post(route('seller.orders.confirmReturn', $order), ['rider_badge' => 'EZR:'.$badge, 'method' => 'manual'])->assertRedirect();
    }

    private function parcel(array $extra = []): array
    {
        $order = Order::forceCreate($extra + ['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'sorting_center_id' => $this->hub->id, 'delivery_courier_id' => $this->rider->id, 'order_number' => 'RET-'.Str::random(10), 'status' => 'RETURNED_TO_SELLER', 'subtotal' => 300, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 350, 'payment_method' => 'COD', 'recipient_name' => 'Return buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Test street']);
        $product = Product::forceCreate(['user_id' => $this->seller->id, 'name' => 'Returned product', 'price' => 100, 'stock' => 2, 'category' => 'Home and Garden', 'weight_grams' => 1000, 'compliance_status' => 'approved']);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => 'Purchased snapshot', 'quantity' => 3, 'unit_price' => 100, 'item_total' => 300]);

        return [$order->refresh(), $product, $item];
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Return', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
}
