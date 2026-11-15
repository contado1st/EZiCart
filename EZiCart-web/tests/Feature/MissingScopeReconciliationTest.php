<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderWorkflowNotification;
use App\Services\CheckoutPricingService;
use App\Services\DemoWalletFundingService;
use App\Services\FinancialLedgerService;
use App\Services\PlacePurchaseService;
use App\Services\WalletOrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MissingScopeReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['financial.sandbox_enabled' => true]);
    }

    public function test_abandoning_and_reopening_internal_wallet_preserves_one_to_pay_reservation_without_a_charge(): void
    {
        Notification::fake();
        $buyer = $this->user('buyer');
        [$order,$product] = $this->reserve($buyer);
        $due = $order->wallet_payment_due_at->toIso8601String();
        $this->as($buyer)->get(route('buyer.orders.wallet-payment', $order))->assertSee('Back to To Pay')->assertSee('Leaving this page');
        $this->get(route('buyer.dashboard', ['order_group' => 'to_pay']))->assertSee($order->order_number);
        $this->as($buyer)->get(route('buyer.orders.wallet-payment', $order))->assertOk()->assertSee('Reserved, awaiting payment');
        $this->assertTrue($order->fresh()->isAwaitingWalletPayment());
        $this->assertSame($due, $order->fresh()->wallet_payment_due_at->toIso8601String());
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(0, DB::table('ledger_transactions')->where('type', 'order_payment')->count());
        Notification::assertNotSentTo($order->seller, OrderWorkflowNotification::class);
    }

    public function test_committed_wallet_payment_survives_a_closed_page_and_retries_charge_once(): void
    {
        Notification::fake();
        $buyer = $this->user('buyer');
        [$order,$product] = $this->reserve($buyer);
        $funding = app(DemoWalletFundingService::class);
        $intent = $funding->request($buyer, 20000, (string) Str::uuid(), 'demo_credit', ['reason' => 'School closed-browser payment recovery test.']);
        $funding->review($intent, $this->user('ecommerce_admin'), 'approve', 'Independent school demo funding approval.');
        // The committed server operation completes even when its redirect is never consumed.
        app(WalletOrderPaymentService::class)->pay($order, $buyer);
        $transaction = $order->fresh()->wallet_transaction_id;
        $this->as($buyer)->get(route('buyer.dashboard', ['order_group' => 'to_ship']))->assertSee($order->order_number);
        $this->get(route('buyer.orders.wallet-payment', $order))->assertSee('A wallet payment is already recorded');
        $this->post(route('buyer.orders.wallet-payment.pay', $order), ['password' => 'Password123!'])->assertSessionHasNoErrors();
        $this->assertSame($transaction, $order->fresh()->wallet_transaction_id);
        $this->assertSame(1, DB::table('ledger_transactions')->where('type', 'order_payment')->count());
        $this->assertSame(5000, app(FinancialLedgerService::class)->wallet($buyer)->balance_cents);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertFalse($order->fresh()->isAwaitingWalletPayment());
        Notification::assertSentTo($order->seller, OrderWorkflowNotification::class);
    }

    public function test_reason_only_or_forced_delivered_correction_is_blocked_and_valid_request_changes_no_status(): void
    {
        Storage::fake('private');
        $hub = $this->user('sorting_center');
        $order = Order::forceCreate(['order_number' => 'POLICY-'.Str::random(10), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'sorting_center_id' => $hub->id, 'recipient_name' => 'Synthetic buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Fixture', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'OUT_FOR_DELIVERY']);
        $this->as($hub)->post(route('logistics-corrections.store', $order), ['reason' => '', 'status' => 'DELIVERED', 'evidence' => UploadedFile::fake()->image('evidence.jpg')])->assertSessionHasErrors(['reason', 'status']);
        $this->post(route('logistics-corrections.store', $order), ['reason' => 'Rider offline; phone confirmation alone is not proof.', 'target_status' => 'DELIVERED', 'evidence' => UploadedFile::fake()->image('evidence.jpg')])->assertSessionHasErrors('target_status');
        $this->assertDatabaseCount('parcel_corrections', 0);
        $this->post(route('logistics-corrections.store', $order), ['reason' => 'Investigate offline rider and reconcile actual delivery evidence.', 'evidence' => UploadedFile::fake()->image('evidence.jpg')])->assertSessionHasNoErrors();
        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);
        $this->assertNull($order->fresh()->delivery_code_used_at);
        $this->assertDatabaseCount('parcel_corrections', 1);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    private function as(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'Password123!', 'first_name' => 'Scope', 'last_name' => 'Fixture', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }

    private function reserve(User $buyer): array
    {
        $product = Product::forceCreate(['user_id' => $this->user('seller')->id, 'name' => 'School wallet product', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $address = ['recipient_name' => 'Scope buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Fixture'];
        $quote = app(CheckoutPricingService::class)->quote($cart, null, false, ['address' => $address, 'payment_method' => 'WALLET']);
        $order = app(PlacePurchaseService::class)->place($buyer, (string) Str::uuid(), 'scope-fixture', $cart, $quote['hash'], null, $address, 'WALLET', true)->orders()->firstOrFail();

        return [$order, $product];
    }
}
