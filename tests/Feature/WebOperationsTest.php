<?php

namespace Tests\Feature;

use App\Models\DirectConversation;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\ParcelEvidence;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\SellerPromotion;
use App\Models\StaffProfileRevision;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutPricingService;
use App\Services\PlacePurchaseService;
use App\Services\SellerPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_staff_changes_are_reviewed_without_overwriting_verified_information(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier');
        $admin = $this->user('logistics_admin');
        $this->as($rider)->patch(route('staff.update', $rider), [
            'first_name' => $rider->first_name, 'last_name' => $rider->last_name, 'contact_no' => $rider->contact_no,
            'plate_number' => 'NEW 456', 'vehicle_category' => 'two_wheel', 'vehicle_description' => 'Blue delivery motorcycle',
            'avatar' => UploadedFile::fake()->image('rider.png', 200, 200),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->assertNull($rider->fresh()->plate_number);
        $this->assertNull($rider->fresh()->profile_avatar_path);
        $this->assertSame('pending', $revision->status);
        $this->assertSame(1, $admin->notifications()->count());
        $this->as($rider)->patch(route('staff.update', $rider), ['first_name' => 'Changed', 'last_name' => $rider->last_name, 'contact_no' => $rider->contact_no])->assertSessionHasErrors('profile');
        $this->as($this->user('sorting_center'))->patch(route('staff.review', $revision), ['decision' => 'approve'])->assertForbidden();
        $this->as($admin)->get(route('staff.reviews'))->assertOk()->assertSee('NEW 456');
        $this->patch(route('staff.review', $revision), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('NEW 456', $rider->fresh()->plate_number);
        $this->assertSame('NEW456', $rider->fresh()->canonical_plate);
        Storage::disk('private')->assertExists($rider->fresh()->profile_avatar_path);
        $this->assertSame('approved', $revision->fresh()->status);
        $this->as($this->user('buyer'))->get(route('staff.show', $rider))->assertForbidden();
        $related = $this->user('buyer');
        $order = $this->order($related, $this->user('seller'));
        $order->forceFill(['pickup_courier_id' => $rider->id])->save();
        $this->as($related)->get(route('staff.show', $rider))->assertOk()->assertSee('NEW 456')->assertDontSee('Update history');
        $this->get(route('staff.avatar', $rider))->assertOk();
        $this->as($rider)->patch(route('account.appearance.update'), [])->assertRedirectToRoute('staff.show', $rider);
    }

    public function test_rejected_staff_updates_keep_history_and_vehicle_endpoint_cannot_bypass_review(): void
    {
        $rider = $this->user('courier');
        $admin = $this->user('logistics_admin');
        $this->as($admin)->patch(route('coverage.vehicle', $rider), ['vehicle_category' => 'four_wheel', 'vehicle_description' => 'Delivery van', 'reason' => 'Verified proposed replacement'])->assertRedirect()->assertSessionHasNoErrors();
        $revision = StaffProfileRevision::firstOrFail();
        $this->assertNull($rider->fresh()->vehicle_description);
        $this->patch(route('staff.review', $revision), ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->flushSession();
        $this->patch(route('staff.review', $revision), ['decision' => 'reject', 'reason' => 'Upload the matching registration first.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $revision->fresh()->status);
        $this->assertNull($rider->fresh()->vehicle_description);
        $this->assertNull($revision->fresh()->pending_user_id);
        $this->assertDatabaseHas('administrative_audits', ['action' => 'staff_profile_rejected']);
    }

    public function test_chat_attachments_are_private_and_invalid_extensions_are_rejected(): void
    {
        Storage::fake('private');
        $seller = $this->user('seller');
        $admin = $this->user('admin');
        $conversation = DirectConversation::create(['first_user_id' => $seller->id, 'second_user_id' => $admin->id]);
        $this->as($seller)->post(route('chat.store', $conversation), ['panel' => 1, 'attachments' => [UploadedFile::fake()->image('parcel.png')]])->assertRedirect()->assertSessionHasNoErrors();
        $attachment = MessageAttachment::firstOrFail();
        Storage::disk('private')->assertExists($attachment->path);
        $this->as($admin)->get(route('chat.show', [$conversation, 'panel' => 1]))->assertOk()->assertSee('parcel.png');
        $this->get(route('chat.attachments.show', $attachment))->assertOk()->assertDownload('parcel.png');
        $this->get(route('chat.attachments.show', [$attachment, 'preview' => 1]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->as($this->user('buyer'))->get(route('chat.attachments.show', $attachment))->assertForbidden();
        $this->as($seller)->post(route('chat.store', $conversation), ['attachments' => [UploadedFile::fake()->image('payload.php')]])->assertSessionHasErrors('attachments.0');
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_voucher_unique_recipient_cap_is_independent_of_redemptions_and_preserves_claims(): void
    {
        $seller = $this->user('seller');
        $voucher = $this->voucher($seller, ['recipient_limit' => 1, 'per_buyer_limit' => 2]);
        $buyer = $this->user('buyer');
        $this->as($buyer)->post(route('buyer.vouchers.claim', $voucher))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('buyer.vouchers.claim', $voucher))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $voucher->fresh()->claimed_count);
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->as($this->user('buyer'))->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasErrors('voucher_code');
        $this->assertSame(1, DB::table('voucher_claims')->count());
        $this->as($seller)->patch(route('seller.vouchers.limits', $voucher), ['recipient_limit' => 2, 'per_buyer_limit' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->delete(route('seller.vouchers.destroy', $voucher))->assertRedirect();
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'is_active' => false]);
        $this->assertSame(1, DB::table('voucher_claims')->count());
    }

    public function test_shared_checkout_enforces_new_voucher_rules_and_replays_only_one_redemption(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $voucher = $this->voucher($seller, ['recipient_limit' => 1, 'per_buyer_limit' => 1]);
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $quote = app(CheckoutPricingService::class)->quote($cart, $voucher);
        $token = (string) Str::uuid();
        $place = fn ($person, $key) => app(PlacePurchaseService::class)->place($person, $key, hash('sha256', 'request'), $cart, $quote['hash'], $voucher->id, $this->address());
        $purchase = $place($buyer, $token);
        $this->assertSame($purchase->id, $place($buyer, $token)->id);
        $this->assertSame(1, $voucher->fresh()->claimed_count);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame(1, DB::table('voucher_claims')->value('redemption_count'));
        try {
            $place($buyer, (string) Str::uuid());
            $this->fail('The buyer limit must be enforced.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('voucher_code', $e->errors());
        }
        try {
            $place($this->user('buyer'), (string) Str::uuid());
            $this->fail('The recipient cap must be enforced.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('voucher_code', $e->errors());
        }
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_shop_closure_blocks_new_purchases_and_scheduled_return_reopens_the_shop(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $product = $this->product($seller);
        $existing = $this->order($buyer, $seller);
        $this->as($seller)->patch(route('seller.availability.update'), ['shop_availability' => 'vacation', 'shop_unavailable_from' => now()->subMinute()->toDateTimeString(), 'shop_returns_at' => now()->addDay()->toDateTimeString()])->assertRedirect()->assertSessionHasNoErrors();
        $this->as($buyer)->get(route('product.show', $product))->assertOk()->assertSee('Seller on vacation.');
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->assertSame('PLACED', $existing->fresh()->status);
        $this->assertFalse($seller->fresh()->acceptsWebOrders());
        $this->travel(2)->days();
        $this->assertTrue($seller->fresh()->acceptsWebOrders());
    }

    public function test_old_voucher_orders_count_toward_new_recipient_limits(): void
    {
        $this->freezeTime();
        $seller = $this->user('seller');
        $first = $this->user('buyer');
        $second = $this->user('buyer');
        $voucher = $this->voucher($seller, ['used_count' => 2]);
        $this->order($first, $seller)->forceFill(['voucher_code' => $voucher->code])->save();
        $this->order($second, $seller)->forceFill(['voucher_code' => $voucher->code])->save();
        $this->as($seller)->patch(route('seller.vouchers.limits', $voucher), ['recipient_limit' => 1])->assertSessionHasErrors('recipient_limit');
        $this->flushSession();
        $this->patch(route('seller.vouchers.limits', $voucher), ['recipient_limit' => 2, 'per_buyer_limit' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $voucher->fresh()->claimed_count);
        $this->assertSame(2, DB::table('voucher_claims')->where('voucher_id', $voucher->id)->count());
        $this->assertSame(2, (int) DB::table('voucher_claims')->sum('redemption_count'));
        $this->as($this->user('buyer'))->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasErrors('voucher_code');
    }

    public function test_promotions_target_opted_in_followers_once_and_obey_weekly_limits(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $optedOut = $this->user('buyer');
        $optedOut->forceFill(['seller_promotions_enabled' => false])->save();
        $outsider = $this->user('buyer');
        $seller->shopFollowers()->attach([$buyer->id, $optedOut->id]);
        $this->as($seller)->post(route('seller.promotions.store'), ['title' => 'Weekend offer', 'message' => 'Explore our new collection.', 'starts_at' => now()->startOfDay()->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString()])->assertRedirect()->assertSessionHasNoErrors();
        $service = app(SellerPromotionService::class);
        $this->assertSame(1, $service->deliver());
        $this->assertSame(0, $service->deliver());
        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertSame(0, $optedOut->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $this->as($buyer)->get(route('notifications.index', ['category' => 'promotions']))->assertOk()->assertSee('ADVERTISEMENT');
        for ($i = 0; $i < 4; $i++) {
            SellerPromotion::create(['seller_id' => $seller->id, 'title' => 'Offer '.$i, 'message' => 'A follow-up offer.', 'starts_at' => now(), 'expires_at' => now()->addDay()]);
        }
        $this->assertSame(2, $service->deliver());
        $this->assertSame(3, DB::table('promotion_deliveries')->where('buyer_id', $buyer->id)->count());
        $this->as($this->user('seller'))->delete(route('seller.promotions.cancel', SellerPromotion::first()))->assertForbidden();
        $this->as($seller)->post(route('seller.promotions.store'), ['title' => 'Another campaign', 'message' => 'Too many.', 'starts_at' => now()->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString()])->assertSessionHasErrors('title');
    }

    public function test_parcel_photos_require_an_owned_accepted_event_and_are_idempotent(): void
    {
        Storage::fake('private');
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $order = $this->order($buyer, $seller);
        $order->forceFill(['pickup_courier_id' => $rider->id])->save();
        $event = ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $rider->id, 'event_type' => 'pickup_received', 'status' => 'PICKED_UP', 'location' => 'Majayjay']);
        $photo = UploadedFile::fake()->image('handoff.png');
        $bytes = file_get_contents($photo->getRealPath());
        $upload = fn () => ['event_type' => 'tracking', 'event_id' => $event->id, 'photos' => [UploadedFile::fake()->createWithContent('handoff.png', $bytes)]];
        $this->as($rider)->post(route('parcel-evidence.store', $order), $upload())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('parcel-evidence.store', $order), $upload())->assertRedirect()->assertSessionHasNoErrors();
        $evidence = ParcelEvidence::firstOrFail();
        $this->assertSame(1, ParcelEvidence::count());
        $this->assertSame('PLACED', $order->fresh()->status);
        $this->as($buyer)->get(route('parcel-evidence.show', $order))->assertOk()->assertSee('Tracking and handoffs');
        $this->get(route('parcel-evidence.photo', $evidence))->assertOk();
        $this->post(route('parcel-evidence.store', $order), $upload())->assertForbidden();
        $this->as($this->user('buyer'))->get(route('parcel-evidence.photo', $evidence))->assertForbidden();
        $rejected = DB::table('scan_events')->insertGetId(['order_id' => $order->id, 'actor_id' => $rider->id, 'station' => 'intake', 'result' => 'rejected', 'method' => 'manual', 'created_at' => now()]);
        $this->as($rider)->post(route('parcel-evidence.store', $order), ['event_type' => 'scan', 'event_id' => $rejected, 'photos' => [UploadedFile::fake()->image('invalid.png')]])->assertForbidden();
    }

    public function test_seller_can_manage_combinations_and_buyer_receives_the_selected_price_stock_and_image(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller');
        $this->as($seller)->get(route('seller.products.create'))->assertOk()->assertSee('Generate combinations');
        $this->post(route('seller.products.store'), ['name' => 'Combination shirt', 'category' => "Women's Apparel", 'price' => 100, 'stock' => 99, 'variations_present' => 1,
            'variations' => [['type' => 'Size / Color', 'value' => 'Small / Black', 'sku' => 'SHIRT-S-B', 'price_adjustment' => 20, 'stock' => 3, 'image' => UploadedFile::fake()->image('black.png')],
                ['type' => 'Size / Color', 'value' => 'Large / White', 'sku' => 'SHIRT-L-W', 'price_adjustment' => 30, 'stock' => 2, 'image_path' => 'documents/not-owned.jpg']]])->assertRedirect()->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertSame(5, $product->stock);
        $variant = $product->variations()->where('sku', 'SHIRT-S-B')->firstOrFail();
        Storage::disk('public')->assertExists($variant->image_path);
        $this->assertNull($product->variations()->where('sku', 'SHIRT-L-W')->firstOrFail()->image_path);
        $product->forceFill(['compliance_status' => 'approved'])->save();
        $buyer = $this->user('buyer');
        $this->as($buyer)->get(route('product.show', $product))->assertOk()->assertSee('Small / Black')->assertSee('data-image', false);
        $this->post(route('cart.add', $product), ['quantity' => 4, 'variation_id' => $variant->id])->assertSessionHasErrors();
        $this->flushSession();
        $this->post(route('cart.add', $product), ['quantity' => 1, 'variation_id' => $variant->id])->assertRedirect()->assertSessionHasNoErrors();
        $quote = app(CheckoutPricingService::class)->quote([['product_id' => $product->id, 'variation_id' => $variant->id, 'quantity' => 1]]);
        $this->assertSame(12000, array_values($quote['lines'])[0]['unit_cents']);
        $this->assertSame('Size / Color: Small / Black', array_values($quote['lines'])[0]['variation_info']);
        $this->as($seller)->put(route('seller.products.update', $product), ['name' => $product->name, 'category' => $product->category, 'price' => 100, 'stock' => 5,
            'variations' => [['type' => 'Color', 'value' => 'Black', 'stock' => 1], ['type' => 'Color', 'value' => 'black', 'stock' => 1]]])->assertSessionHasErrors('variations');
    }

    public function test_new_categories_include_legacy_aliases_without_losing_old_products(): void
    {
        $seller = $this->user('seller');
        $legacy = $this->product($seller, ['category' => 'Electronics']);
        $this->assertCount(12, config('web-commerce.categories'));
        $this->get(route('search.index', ['category' => 'Electronics and Gadgets']))->assertOk()->assertSee($legacy->name);
        $this->get(route('home'))->assertOk()->assertSee('Pet Supplies')->assertSee('Jewelry and Watches');
        $this->assertSame('Electronics', $legacy->fresh()->category);
    }

    public function test_web_only_fields_do_not_expand_existing_mobile_product_response_shapes(): void
    {
        $seller = $this->user('seller');
        $seller->forceFill(['last_web_activity_at' => now(), 'shop_availability' => 'vacation'])->save();
        $product = $this->product($seller);
        $product->variations()->create(['type' => 'Color', 'value' => 'Black', 'stock' => 2, 'price_adjustment' => 0, 'sku' => 'PRIVATE-WEB-SKU', 'image_path' => 'products/variant.png']);
        $data = $this->getJson('/api/products/'.$product->id)->assertOk()->json('data');
        $this->assertSame('Black', $data['variations'][0]['value']);
        $this->assertArrayNotHasKey('sku', $data['variations'][0]);
        $this->assertArrayNotHasKey('image_path', $data['variations'][0]);
        $this->assertArrayNotHasKey('last_web_activity_at', $data['seller']);
        $this->assertArrayNotHasKey('shop_availability', $data['seller']);
    }

    public function test_web_activity_is_throttled_and_polling_does_not_keep_a_seller_online(): void
    {
        $seller = $this->user('seller');
        $this->as($seller)->get(route('seller.dashboard'))->assertOk();
        $first = $seller->fresh()->last_web_activity_at;
        $this->travel(2)->minutes();
        $this->get(route('seller.dashboard'))->assertOk();
        $this->assertTrue($first->equalTo($seller->fresh()->last_web_activity_at));
        $this->travel(20)->days();
        $this->getJson(route('communications.counts'))->assertOk();
        $this->assertTrue($first->equalTo($seller->fresh()->last_web_activity_at));
        $this->as($this->user('buyer'))->get(route('shops.show', $seller))->assertOk()->assertSee('This seller has been inactive for');
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    public function test_new_web_pages_render_for_their_authorized_roles(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $rider = $this->user('courier');
        $admin = $this->user('logistics_admin');
        $commerce = $this->user('ecommerce_admin');
        $this->as($buyer)->get(route('buyer.vouchers.index'))->assertOk();
        $this->get(route('parcel-evidence.index'))->assertOk();
        $this->as($seller)->get(route('seller.promotions.index'))->assertOk();
        $this->get(route('seller.availability.edit'))->assertOk();
        $this->get(route('seller.vouchers.index'))->assertOk();
        $this->as($rider)->get(route('staff.index'))->assertOk();
        $this->get(route('staff.show', $rider))->assertOk();
        $this->as($admin)->get(route('staff.reviews'))->assertOk();
        $this->as($commerce)->get(route('admin.promotions.index'))->assertOk();
        $this->as($buyer)->get(route('staff.reviews'))->assertForbidden();
        $this->get(route('seller.promotions.index'))->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Test', 'last_name' => ucfirst($role), 'sex' => 'Other', 'contact_no' => '09'.fake()->unique()->numerify('#########'), 'birthday' => '1990-01-01', 'age' => 36, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => '45 Market Road', 'business_name' => $role === 'seller' ? 'Test Shop '.Str::random(5) : null]);
    }

    private function product(User $seller, array $extra = []): Product
    {
        return Product::forceCreate(array_merge(['user_id' => $seller->id, 'name' => 'Test Product '.Str::random(5), 'category' => 'Pet Supplies', 'price' => 100, 'stock' => 10, 'compliance_status' => 'approved', 'is_archived' => false], $extra));
    }

    private function voucher(User $seller, array $extra = []): Voucher
    {
        return Voucher::create(array_merge(['seller_id' => $seller->id, 'code' => Str::upper(Str::random(8)), 'type' => 'fixed', 'value' => 10, 'min_spend' => 0, 'is_active' => true], $extra));
    }

    private function address(): array
    {
        return ['recipient_name' => 'Test Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => '45 Market Road'];
    }

    private function order(User $buyer, User $seller): Order
    {
        return Order::forceCreate($this->address() + ['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'WEB-'.Str::upper(Str::random(10)), 'subtotal' => 100, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'PLACED']);
    }
}
