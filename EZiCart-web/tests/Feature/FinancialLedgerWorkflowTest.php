<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DeliveryRateVersion;
use App\Models\Dispute;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\OrderFinance;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\ShipmentFinancial;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use App\Services\DeliveryCodeService;
use App\Services\DeliveryPricingService;
use App\Services\DeliveryRateManagementService;
use App\Services\FinancialLedgerService;
use App\Services\LogisticsSettlementService;
use App\Services\OrderFinancialService;
use App\Services\OrderTransitionService;
use App\Services\OrderWalletService;
use App\Services\ParcelTransferService;
use App\Services\PlacePurchaseService;
use App\Services\RemittanceBatchService;
use App\Services\RiderBadgeService;
use App\Services\ShipmentAdjustmentService;
use App\Services\VoucherFundingService;
use App\Services\WalletFundingService;
use App\Services\WalletWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialLedgerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private User $admin;

    private FinancialLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['financial.sandbox_enabled' => true, 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        Notification::fake();
        Storage::fake('private');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $this->admin = $this->user('ecommerce_admin');
        $this->ledger = app(FinancialLedgerService::class);
    }

    public function test_signed_funding_replay_failure_and_reversal_keep_balances_reconciled(): void
    {
        $funding = app(WalletFundingService::class);
        $intent = $funding->create($this->buyer, 20000, (string) Str::uuid());
        $body = json_encode(['event_id' => 'event-1', 'intent' => $intent->reference, 'amount_cents' => 20000, 'currency' => 'PHP', 'status' => 'paid', 'external_reference' => 'provider-1']);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $funding->secret());
        $this->postJson(route('finance.sandbox.webhook'), json_decode($body, true), ['X-EZiCart-Signature' => 'bad', 'X-EZiCart-Timestamp' => $timestamp])->assertUnauthorized();
        $funding->callback($body, $signature, $timestamp);
        $funding->callback($body, $signature, $timestamp);
        $this->assertSame(20000, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame(1, LedgerTransaction::where('type', 'sandbox_topup')->count());
        $funding->simulate($intent->refresh(), $this->admin, 'reversed');
        $this->assertSame(0, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame('REVERSED', $intent->refresh()->status);
        $failed = $funding->create($this->buyer, 10000, (string) Str::uuid());
        $funding->simulate($failed, $this->admin, 'failed');
        $this->assertSame('FAILED', $failed->refresh()->status);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_spending_is_idempotent_and_cannot_overdraw_or_edit_posted_entries(): void
    {
        $this->fund($this->buyer, 10000);
        $from = $this->ledger->wallet($this->buyer);
        $to = $this->ledger->treasury();
        $first = $this->ledger->transfer('test-spend', 'test_transfer', $from, $to, 7500, $this->buyer);
        $repeat = $this->ledger->transfer('test-spend', 'test_transfer', $from, $to, 7500, $this->buyer);
        $this->assertSame($first->id, $repeat->id);
        try {
            $this->ledger->transfer('second-spend', 'test_transfer', $from, $to, 7500, $this->buyer);
            $this->fail('Overdraft allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('balance', $exception->errors());
        }
        try {
            $this->ledger->transfer('test-spend', 'test_transfer', $from, $to, 500, $this->buyer);
            $this->fail('Conflicting idempotency key allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        try {
            $first->update(['type' => 'edited']);
            $this->fail('Ledger edit allowed.');
        } catch (\LogicException) {
            $this->assertSame('test_transfer', $first->fresh()->type);
        }
        try {
            LedgerEntry::where('transaction_id', $first->id)->firstOrFail()->delete();
            $this->fail('Ledger deletion allowed.');
        } catch (\LogicException) {
            $this->assertSame(2, LedgerEntry::where('transaction_id', $first->id)->count());
        }
        $this->assertSame(2500, $from->refresh()->balance_cents);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_wallet_order_is_atomic_retry_safe_and_refunded_on_approved_cancellation(): void
    {
        $this->fund($this->buyer, 20000);
        $product = $this->product();
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($cart);
        $token = (string) Str::uuid();
        $service = app(PlacePurchaseService::class);
        $purchase = $service->place($this->buyer, $token, 'request', $cart, $quote['hash'], null, $this->address(), 'WALLET');
        $service->place($this->buyer, $token, 'request', $cart, $quote['hash'], null, $this->address(), 'WALLET');
        $order = $purchase->orders()->firstOrFail();
        $this->assertSame('WALLET', $order->payment_method);
        $this->assertSame(15000, $order->wallet_paid_cents);
        $this->assertSame(5000, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame(9000, $this->ledger->wallet($this->seller, 'pending')->balance_cents);
        $this->assertSame(0, $this->ledger->wallet($this->seller)->balance_cents);
        $this->assertNull($order->finance->collected_cents);
        $this->assertSame(9, $product->refresh()->stock);
        $this->signIn($this->buyer)->get(route('finance.index'))->assertOk()
            ->assertSee('Wallet payment recorded')->assertSee('Not applicable (prepaid)');
        app(OrderTransitionService::class)->transition($order, $this->admin, OrderStatus::Cancelled, 'admin_cancelled', null, 'Demonstration cancellation approved.');
        $this->assertSame(20000, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame(0, $this->ledger->wallet($this->seller, 'pending')->balance_cents);
        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame('VOID', $order->finance()->first()->remittance_status);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_insufficient_wallet_payment_rolls_back_stock_purchase_and_promotion_consumption(): void
    {
        $this->fund($this->seller, 4000);
        $voucher = Voucher::create(['seller_id' => $this->seller->id, 'code' => 'FUNDED', 'type' => 'fixed', 'value' => 20, 'usage_limit' => 2, 'is_active' => true]);
        app(VoucherFundingService::class)->reserve($voucher, $this->seller);
        $product = $this->product();
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($cart, $voucher);
        try {
            app(PlacePurchaseService::class)->place($this->buyer, (string) Str::uuid(), 'request', $cart, $quote['hash'], $voucher->id, $this->address(), 'WALLET');
            $this->fail('Unfunded wallet order was placed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('balance', $exception->errors());
        }
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('voucher_redemptions', 0);
        $this->assertSame(0, $voucher->budget()->first()->consumed_cents);
        $this->assertSame(10, $product->refresh()->stock);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_seller_promotion_is_reserved_once_and_not_deducted_again_from_seller_proceeds(): void
    {
        $this->fund($this->seller, 10000);
        $this->fund($this->buyer, 20000);
        $voucher = Voucher::create(['seller_id' => $this->seller->id, 'code' => 'SELLER20', 'type' => 'fixed', 'value' => 20, 'usage_limit' => 2, 'is_active' => true]);
        app(VoucherFundingService::class)->reserve($voucher, $this->seller);
        $this->assertSame(6000, $this->ledger->wallet($this->seller)->balance_cents);
        $order = $this->buy($this->product(), $voucher);
        $this->assertSame(9200, $order->finance->seller_payable_cents);
        $this->assertSame(2000, $order->seller_promo_funded_cents);
        $this->assertSame(2000, $voucher->budget()->first()->consumed_cents);
        $order->forceFill(['status' => 'COMPLETED', 'delivered_at' => now()->subDays(8)])->save();
        $finances = app(OrderFinancialService::class);
        $payout = $finances->requestPayout($order, $this->seller);
        $finances->approvePayout($payout, $this->admin, 'Checked eligible settlement.');
        $finances->settleToWallet($payout, $this->admin, 'Credit eligible proceeds.');
        $this->assertSame(15200, $this->ledger->wallet($this->seller)->balance_cents);
        app(VoucherFundingService::class)->release($voucher, $this->seller);
        $this->assertSame(17200, $this->ledger->wallet($this->seller)->balance_cents);
        $this->assertSame('SETTLED', $payout->refresh()->status);
        $this->assertSame('UNCONFIGURED', ShipmentFinancial::where('order_id', $order->id)->first()->settlement_snapshot['status']);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_platform_shipping_voucher_uses_treasury_and_preserves_seller_payable(): void
    {
        $this->fund($this->admin, 10000, true);
        $this->fund($this->buyer, 20000);
        $voucher = Voucher::create(['code' => 'SHIP50', 'type' => 'fixed', 'value' => 50, 'discount_target' => 'shipping', 'usage_limit' => 1, 'is_active' => true]);
        app(VoucherFundingService::class)->reserve($voucher, $this->admin);
        $order = $this->buy($this->product(), $voucher);
        $this->assertSame(10000, $order->wallet_paid_cents);
        $this->assertSame(9000, $order->finance->seller_payable_cents);
        $this->assertSame(5000, $order->finance->platform_subsidy_cents);
        $this->assertSame(5000, $order->finance->funded_cents);
        $this->assertSame(5000, $this->ledger->treasury()->balance_cents);
        $this->assertSame(0, $this->ledger->wallet($this->seller)->balance_cents);
        $this->assertSame(5000, ShipmentFinancial::where('order_id', $order->id)->first()->platform_shipping_cents);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_batch_discrepancy_requires_independent_receipt_investigation_and_exact_resolution(): void
    {
        $hub = $this->user('sorting_center');
        $rider = $this->user('courier');
        $logisticsAdmin = $this->user('logistics_admin');
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $order = $this->order(['sorting_center_id' => $hub->id, 'delivery_courier_id' => $rider->id, 'status' => 'DELIVERED']);
            $finance = app(OrderFinancialService::class)->initialize($order);
            $finance->update(['collector_id' => $rider->id, 'collected_cents' => 15000, 'remittance_status' => 'COLLECTED']);
            $ids[] = $finance->id;
        }
        Storage::disk('private')->put('batch.pdf', 'test evidence');
        $service = app(RemittanceBatchService::class);
        $batch = $service->submit($rider, $hub, $ids, 29900, 'batch.pdf', 'Cash submission declaration.');
        $service->receive($batch, $hub, 29900, 'batch.pdf', 'Cash counted at receiving desk.');
        $this->assertSame(-100, $batch->refresh()->discrepancy_cents);
        $this->assertSame('DISCREPANCY', $batch->status);
        $service->investigate($batch, $logisticsAdmin, 'Investigate missing cash.');
        $service->reconcile($batch, $logisticsAdmin, 30000, 'batch.pdf', 'Additional cash received and counted.');
        $this->assertSame('VERIFIED', $batch->refresh()->status);
        $this->assertSame(29900, $batch->submitted_cents);
        $this->assertSame(30000, $batch->received_cents);
        $this->assertSame(2, OrderFinance::whereIn('id', $ids)->where('remittance_status', 'VERIFIED')->count());
        $this->assertSame(0, $this->ledger->wallet($rider)->balance_cents);
        $this->assertDatabaseCount('remittance_batch_events', 5);
        try {
            $service->submit($rider, $hub, $ids, 30000, 'batch.pdf', 'Duplicate submission.');
            $this->fail('Duplicate batch accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        try {
            $service->receive($batch, $rider, 30000, 'batch.pdf', 'Self verification.');
            $this->fail('Self verification allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_wallet_withdrawal_holds_funds_until_authorized_review_and_sandbox_execution(): void
    {
        $this->fund($this->buyer, 20000);
        $service = app(WalletWithdrawalService::class);
        $withdrawal = $service->request($this->buyer, 15000, 'Sandbox destination 123', (string) Str::uuid());
        $this->assertSame(5000, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame(15000, $this->ledger->wallet($this->buyer, 'held')->balance_cents);
        $service->review($withdrawal, $this->admin, 'approve', 'Identity and available balance reviewed.');
        $service->settle($withdrawal, $this->admin, 'SANDBOX-PAYOUT-1', 'Mock disbursement completed.');
        $this->assertSame('SETTLED', $withdrawal->refresh()->status);
        $this->assertSame(0, $this->ledger->wallet($this->buyer, 'held')->balance_cents);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_versioned_pricing_uses_approved_effective_rates_and_dimensional_weight(): void
    {
        $creator = $this->user('logistics_admin');
        $approver = $this->user('logistics_admin');
        $service = app(DeliveryRateManagementService::class);
        foreach (['same_barangay', 'same_municipality', 'inter_municipality', 'inter_province'] as $route) {
            $rate = $service->create($creator, ['name' => 'Test '.$route, 'route_class' => $route, 'sandbox' => true, 'effective_at' => now()->subMinute(), 'volumetric_divisor' => 6000, 'rules' => ['weight_brackets' => [['max_grams' => 1000, 'components' => ['last_mile' => 2000, 'cod' => 100]], ['max_grams' => 5000, 'components' => ['last_mile' => 4000, 'cod' => 100]]]]]);
            $service->approve($rate, $approver, 'Approved sandbox test rate.');
        }
        $service->activate($approver, 'Activate test tariff coverage.');
        $product = $this->product(['weight_grams' => 500, 'length_mm' => 200, 'width_mm' => 200, 'height_mm' => 200]);
        $quote = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'quantity' => 1]], null, false, ['address' => $this->address(), 'payment_method' => 'WALLET']);
        $snapshot = $quote['groups'][$this->seller->id]['shipping_snapshot'];
        $this->assertSame(1334, $snapshot['chargeable_grams']);
        $this->assertSame(4000, $snapshot['total_cents']);
        $this->assertSame(0, $snapshot['components']['cod']);
        $this->assertSame('same_barangay', $snapshot['route_class']);
        $rate = DeliveryRateVersion::findOrFail($snapshot['rate_version_id']);
        $cash = app(DeliveryPricingService::class)->calculate($rate, 500, [200, 200, 200], 'COD');
        $this->assertSame(4100, $cash['total_cents']);
    }

    public function test_financial_pages_and_statements_are_role_scoped_and_render_successfully(): void
    {
        $this->fund($this->buyer, 10000);
        $this->signIn($this->buyer)->get(route('finance.wallet'))->assertOk()->assertSee('Sandbox accounting credits');
        $this->get(route('finance.shipments'))->assertOk();
        $this->get(route('finance.rates'))->assertForbidden();
        $this->get(route('finance.batches'))->assertForbidden();
        $this->get(route('finance.statements', 'remittances'))->assertForbidden();
        $this->get(route('finance.statements', 'wallet'))->assertOk()->assertDownload();
        $this->signIn($this->user('logistics_admin'))->get(route('finance.rates'))->assertOk();
        $this->get(route('finance.batches'))->assertOk();
        $this->signIn($this->user('sorting_center'))->get(route('finance.batches'))->assertOk();
    }

    public function test_prepaid_order_completes_pickup_hub_transfer_delivery_and_cost_based_settlement_without_cod(): void
    {
        $this->withoutExceptionHandling();
        $this->approveExampleRates();
        $source = $this->user('sorting_center');
        $destination = $this->user('sorting_center');
        $destination->forceFill(['municipality' => 'Santa Cruz'])->save();
        $sourceArea = Area::create(['name' => 'Origin', 'code' => 'FIN-ORIGIN', 'is_active' => true, 'sorting_center_id' => $source->id]);
        $area = Area::create(['name' => 'Destination', 'code' => 'FIN-DEST', 'is_active' => true, 'sorting_center_id' => $destination->id]);
        foreach ([[$sourceArea, 'Majayjay'], [$area, 'Santa Cruz']] as [$mappedArea, $town]) {
            AreaMunicipality::create(['area_id' => $mappedArea->id, 'province' => 'Laguna', 'municipality' => $town, 'province_normalized' => 'laguna', 'municipality_normalized' => mb_strtolower($town)]);
        }
        $pickup = $this->rider($source);
        $pickup->serviceAreas()->attach($sourceArea->id, ['is_active' => true, 'is_primary' => true]);
        $driver = $this->rider($source);
        $driver->forceFill(['hub_transfer_only' => true])->save();
        $delivery = $this->rider($destination);
        $delivery->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        $logisticsAdmin = $this->user('logistics_admin');
        $this->fund($this->buyer, 30000);
        $product = $this->product();
        $address = [...$this->address(), 'municipality' => 'Santa Cruz', 'destination_area_id' => $area->id];
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($cart, null, false, ['address' => $address, 'payment_method' => 'WALLET']);
        $order = app(PlacePurchaseService::class)->place($this->buyer, (string) Str::uuid(), 'journey', $cart, $quote['hash'], null, $address, 'WALLET')->orders()->firstOrFail();
        $this->signIn($this->seller)->patch(route('orders.weight', $order), ['parcel_weight_kg' => '1.000', 'reason' => 'Packed parcel weighed.'])->assertRedirect()->assertSessionHasNoErrors();
        foreach (['CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'] as $status) {
            $this->patch(route('seller.orders.updateStatus', $order), ['status' => $status])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post(route('seller.orders.schedulePickup', $order), ['pickup_scheduled_for' => now()->addHour()->toDateTimeString(), 'pickup_window' => 'Morning pickup'])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($source)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $pickup->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($pickup)->post(route('courier.orders.claim', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('courier.orders.confirmPickup', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($this->seller)->post(route('seller.orders.confirmHandover', $order), ['rider_badge' => 'EZR:'.app(RiderBadgeService::class)->ensure($pickup)])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($pickup)->post(route('courier.orders.confirmPickup', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($source)->post(route('logistics.orders.receive', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.sort', $order))->assertRedirect()->assertSessionHasNoErrors();
        $transfer = app(ParcelTransferService::class)->plan($order->fresh(), $logisticsAdmin, $destination, 'Destination routing approved.');
        $this->post(route('parcel-transfers.release', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'transport_reference' => 'FIN-TRANSPORT', 'carrier_rider_id' => $driver->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.sort', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $delivery->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($delivery)->post(route('courier.orders.accept-assignment', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($destination)->post(route('logistics.orders.releaseToRider', $order), ['rider_badge' => 'EZR:'.app(RiderBadgeService::class)->ensure($delivery), 'parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($delivery)->post(route('courier.orders.startDelivery', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('courier.orders.completeDelivery', $order), ['recipient_confirmation' => 'Finance buyer', 'delivery_code' => app(DeliveryCodeService::class)->reveal($order->fresh()), 'proof_file' => UploadedFile::fake()->image('prepaid-delivery.jpg')])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($this->buyer)->post(route('buyer.orders.confirm', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->cod_collected_amount);
        $this->assertNull($order->finance()->first()->collector_id);
        $this->travel(8)->days();
        $finances = app(OrderFinancialService::class);
        $payout = $finances->requestPayout($order->fresh(), $this->seller);
        $finances->approvePayout($payout, $this->admin, 'Matured verified settlement.');
        $finances->settleToWallet($payout, $this->admin, 'Credit all eligible beneficiaries.');
        $this->assertSame(2000, $this->ledger->wallet($pickup)->balance_cents);
        $this->assertSame(2500, $this->ledger->wallet($driver)->balance_cents);
        $this->assertSame(2500, $this->ledger->wallet($delivery)->balance_cents);
        $this->assertSame(800, $this->ledger->wallet($source)->balance_cents);
        $this->assertSame(800, $this->ledger->wallet($destination)->balance_cents);
        $this->assertSame(0, $this->ledger->escrow($order->id)->balance_cents);
        $this->assertSame('SETTLED', ShipmentFinancial::where('order_id', $order->id)->first()->settlement_snapshot['status']);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_refunded_return_preserves_buyer_refund_and_requires_treasury_funding_for_logistics_costs(): void
    {
        $this->approveExampleRates();
        $this->fund($this->buyer, 30000);
        $order = $this->buy($this->product());
        $hub = $this->user('sorting_center');
        $rider = $this->rider($hub);
        $order->forceFill(['status' => 'RETURNED_TO_SELLER', 'pickup_courier_id' => $rider->id, 'delivery_courier_id' => $rider->id, 'sorting_center_id' => $hub->id, 'picked_up_at' => now()->subDays(2), 'delivered_at' => now()->subDay(), 'return_handed_to_seller_at' => now()])->save();
        $order->deliveryAttempts()->create(['rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => 'proof.jpg', 'attempted_at' => now()->subDay()]);
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $hub->id, 'event_type' => 'hub_received', 'status' => 'AT_SORTING_CENTER']);
        $dispute = Dispute::forceCreate(['order_id' => $order->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'description' => 'Returned defective goods.', 'reason' => 'Damaged item', 'status' => 'REFUND_APPROVED']);
        $refund = app(OrderFinancialService::class)->authorizeRefund($dispute, $this->admin);
        app(OrderWalletService::class)->refund($refund, $this->admin, 'Approved full wallet refund.');
        $this->assertSame(30000, $this->ledger->wallet($this->buyer)->balance_cents);
        $logisticsAdmin = $this->user('logistics_admin');
        Storage::disk('private')->put('return-cost.pdf', 'Evidence');
        $adjustments = app(ShipmentAdjustmentService::class);
        $request = $adjustments->proposeOperatingFunds($order, $logisticsAdmin, 0, 'return-cost.pdf', 'Evidence of completed transport and return.', true);
        try {
            $adjustments->approve($request, $this->admin, 'Review return funding.');
            $this->fail('Unfunded treasury approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('balance', $exception->errors());
        }
        $this->fund($this->admin, 20000, true);
        $adjustments->approve($request, $this->admin, 'Review and fund return costs.');
        app(LogisticsSettlementService::class)->settleReturned($order, $logisticsAdmin);
        $this->assertSame(30000, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->assertSame(6500, $this->ledger->wallet($rider)->balance_cents);
        $this->assertSame(800, $this->ledger->wallet($hub)->balance_cents);
        $snapshot = ShipmentFinancial::where('order_id', $order->id)->first();
        $this->assertTrue($snapshot->settlement_snapshot['original_shipping_refunded']);
        $this->assertSame(0, $snapshot->settlement_snapshot['net_margin_cents']);
        $this->assertSame(0, $this->ledger->escrow($order->id)->balance_cents);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    private function approveExampleRates(): void
    {
        $creator = $this->user('logistics_admin');
        $approver = $this->user('logistics_admin');
        $service = app(DeliveryRateManagementService::class);
        foreach (['same_barangay', 'same_municipality', 'inter_municipality', 'inter_province'] as $route) {
            $inter = in_array($route, ['inter_municipality', 'inter_province'], true);
            $rate = $service->create($creator, ['name' => 'Sandbox journey '.$route, 'route_class' => $route, 'sandbox' => true, 'volumetric_divisor' => 6000, 'effective_at' => now()->subMinute(), 'rules' => ['weight_brackets' => [['max_grams' => 5000, 'components' => ['pickup' => 2000, 'origin_hub' => 800, 'linehaul' => $inter ? 2500 : 0, 'destination_hub' => $inter ? 800 : 0, 'last_mile' => 2500, 'overhead' => 800, 'margin' => 500, 'cod' => 400, 'return_to_sender' => 2000]]]]]);
            $service->approve($rate, $approver, 'Approve sandbox journey rates.');
        }
        $service->activate($approver, 'Activate sandbox journey rates.');
    }

    private function rider(User $hub): User
    {
        $rider = $this->user('courier');
        $rider->forceFill(['logistics_owner_id' => $hub->id, 'vehicle_capacity_grams' => 100000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear()])->save();

        return $rider;
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Finance', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }

    public function test_voucher_creation_reports_liability_shortfall_and_expiry_releases_unspent_funds(): void
    {
        $data = ['code' => 'BUDGET', 'type' => 'fixed', 'value' => 50, 'min_spend' => 0, 'usage_limit' => 100];
        $this->signIn($this->seller)->postJson(route('seller.vouchers.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('balance');
        $this->assertDatabaseMissing('vouchers', ['code' => 'BUDGET']);
        $this->fund($this->seller, 200000);
        $response = $this->postJson(route('seller.vouchers.store'), $data)->assertUnprocessable();
        $response->assertJsonPath('errors.balance.0', 'Insufficient available funds. Required: PHP 5,000.00; available: PHP 2,000.00; additional funds required: PHP 3,000.00.');
        $this->fund($this->seller, 300000);
        $this->post(route('seller.vouchers.store'), $data)->assertRedirect();
        $voucher = Voucher::where('code', 'BUDGET')->firstOrFail();
        $this->assertSame(500000, $voucher->budget->maximum_cents);
        $this->assertSame(0, $this->ledger->wallet($this->seller)->balance_cents);
        $this->postJson(route('seller.vouchers.store'), [...$data, 'code' => 'UNCAPPED', 'type' => 'percent'])->assertUnprocessable()->assertJsonValidationErrors('max_discount');
        $voucher->update(['expires_at' => now()->subMinute()]);
        $this->artisan('finance:release-expired-vouchers')->assertSuccessful();
        $this->assertSame(500000, $this->ledger->wallet($this->seller)->balance_cents);
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_spent_provider_reversal_freezes_spending_and_rejects_stale_or_mismatched_events(): void
    {
        $funding = app(WalletFundingService::class);
        $intent = $funding->create($this->buyer, 20000, (string) Str::uuid());
        $funding->simulate($intent, $this->admin, 'paid');
        $this->ledger->transfer('spent-before-reversal', 'test_transfer', $this->ledger->wallet($this->buyer), $this->ledger->treasury(), 15000, $this->buyer);
        $funding->simulate($intent->refresh(), $this->admin, 'reversed');
        $this->assertSame('UNDER_INVESTIGATION', $intent->refresh()->status);
        $this->assertTrue($this->ledger->wallet($this->buyer)->frozen);
        $this->assertSame(5000, $this->ledger->wallet($this->buyer)->balance_cents);
        $this->signIn($this->buyer)->postJson(route('finance.wallet.withdraw'), ['amount' => 1, 'destination' => 'sandbox-only', 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('wallet_withdrawals', 0);
        $body = json_encode(['event_id' => 'tampered', 'intent' => $intent->reference, 'amount_cents' => 20001, 'currency' => 'PHP', 'status' => 'paid', 'external_reference' => 'wrong']);
        foreach ([now()->timestamp - 301 => 401, now()->timestamp => 422] as $timestamp => $expected) {
            $signature = hash_hmac('sha256', $timestamp.'.'.$body, $funding->secret());
            try {
                $funding->callback($body, $signature, $timestamp);
                $this->fail('Invalid callback accepted.');
            } catch (HttpException $exception) {
                $this->assertSame($expected, $exception->getStatusCode());
            }
        }
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_wallet_actions_enforce_admin_domains_and_disabled_provider_mode(): void
    {
        $this->fund($this->buyer, 5000);
        $withdrawal = app(WalletWithdrawalService::class)->request($this->buyer, 2000, 'test-destination', (string) Str::uuid());
        $this->signIn($this->user('logistics_admin'))->postJson(route('finance.wallet.withdrawals.review', $withdrawal), ['decision' => 'approve', 'reason' => 'Incorrect domain review.'])->assertForbidden();
        $this->signIn($this->seller)->postJson(route('finance.wallet.withdrawals.review', $withdrawal), ['decision' => 'approve', 'reason' => 'Unauthorized review.'])->assertForbidden();
        $this->signIn($this->admin)->post(route('finance.wallet.withdrawals.review', $withdrawal), ['decision' => 'reject', 'reason' => 'Requested cancellation.'])->assertRedirect();
        $this->assertSame(5000, $this->ledger->wallet($this->buyer)->balance_cents);
        $intent = app(WalletFundingService::class)->create($this->buyer, 100, (string) Str::uuid());
        $this->signIn($this->buyer)->postJson(route('finance.wallet.simulate', $intent), ['result' => 'paid'])->assertForbidden();
        config(['financial.mode' => 'live']);
        $this->postJson(route('finance.wallet.topup'), ['amount' => 1, 'request_key' => (string) Str::uuid()])->assertStatus(503);
        $this->assertSame('PENDING', $intent->fresh()->status);
    }

    public function test_long_distance_zones_require_approval_and_do_not_change_historical_snapshots(): void
    {
        $maker = $this->user('logistics_admin');
        $checker = $this->user('logistics_admin');
        $service = app(DeliveryRateManagementService::class);
        $rules = ['weight_brackets' => [['max_grams' => 5000, 'components' => ['pickup' => 2000, 'last_mile' => 3000, 'remote' => 500]]], 'destination_zones' => [['origin_province' => 'Laguna', 'province' => 'Cebu']], 'remote_municipalities' => ['  CEBU CITY ']];
        $rate = $service->create($maker, ['name' => 'Fictional long-distance test', 'route_class' => 'long_distance', 'sandbox' => true, 'volumetric_divisor' => 6000, 'effective_at' => now()->subMinute(), 'rules' => $rules]);
        $address = ['province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Test'];
        $this->seller->update(['province' => 'Laguna', 'municipality' => 'Majayjay']);
        $pricing = app(DeliveryPricingService::class);
        $this->assertSame('inter_province', $pricing->route($this->seller, $address));
        $this->signIn($maker)->postJson(route('finance.rates.approve', $rate), ['reason' => 'Self approval not allowed.'])->assertForbidden();
        $service->approve($rate, $checker, 'Independent test tariff approval.');
        $this->assertSame('long_distance', $pricing->route($this->seller, $address));
        $snapshot = $pricing->calculate($rate->refresh(), 1000, [100, 100, 100], 'COD', $address);
        $this->assertSame(5500, $snapshot['total_cents']);
        try {
            $rate->update(['rules' => []]);
            $this->fail('Approved tariff was editable.');
        } catch (\LogicException) {
            $this->assertSame($rules['weight_brackets'], $rate->fresh()->rules['weight_brackets']);
        }
        $this->assertSame(5500, $snapshot['total_cents']);
    }

    public function test_batch_exact_excess_missing_evidence_and_wrong_hub_controls(): void
    {
        $rider = $this->user('courier');
        $hub = $this->user('sorting_center');
        $otherHub = $this->user('sorting_center');
        Storage::disk('private')->put('cash.pdf', 'Synthetic evidence');
        $service = app(RemittanceBatchService::class);
        foreach ([15000, 15100] as $received) {
            $order = $this->order(['sorting_center_id' => $hub->id, 'delivery_courier_id' => $rider->id]);
            $finance = app(OrderFinancialService::class)->initialize($order);
            $finance->update(['collector_id' => $rider->id, 'collected_cents' => 15000, 'remittance_status' => 'COLLECTED']);
            foreach ([[$hub, 'missing.pdf'], [$otherHub, 'cash.pdf']] as [$targetHub, $evidence]) {
                try {
                    $service->submit($rider, $targetHub, [$finance->id], 15000, $evidence, 'Invalid batch submission.');
                    $this->fail('Invalid evidence or hub accepted.');
                } catch (HttpException $exception) {
                    $this->assertSame(422, $exception->getStatusCode());
                }
            }
            $batch = $service->submit($rider, $hub, [$finance->id], 15000, 'cash.pdf', 'Cash handover declaration.');
            $service->receive($batch, $hub, $received, 'cash.pdf', 'Physical cash received and counted.');
            $this->assertSame($received === 15000 ? 'VERIFIED' : 'DISCREPANCY', $batch->fresh()->status);
            $this->assertSame($received - 15000, $batch->fresh()->discrepancy_cents);
        }
        $this->assertSame(0, $this->ledger->wallet($rider)->balance_cents);
    }

    public function test_measured_weight_adjustment_requires_funded_treasury_review_and_keeps_buyer_price(): void
    {
        $maker = $this->user('logistics_admin');
        $checker = $this->user('logistics_admin');
        $hub = $this->user('sorting_center');
        $rates = app(DeliveryRateManagementService::class);
        $rate = $rates->create($maker, ['name' => 'Measurement test only', 'route_class' => 'same_barangay', 'sandbox' => true, 'volumetric_divisor' => 6000, 'effective_at' => now()->subMinute(), 'rules' => ['weight_brackets' => [['max_grams' => 1000, 'components' => ['last_mile' => 5000]], ['max_grams' => 5000, 'components' => ['last_mile' => 7000]]]]]);
        $rates->approve($rate, $checker, 'Independent measurement test approval.');
        DB::table('commerce_settings')->updateOrInsert(['key' => 'shipping_model'], ['value' => 1]);
        $this->fund($this->buyer, 30000);
        $order = $this->buy($this->product(['length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100]));
        $order->forceFill(['sorting_center_id' => $hub->id, 'status' => 'AT_SORTING_CENTER'])->save();
        $total = $order->total_amount;
        Storage::disk('private')->put('scale.jpg', 'Synthetic scale evidence');
        $service = app(ShipmentAdjustmentService::class);
        $adjustment = $service->propose($order, $hub, 2000, [100, 100, 100], 'scale.jpg', 'Scale shows two kilograms.');
        try {
            $service->approve($adjustment, $this->admin, 'Approve verified weight correction.');
            $this->fail('Unfunded treasury approval accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('balance', $exception->errors());
        }
        $this->assertSame('PROPOSED', $adjustment->fresh()->status);
        $this->fund($this->admin, 2000, true);
        $service->approve($adjustment, $this->admin, 'Approve and fund verified weight correction.');
        $snapshot = ShipmentFinancial::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(2000, $snapshot->additional_funding_cents);
        $this->assertSame(5000, $snapshot->quote_snapshot['total_cents']);
        $this->assertSame($total, $order->fresh()->total_amount);
        $this->assertSame(15000, $order->fresh()->wallet_paid_cents);
        $this->signIn($this->user('buyer'))->get(route('finance.shipments.evidence', $adjustment))->assertForbidden();
        $this->assertTrue($this->ledger->reconcile()['balanced']);
    }

    public function test_api_requote_preserves_checkout_token_and_returns_destination_specific_hash(): void
    {
        $product = $this->product();
        $token = (string) Str::uuid();
        $body = ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'destination' => $this->address(), 'checkout_token' => $token];
        $this->postJson('/api/cart/quote', $body)->assertOk()->assertJsonPath('data.checkout_token', $token);
        $otherSeller = $this->user('seller');
        $otherProduct = $this->product(['user_id' => $otherSeller->id]);
        $body['items'][] = ['product_id' => $otherProduct->id, 'quantity' => 1];
        $this->postJson('/api/cart/quote', $body)->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    private function fund(User $owner, int $amount, bool $treasury = false): void
    {
        $funding = app(WalletFundingService::class);
        $intent = $funding->create($owner, $amount, (string) Str::uuid(), $treasury);
        $funding->simulate($intent, $this->admin, 'paid');
    }

    private function product(array $extra = []): Product
    {
        return Product::forceCreate($extra + ['user_id' => $this->seller->id, 'name' => 'Finance demo product', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
    }

    private function address(): array
    {
        return ['recipient_name' => 'Finance buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Test street'];
    }

    private function buy(Product $product, ?Voucher $voucher = null): Order
    {
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($cart, $voucher, false, ['address' => $this->address(), 'payment_method' => 'WALLET']);

        return app(PlacePurchaseService::class)->place($this->buyer, (string) Str::uuid(), 'request', $cart, $quote['hash'], $voucher?->id, $this->address(), 'WALLET')->orders()->firstOrFail();
    }

    private function order(array $extra = []): Order
    {
        return Order::forceCreate($extra + $this->address() + ['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'order_number' => 'FIN-'.Str::upper(Str::random(10)), 'status' => 'PLACED', 'subtotal' => 100, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'payment_method' => 'COD']);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
