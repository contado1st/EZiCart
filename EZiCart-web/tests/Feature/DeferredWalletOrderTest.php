<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\BuyerCartService;
use App\Services\BuyerOrderGroupingService;
use App\Services\CheckoutPricingService;
use App\Services\DemoWalletFundingService;
use App\Services\FinancialLedgerService;
use App\Services\OrderProgressService;
use App\Services\PlacePurchaseService;
use App\Services\WalletOrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DeferredWalletOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['financial.sandbox_enabled' => true]);
        Notification::fake();
    }

    public function test_unpaid_reservation_is_owned_to_pay_without_seller_deadline_or_fulfillment(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $token = (string) Str::uuid();
        $order = $this->reserve($buyer, $product, $token);
        $repeat = $this->reserve($buyer, $product, $token);
        $this->assertSame($order->id, $repeat->id);
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertNull($order->seller_response_due_at);
        $this->assertNull($order->wallet_transaction_id);
        $this->assertSame(0, $order->wallet_paid_cents);
        $this->assertSame(now()->addMinutes(30)->toDateTimeString(), $order->wallet_payment_due_at->toDateTimeString());
        Notification::assertNotSentTo($seller, OrderWorkflowNotification::class);
        $counts = app(BuyerOrderGroupingService::class)->counts(Order::where('buyer_id', $buyer->id));
        $this->assertSame(1, $counts['to_pay']);
        $this->assertSame(0, $counts['to_ship']);
        $this->assertSame(1, $counts['all']);
        $this->signIn($buyer)->get(route('buyer.dashboard', ['order_group' => 'to_pay']))->assertOk()->assertSee($order->order_number)->assertSee('Pay or cancel reservation');
        $this->get(route('buyer.orders.wallet-payment', $order))->assertOk()->assertSee('Additional demo funds needed')->assertSee('150.00');
        $this->signIn($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertSessionHasErrors('status');
        $this->assertSame('PLACED', $order->fresh()->status);
        $this->assertSame('buyer', app(OrderProgressService::class)->describe($order)['owner']);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_password_confirmed_payment_debits_exact_total_and_replay_does_not_restart_deadline_or_notify_twice(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $this->fund($buyer, 20000);
        $this->signIn($buyer)->post(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->assertNull($order->fresh()->wallet_transaction_id);
        $this->post(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertRedirect(route('buyer.orders.show', $order));
        $deadline = $order->fresh()->seller_response_due_at;
        $this->travel(1)->minutes();
        $this->post(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertRedirect();
        $this->assertTrue($order->fresh()->seller_response_due_at->eq($deadline));
        $ledger = app(FinancialLedgerService::class);
        $this->assertSame(5000, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame(9000, $ledger->wallet($seller, 'pending')->balance_cents);
        $this->assertSame(15000, $order->fresh()->wallet_paid_cents);
        $this->assertNull($order->fresh()->finance->collector_id);
        $this->assertNull($order->fresh()->finance->collected_cents);
        $this->assertDatabaseCount('ledger_transactions', 3);
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'wallet_payment_received')->count());
        // The established sender dispatches database and after-commit mail separately.
        Notification::assertSentToTimes($seller, OrderWorkflowNotification::class, 2);
        $counts = app(BuyerOrderGroupingService::class)->counts(Order::where('buyer_id', $buyer->id));
        $this->assertSame(0, $counts['to_pay']);
        $this->assertSame(1, $counts['to_ship']);
        $this->assertTrue($ledger->reconcile()['balanced']);
    }

    public function test_insufficient_funds_does_not_charge_or_release_a_still_valid_reservation(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $this->fund($buyer, 10000);
        $this->signIn($buyer)->post(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertSessionHasErrors('balance');
        $this->assertSame(10000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertNull($order->fresh()->wallet_transaction_id);
        $this->assertTrue($order->fresh()->isAwaitingWalletPayment());
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertDatabaseCount('ledger_transactions', 1);
        Notification::assertNotSentTo($seller, OrderWorkflowNotification::class);
    }

    public function test_expiry_boundary_and_repeated_cleanup_restore_stock_once_and_reject_late_payment(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $this->fund($buyer, 20000);
        $this->travel(30)->minutes();
        $this->signIn($buyer)->postJson(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertUnprocessable();
        $this->artisan('wallet:expire-orders')->expectsOutput('Expired 1 unpaid wallet reservation(s).')->assertSuccessful();
        $this->artisan('wallet:expire-orders')->expectsOutput('Expired 0 unpaid wallet reservation(s).')->assertSuccessful();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(20000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'wallet_payment_expired')->count());
    }

    public function test_unpaid_owner_can_cancel_without_seller_approval_but_paid_cancellation_uses_existing_policy(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $unpaid = $this->reserve($buyer, $product);
        $this->signIn($buyer)->post(route('buyer.orders.wallet-payment.cancel', $unpaid))->assertRedirect();
        $this->assertSame('CANCELLED', $unpaid->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('order_cancellation_requests', 0);
        $paid = $this->reserve($buyer, $product);
        $this->fund($buyer, 20000);
        app(WalletOrderPaymentService::class)->pay($paid, $buyer);
        $this->postJson(route('buyer.orders.wallet-payment.cancel', $paid))->assertUnprocessable();
        $this->assertSame('PLACED', $paid->fresh()->status);
        $this->post(route('buyer.orders.cancel', $paid), ['reason' => 'Buyer changed plans before shipment.'])->assertRedirect();
        $this->assertNotNull($paid->fresh()->pendingCancellation);
        $this->assertSame(5000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
    }

    public function test_other_accounts_and_roles_cannot_view_pay_or_cancel_the_reservation(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $other = $this->user('buyer');
        $this->signIn($other)->get(route('buyer.orders.wallet-payment', $order))->assertNotFound();
        $this->post(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertNotFound();
        $this->post(route('buyer.orders.wallet-payment.cancel', $order))->assertNotFound();
        $this->signIn($seller)->get(route('buyer.orders.wallet-payment', $order))->assertForbidden();
        $this->assertTrue($order->fresh()->isAwaitingWalletPayment());
    }

    public function test_seller_cancels_internal_demo_paid_order_with_one_full_wallet_refund_and_no_earnings(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $this->fund($buyer, 20000);
        app(WalletOrderPaymentService::class)->pay($order, $buyer);
        $this->signIn($seller)->post(route('seller.orders.cancel', $order), ['reason' => 'Product damaged before packing.'])->assertRedirect();
        $ledger = app(FinancialLedgerService::class);
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(20000, $ledger->wallet($buyer)->balance_cents);
        $this->assertSame(0, $ledger->wallet($seller)->balance_cents);
        $this->assertSame(0, $ledger->wallet($seller, 'pending')->balance_cents);
        $this->assertSame(10, $product->fresh()->stock);
        $this->postJson(route('seller.orders.cancel', $order), ['reason' => 'Repeat cancellation request.'])->assertUnprocessable();
        $this->assertSame(1, LedgerTransaction::where('type', 'cancelled_order_refund')->count());
        Notification::assertSentTo($buyer, OrderWorkflowNotification::class, fn ($notification) => $notification->eventType === 'wallet_refunded');
        $this->assertTrue($ledger->reconcile()['balanced']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_deferred_web_checkout_replay_preserves_unselected_cart_and_routes_to_payment(bool $buyNow): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $other = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Keep in cart', 'category' => 'Home and Garden', 'price' => 200, 'stock' => 5, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
        $this->signIn($buyer)->post(route('cart.add', $product), ['quantity' => 1]);
        $this->post(route('cart.add', $other), ['quantity' => 2]);
        if ($buyNow) {
            $this->post(route('cart.add', $product), ['quantity' => 1, 'intent' => 'buy_now'])->assertRedirect();
        } else {
            $this->post(route('cart.checkout'), ['selected_keys' => [(string) $product->id]])->assertRedirect();
        }
        $this->get(route('checkout.index', ['payment_method' => 'WALLET']))->assertOk()->assertSee('Reserve now and pay from To Pay');
        $data = ['checkout_token' => session('checkout_token'), 'quote_hash' => session('checkout_quote_hash'), 'payment_method' => 'WALLET', 'wallet_payment_timing' => 'later',
            'recipient_name' => 'Demo buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Test street'];
        $this->post(route('checkout.process'), $data)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->post(route('checkout.process'), $data)->assertRedirect(route('buyer.orders.wallet-payment', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->assertTrue($order->isAwaitingWalletPayment());
        $this->assertSame($buyNow ? [$product->id, $other->id] : [$other->id], array_keys(app(BuyerCartService::class)->read($buyer)));
        $this->assertSame(2, app(BuyerCartService::class)->read($buyer)[$other->id]['quantity']);
    }

    public function test_api_counts_exclude_the_new_unpaid_reservation_from_to_ship_and_cancel_it_immediately(): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $token = ApiToken::createTokenForUser($buyer)['plainTextToken'];
        $this->withToken($token)->getJson('/api/orders?tab=to_pay')->assertOk()->assertJsonPath('counts.to_pay', 1)->assertJsonPath('counts.to_ship', 0)->assertJsonPath('total', 1);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('order_counts.to_pay', 1)->assertJsonPath('order_counts.to_ship', 0);
        $this->postJson('/api/orders/'.$order->id.'/cancel', ['reason' => 'Cancel unpaid reservation.'])->assertOk();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertDatabaseCount('order_cancellation_requests', 0);
        $this->getJson('/api/orders?tab=to_pay')->assertOk()->assertJsonPath('counts.to_pay', 0)->assertJsonPath('total', 0);
    }

    #[TestWith(['frozen'])]
    #[TestWith(['archived'])]
    #[TestWith(['disabled'])]
    #[TestWith(['received'])]
    #[TestWith(['released'])]
    public function test_payment_hold_cannot_charge_or_start_seller_deadlines(string $hold): void
    {
        [$buyer, $seller, $product] = $this->crew();
        $order = $this->reserve($buyer, $product);
        $this->fund($buyer, 20000);
        if ($hold === 'frozen') {
            app(FinancialLedgerService::class)->wallet($buyer)->update(['frozen' => true]);
        }
        if ($hold === 'archived') {
            $product->update(['is_archived' => true]);
        }
        if ($hold === 'disabled') {
            config(['financial.sandbox_enabled' => false]);
        }
        if ($hold === 'received') {
            $order->forceFill(['received_at' => now()])->save();
        }
        if ($hold === 'released') {
            $order->forceFill(['hub_released_at' => now()])->save();
        }
        $this->signIn($buyer)->postJson(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertStatus($hold === 'disabled' ? 503 : 422);
        $this->assertNull($order->fresh()->wallet_transaction_id);
        $this->assertNull($order->fresh()->seller_response_due_at);
        $this->assertSame(20000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    private function crew(): array
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Internal wallet demo product', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved']);

        return [$buyer, $seller, $product];
    }

    private function reserve(User $buyer, Product $product, ?string $token = null): Order
    {
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $address = ['recipient_name' => 'Demo buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Test street'];
        $quote = app(CheckoutPricingService::class)->quote($cart, null, false, ['address' => $address, 'payment_method' => 'WALLET']);

        return app(PlacePurchaseService::class)->place($buyer, $token ?? (string) Str::uuid(), 'demo-reservation', $cart, $quote['hash'], null, $address, 'WALLET', true)->orders()->firstOrFail();
    }

    private function fund(User $owner, int $amount): void
    {
        $funding = app(DemoWalletFundingService::class);
        $intent = $funding->request($owner, $amount, (string) Str::uuid(), 'demo_credit', ['reason' => 'School prepaid order testing.']);
        $funding->review($intent, $this->user('ecommerce_admin'), 'approve', 'Independent demo funding review.');
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Demo', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved',
            'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
