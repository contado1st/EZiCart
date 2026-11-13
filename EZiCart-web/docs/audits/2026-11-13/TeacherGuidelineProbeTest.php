<?php

namespace Tests\Audit;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BuyerAddressService;
use App\Services\DeliveryCodeService;
use App\Services\FinancialLedgerService;
use App\Services\WalletWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Audit-only characterization: passing probes CONFIRM differences, not teacher-case passes. */
class TeacherGuidelineProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4]);
        Notification::fake();
        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_address_book_allows_eleven_and_replaces_a_deleted_default(): void
    {
        $buyer = $this->user('buyer');
        $addresses = app(BuyerAddressService::class);
        for ($i = 0; $i < 11; $i++) {
            $addresses->save($buyer, ['label' => 'Address '.$i, 'recipient_name' => 'Audit Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Synthetic address']);
        }
        $this->assertSame(11, $buyer->addresses()->count());
        $default = $buyer->addresses()->where('is_default', true)->firstOrFail();
        $addresses->delete($buyer, $default->id);
        $this->assertSame(10, $buyer->addresses()->count());
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
        $this->observe('BUY-ADDR-005,007', 'Eleven addresses saved; deleting the default succeeds and automatically chooses a replacement.');
    }

    public function test_product_boundaries_and_duplicate_submit_differ_from_teacher_rules(): void
    {
        $seller = $this->user('seller');
        $seller->forceFill(['line_of_business' => 'Pet Supplies'])->save();
        $payload = ['name' => 'X', 'category' => 'Electronics and Gadgets', 'price' => '0.99', 'stock' => 100000, 'weight_kg' => '0.010'];
        $this->actingAs($seller)->post(route('seller.products.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('seller.products.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $product = Product::firstOrFail();
        $this->assertSame('pending_review', $product->compliance_status);
        $this->assertSame('X', $product->name);
        $this->assertNull($product->image_path);
        $this->observe('SEL-PROD-001,003,004,005,006,017; checklist 2.2.2', 'Two identical POSTs create two pending-review products. A one-character name, PHP 0.99, 100000 stock, missing description/image and a category different from the seller business category are accepted.');
    }

    public function test_review_accepts_501_characters_after_thirty_days_without_photo_support(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $product = $this->product($seller);
        $order = $this->order($buyer, $seller, ['status' => 'COMPLETED', 'delivered_at' => now()->subDays(41), 'created_at' => now()->subDays(42), 'updated_at' => now()->subDays(40)]);
        $order->trackingEvents()->forceCreate(['actor_id' => $buyer->id, 'status' => 'COMPLETED', 'event_type' => 'order_completed', 'created_at' => now()->subDays(40)]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'item_total' => 100]);
        $this->actingAs($buyer)->post(route('buyer.orders.review', $order), ['product_id' => $product->id, 'rating' => 5, 'comment' => str_repeat('a', 501)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reviews', 1);
        $this->observe('BUY-RATE-001,004,005; A12', 'An order with a completion event 40 days ago accepts a new 501-character review. Review storage has no media/edit/reply fields or endpoint.');
    }

    public function test_preparing_cancellation_is_pending_without_reason_but_ready_for_pickup_is_blocked(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $order = $this->order($buyer, $seller, ['status' => 'PREPARING']);
        $this->actingAs($buyer)->post(route('buyer.orders.cancel', $order), [])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PREPARING', $order->fresh()->status);
        $this->assertDatabaseHas('order_cancellation_requests', ['order_id' => $order->id, 'status' => 'PENDING']);
        $ready = $this->order($buyer, $seller, ['status' => 'READY_FOR_PICKUP']);
        $this->post(route('buyer.orders.cancel', $ready), [])->assertUnprocessable();
        $this->observe('BUY-CAN-001,002,004; A6', 'Before Ready for Pickup, cancellation accepts no supplied reason and waits for seller approval. At Ready for Pickup it is correctly blocked.');
    }

    public function test_completed_order_can_open_a_dispute_after_seven_days_without_evidence(): void
    {
        $buyer = $this->user('buyer');
        $order = $this->order($buyer, $this->user('seller'), ['status' => 'COMPLETED', 'delivered_at' => now()->subDays(20)]);
        $this->actingAs($buyer)->post(route('buyer.orders.dispute.store', $order), ['reason' => 'Other', 'description' => 'This synthetic audit dispute has no uploaded evidence.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('disputes', ['order_id' => $order->id, 'status' => 'PENDING', 'evidence_path' => null]);
        $this->observe('BUY-RET-003,004; A11,A31', 'A completed order delivered 20 days ago accepts a dispute with no uploaded evidence.');
    }

    public function test_delivery_code_lock_does_not_automatically_unlock_after_fifteen_minutes(): void
    {
        $codes = app(DeliveryCodeService::class);
        $issued = $codes->issue();
        $code = $issued['code'];
        unset($issued['code']);
        $order = $this->order($this->user('buyer'), $this->user('seller'), [...$issued, 'status' => 'OUT_FOR_DELIVERY', 'delivery_code_attempts' => 5]);
        $this->assertFalse($codes->matches($order, $code));
        $this->travel(16)->minutes();
        $this->assertFalse($codes->matches($order->fresh(), $code));
        $this->observe('RID-DLV-004; A32', 'After five failed attempts, the correct delivery code remains unusable after 16 minutes; buyer refresh is needed.');
    }

    public function test_voucher_code_symbols_are_accepted_with_a_funded_budget(): void
    {
        $seller = $this->user('seller');
        $this->fund($seller, 5000);
        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'BAD SYMBOL!', 'type' => 'fixed', 'value' => 10, 'usage_limit' => 1, 'min_spend' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vouchers', ['code' => 'BAD SYMBOL!']);
        $this->observe('SEL-VOU-004,006; A26', 'A funded voucher accepts spaces/punctuation and a discount greater than its zero minimum spend.');
    }

    public function test_buyer_profile_contact_change_has_no_otp_or_mobile_format_check(): void
    {
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->patch(route('account.profile.update'), ['first_name' => 'A', 'last_name' => 'B', 'contact_no' => 'NOT-A-MOBILE'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('NOT-A-MOBILE', $buyer->fresh()->contact_no);
        $this->observe('BUY-ACCT-002,009', 'Buyer profile saves one-character names and a non-mobile contact string immediately, without a verification step. Registration validation does not protect this separate update path.');
    }

    public function test_small_and_multiple_pending_withdrawals_are_accepted(): void
    {
        $seller = $this->user('seller');
        $this->fund($seller, 30000);
        $service = app(WalletWithdrawalService::class);
        $one = $service->request($seller, 9999, 'synthetic destination', (string) Str::uuid());
        $two = $service->request($seller, 10000, 'synthetic destination', (string) Str::uuid());
        $this->assertNotSame($one->id, $two->id);
        $this->assertDatabaseCount('wallet_withdrawals', 2);
        $this->observe('SEL-EARN-005,006; A15', 'Wallet withdrawal accepts PHP 99.99 and a second simultaneous pending request. Existing order-level seller payout is a separate per-order request, not the teacher balance-payout contract.');
    }

    private function user(string $role): User
    {
        Auth::forgetGuards();
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'AuditPassword123!', 'first_name' => 'Audit', 'last_name' => 'Synthetic', 'contact_no' => '09123456789', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now()]);
    }

    private function product(User $seller): Product
    {
        return Product::forceCreate(['user_id' => $seller->id, 'name' => 'Synthetic audit product', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
    }

    private function order(User $buyer, User $seller, array $extra = []): Order
    {
        return Order::forceCreate($extra + ['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'AUDIT-'.Str::random(12), 'status' => 'PLACED', 'payment_method' => 'COD', 'subtotal' => 100, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'recipient_name' => 'Synthetic Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Synthetic address']);
    }

    private function fund(User $owner, int $cents): void
    {
        $ledger = app(FinancialLedgerService::class);
        $ledger->transfer('audit-fund-'.Str::uuid(), 'synthetic_test_funding', $ledger->account('audit:clearing', 'test', null, 'available', true), $ledger->wallet($owner), $cents, null);
    }

    private function observe(string $criteria, string $finding): void
    {
        file_put_contents(__DIR__.'/probe-observations.jsonl', json_encode(['criteria' => $criteria, 'observed' => $finding], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
