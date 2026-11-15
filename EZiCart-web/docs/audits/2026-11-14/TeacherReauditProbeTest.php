<?php

namespace Tests\Audit;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BuyerAddressService;
use App\Services\BuyerEmailChangeService;
use App\Services\BuyerOrderGroupingService;
use App\Services\FinancialLedgerService;
use App\Services\RiderWorkAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Characterization only: passing confirms observed behavior, not teacher acceptance. */
class TeacherReauditProbeTest extends TestCase
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

    public function test_phone_and_name_minimum_are_fixed_but_name_digits_and_unverified_phone_remain(): void
    {
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->patch(route('account.profile.update'), ['first_name' => 'A', 'last_name' => 'B', 'contact_no' => 'NOT-A-MOBILE'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'contact_no']);
        $this->patch(route('account.profile.update'), ['first_name' => 'Juan123', 'last_name' => 'Audit', 'contact_no' => '09189876543'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Juan123', $buyer->fresh()->first_name);
        $this->assertSame('09189876543', $buyer->fresh()->contact_no);
        Notification::assertNothingSent();
        $this->observe('BUY-ACCT-002,009', 'One-character names and malformed phones now fail; digits in names and immediate phone changes without phone OTP still succeed.');
    }

    public function test_normal_product_submit_still_has_looser_boundaries_and_no_replay_key(): void
    {
        $seller = $this->user('seller');
        $seller->forceFill(['line_of_business' => 'Pet Supplies'])->save();
        $payload = ['name' => 'X', 'category' => 'Electronics and Gadgets', 'price' => '0.99', 'stock' => 100000, 'weight_kg' => '0.010'];
        $this->actingAs($seller)->post(route('seller.products.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('seller.products.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertNull(Product::firstOrFail()->image_path);
        $this->assertSame('pending_review', Product::firstOrFail()->compliance_status);
        $this->observe('SEL-PROD-001,003,004,005,006,017', 'Normal product POST still accepts one-character name, PHP0.99, stock100000, no description/image, and another shared category. Repeating creates two pending-review products; draft submission has separate replay protection.');
    }

    public function test_address_limit_and_default_deletion_remain_different(): void
    {
        $buyer = $this->user('buyer');
        $service = app(BuyerAddressService::class);
        for ($i = 0; $i < 11; $i++) {
            $service->save($buyer, ['label' => 'Address '.$i, 'recipient_name' => 'Audit Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Synthetic address']);
        }
        $this->assertSame(11, $buyer->addresses()->count());
        $service->delete($buyer, $buyer->addresses()->where('is_default', true)->firstOrFail()->id);
        $this->assertSame(10, $buyer->addresses()->count());
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
        $this->observe('BUY-ADDR-005,007', 'Eleven addresses remain allowed; deleting the default automatically promotes a replacement.');
    }

    public function test_new_review_media_does_not_impose_teacher_length_or_edit_window(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Audit item', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
        $order = $this->order($buyer, $seller, ['status' => 'COMPLETED', 'delivered_at' => now()->subDays(41)]);
        $order->trackingEvents()->forceCreate(['actor_id' => $buyer->id, 'status' => 'COMPLETED', 'event_type' => 'order_completed', 'created_at' => now()->subDays(40)]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'item_total' => 100]);
        $this->actingAs($buyer)->post(route('buyer.orders.review', $order), ['product_id' => $product->id, 'rating' => 5, 'comment' => str_repeat('a', 501)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $review = $order->reviews()->firstOrFail();
        foreach ([1, 2] as $version) {
            $this->patch(route('buyer.reviews.update', $review), ['rating' => 4, 'comment' => 'Edited '.$version, 'version' => $version])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(3, $review->fresh()->revision_version);
        $this->observe('BUY-RATE-004,005; A12', 'Review accepts 501 characters after a 40-day-old completion and permits two versioned edits. Photos/history now exist, but 500-character/30-day/one-edit teacher limits remain absent.');
    }

    public function test_hundred_percent_and_punctuation_voucher_are_still_accepted(): void
    {
        $seller = $this->user('seller');
        $ledger = app(FinancialLedgerService::class);
        $ledger->transfer('audit-'.Str::uuid(), 'synthetic_test_funding', $ledger->account('audit:clearing', 'test', null, 'available', true), $ledger->wallet($seller), 5000, null);
        $this->actingAs($seller)->post(route('seller.vouchers.store'), ['code' => 'BAD SYMBOL!', 'type' => 'percent', 'value' => 100, 'max_discount' => 10, 'usage_limit' => 1, 'min_spend' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vouchers', ['code' => 'BAD SYMBOL!', 'value' => 100]);
        $this->observe('SEL-VOU-004,006,007', 'A funded capped 100-percent voucher with spaces/punctuation and zero minimum spend is accepted.');
    }

    public function test_availability_code_only_filters_when_rollout_is_enabled(): void
    {
        $rider = $this->user('courier');
        $rider->forceFill(['work_availability' => 'unavailable'])->save();
        $service = app(RiderWorkAvailabilityService::class);
        config(['rider-work.enforce_availability' => false]);
        $this->assertTrue($service->availableRiders(User::query())->whereKey($rider->id)->exists());
        $service->assertNewWork($rider);
        config(['rider-work.enforce_availability' => true]);
        $this->assertFalse($service->availableRiders(User::query())->whereKey($rider->id)->exists());
        try {
            $service->assertNewWork($rider);
            $this->fail('Unavailable rider should be blocked when enforcement is enabled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rider_availability', $exception->errors());
        }
        $this->observe('LOG-DISP-003; RID-AVAIL', 'Manual availability filtering works with enforcement enabled in isolated tests, but unavailable approved riders are eligible when it is disabled. Runtime snapshot shows disabled.');
    }

    public function test_ready_for_pickup_group_is_to_receive_before_physical_pickup(): void
    {
        $this->order($this->user('buyer'), $this->user('seller'), ['status' => 'READY_FOR_PICKUP']);
        $counts = app(BuyerOrderGroupingService::class)->counts(Order::query());
        $this->assertSame(1, $counts['to_receive']);
        $this->assertSame(0, $counts['to_ship']);
        $this->observe('BUY-TRK-001; A5', 'Ready for Pickup is already To receive; teacher grouping starts this at Picked Up.');
    }

    public function test_email_change_is_staged_but_expires_in_ten_minutes(): void
    {
        $this->freezeTime();
        $buyer = $this->user('buyer');
        $old = $buyer->email;
        app(BuyerEmailChangeService::class)->request($buyer, 'new-audit@example.test', 'AuditPassword123!');
        $challenge = DB::table('buyer_email_changes')->where('user_id', $buyer->id)->first();
        $this->assertSame(now()->addMinutes(10)->toDateTimeString(), $challenge->expires_at);
        $this->assertSame($old, $buyer->fresh()->email);
        $this->observe('BUY-ACCT-006,007', 'Email change now stages a verified challenge and leaves the old email in effect; challenge expiry is 10 minutes, not the teacher five minutes. Mail is faked.');
    }

    private function user(string $role): User
    {
        Auth::forgetGuards();
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'AuditPassword123!', 'first_name' => 'Audit', 'last_name' => 'Synthetic', 'contact_no' => '09123456789', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now()]);
    }

    private function order(User $buyer, User $seller, array $extra = []): Order
    {
        return Order::forceCreate($extra + ['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'AUDIT-'.Str::random(12), 'status' => 'PLACED', 'payment_method' => 'COD', 'subtotal' => 100, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'recipient_name' => 'Synthetic Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Synthetic address']);
    }

    private function observe(string $criteria, string $finding): void
    {
        file_put_contents(__DIR__.'/probe-observations.jsonl', json_encode(['criteria' => $criteria, 'observed' => $finding], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
