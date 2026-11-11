<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\DeliveryCodeService;
use App\Services\DirectMessagingService;
use App\Services\LogisticsExceptionService;
use App\Services\ParcelLoadService;
use App\Services\ParcelTransferService;
use App\Services\RiderBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModuleRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    public function test_buyer_api_uses_financial_item_and_tracking_snapshots_and_denies_other_buyers(): void
    {
        $order = $this->order(['discount_amount' => 20, 'total_amount' => 130]);
        $product = $this->product($order->seller, ['name' => 'Changed live name', 'price' => 900]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'Purchased name', 'unit_price' => 50, 'item_total' => 100, 'quantity' => 2]);
        ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $order->seller_id, 'event_type' => 'prepared', 'status' => $order->status, 'notes' => 'Packed with protective wrapping.']);
        $token = ApiToken::createTokenForUser($order->buyer)['plainTextToken'];
        $this->withToken($token)->getJson('/api/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.discount', 20)->assertJsonPath('data.total_amount', 130)
            ->assertJsonPath('data.items.0.name', 'Purchased name')->assertJsonPath('data.items.0.price', 50)
            ->assertJsonPath('data.items.0.total', 100)->assertJsonPath('data.tracking_timeline.0.description', 'Packed with protective wrapping.')
            ->assertJsonPath('data.courier', null);
        $this->withToken(ApiToken::createTokenForUser($this->user('buyer'))['plainTextToken'])->getJson('/api/orders/'.$order->id)->assertNotFound();
    }

    public function test_origin_intake_and_sorting_preserve_destination_and_require_both_transfer_scans(): void
    {
        [$source, $destination, $area, $local, $driver] = $this->network();
        $order = $this->order(['status' => 'PICKED_UP', 'sorting_center_id' => null, 'destination_area_id' => $area->id, 'pickup_courier_id' => $local->id]);
        $stranger = $this->user('sorting_center');
        $this->signIn($stranger)->post(route('logistics.orders.receive', $order))->assertForbidden();
        $this->signIn($source)->get(route('logistics.intake'))->assertOk()->assertSee($order->order_number);
        $this->post(route('logistics.orders.receive', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.sort', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($source->id, $order->fresh()->sorting_center_id);
        $this->assertSame($area->id, $order->fresh()->destination_area_id);
        $admin = $this->user('logistics_admin');
        $transfer = app(ParcelTransferService::class)->plan($order->fresh(), $admin, $destination, 'Source to destination routing.');
        $this->signIn($source)->post(route('parcel-transfers.release', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'transport_reference' => 'LINEHAUL-ONE', 'carrier_rider_id' => $driver->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.sort', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($destination->id, $order->fresh()->sorting_center_id);
        $this->assertSame('SORTED', $order->fresh()->status);
        $this->assertSame(0, app(ParcelLoadService::class)->summary($driver)['count']);
        $deliveryRider = $this->user('courier', ['logistics_owner_id' => $destination->id]);
        $deliveryRider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $deliveryRider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($deliveryRider)->post(route('courier.orders.accept-assignment', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($destination)->post(route('logistics.orders.releaseToRider', $order), ['rider_badge' => 'EZR:'.app(RiderBadgeService::class)->ensure($deliveryRider), 'parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($deliveryRider)->post(route('courier.orders.startDelivery', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        Storage::fake('private');
        $this->patch(route('courier.orders.completeDelivery', $order), ['recipient_confirmation' => 'Buyer', 'delivery_code' => app(DeliveryCodeService::class)->reveal($order->fresh()), 'cod_collected_amount' => '150.00', 'proof_file' => UploadedFile::fake()->image('delivered.jpg')])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($order->buyer)->post(route('buyer.orders.confirm', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('COMPLETED', $order->fresh()->status);
    }

    public function test_transport_participants_can_message_and_view_evidence_but_unrelated_drivers_cannot(): void
    {
        [$source, $destination, $area, $local, $driver] = $this->network();
        $order = $this->order(['sorting_center_id' => $source->id, 'destination_area_id' => $area->id]);
        $transfer = app(ParcelTransferService::class)->plan($order, $this->user('logistics_admin'), $destination, 'Assigned carrier access.');
        app(ParcelTransferService::class)->release($transfer, $source, 'EZP:'.$order->parcel_code, 'ACCESS-ONE', $driver);
        $this->assertTrue(app(DirectMessagingService::class)->canContact($driver, $destination));
        $this->assertTrue(app(DirectMessagingService::class)->canContact($destination, $driver));
        $this->signIn($driver)->get(route('parcel-evidence.show', $order))->assertOk();
        $this->get(route('parcel-transfers.index'))->assertOk()->assertSee('Journey &amp; evidence', false);
        $this->assertTrue(app(AnalyticsService::class)->scope($driver)->whereKey($order->id)->exists());
        $this->signIn($this->user('courier'))->get(route('parcel-evidence.show', $order))->assertForbidden();
    }

    public function test_suspended_carrier_recovery_records_one_replacement_without_resetting_custody(): void
    {
        [$source, $destination, $area, $local, $driver] = $this->network();
        $admin = $this->user('logistics_admin');
        $order = $this->order(['sorting_center_id' => $source->id, 'destination_area_id' => $area->id]);
        $service = app(ParcelTransferService::class);
        $transfer = $service->plan($order, $admin, $destination, 'Suspended carrier recovery.');
        $service->release($transfer, $source, 'EZP:'.$order->parcel_code, 'RECOVERY-ONE', $driver);
        $driver->forceFill(['status' => 'suspended'])->save();
        DB::transaction(function () use ($driver, $admin): void {
            app(LogisticsExceptionService::class)->openForRiderSuspension($driver, $admin, 'Suspended during transport.');
            app(LogisticsExceptionService::class)->openForRiderSuspension($driver, $admin, 'Duplicate suspension check.');
        });
        $this->assertDatabaseCount('logistics_exceptions', 1);
        $replacement = $this->user('courier', ['hub_transfer_only' => true, 'logistics_owner_id' => $source->id]);
        $this->signIn($source)->post(route('parcel-transfers.replace-carrier', $transfer), ['carrier_rider_id' => $replacement->id, 'parcel_reference' => 'EZP:'.$order->parcel_code, 'reason' => 'Verified physical handover.'])->assertForbidden();
        $this->signIn($admin)->post(route('parcel-transfers.replace-carrier', $transfer), ['carrier_rider_id' => $replacement->id, 'parcel_reference' => 'WRONG', 'reason' => 'Verified physical handover.'])->assertUnprocessable();
        $this->post(route('parcel-transfers.replace-carrier', $transfer), ['carrier_rider_id' => $replacement->id, 'parcel_reference' => 'EZP:'.$order->parcel_code, 'reason' => 'Verified physical handover.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertSame($replacement->id, $transfer->fresh()->carrier_rider_id);
        $this->assertSame(0, app(ParcelLoadService::class)->summary($driver)['count']);
        $this->assertSame(1, app(ParcelLoadService::class)->summary($replacement)['count']);
        $this->assertDatabaseHas('logistics_exceptions', ['order_id' => $order->id, 'status' => 'RESOLVED', 'new_rider_id' => $replacement->id]);
        $this->signIn($destination)->post(route('parcel-transfers.receive', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, app(ParcelLoadService::class)->summary($replacement)['count']);
    }

    public function test_return_approval_waits_for_assignment_acceptance_buyer_handover_and_label_scan(): void
    {
        $hub = $this->user('sorting_center');
        $oldRider = $this->user('courier', ['logistics_owner_id' => $hub->id]);
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id]);
        $order = $this->order(['status' => 'COMPLETED', 'sorting_center_id' => $hub->id, 'delivery_courier_id' => $oldRider->id, 'delivered_at' => now()]);
        $dispute = Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Damaged Item', 'description' => 'Return the damaged item.', 'status' => 'PENDING']);
        $this->signIn($this->user('ecommerce_admin'))->patch(route('admin.disputes.resolve', $dispute), ['status' => 'REFUND_APPROVED', 'admin_notes' => 'Return and refund authorized.'])->assertRedirect();
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(0, app(ParcelLoadService::class)->summary($oldRider)['count']);
        $this->assertDatabaseCount('delivery_assignments', 0);
        $this->signIn($order->buyer)->get(route('return-pickups.index'))->assertOk()->assertSee($order->order_number);
        $this->signIn($this->user('buyer'))->get(route('return-pickups.index'))->assertOk()->assertDontSee($order->order_number);
        $this->signIn($hub)->post(route('return-pickups.assign', $order), ['rider_id' => $rider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($oldRider)->post(route('return-pickups.accept', $order))->assertForbidden();
        $this->signIn($rider)->post(route('return-pickups.collect', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->post(route('return-pickups.accept', $order))->assertRedirect();
        $this->get(route('return-pickups.index'))->assertOk()->assertSee($order->order_number);
        $this->post(route('return-pickups.collect', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertUnprocessable();
        $this->signIn($this->user('buyer'))->post(route('return-pickups.handover', $order))->assertForbidden();
        $this->signIn($order->buyer)->post(route('return-pickups.handover', $order))->assertRedirect();
        $this->signIn($rider)->post(route('return-pickups.collect', $order), ['parcel_reference' => 'WRONG'])->assertUnprocessable();
        $this->post(route('return-pickups.collect', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('RETURN_IN_TRANSIT', $order->fresh()->status);
        $this->assertSame($rider->id, $order->fresh()->delivery_courier_id);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $rider->id, 'status' => 'active']);
        $this->assertDatabaseHas('refund_executions', ['order_id' => $order->id, 'status' => 'AUTHORIZED']);
        $this->post(route('courier.orders.confirmReturnDelivery', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect()->assertSessionHasNoErrors();
        $this->signIn($order->seller)->post(route('seller.orders.confirmReturn', $order), ['rider_badge' => 'EZR:'.app(RiderBadgeService::class)->ensure($rider)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('RETURNED_TO_SELLER', $order->fresh()->status);
        $this->assertSame(0, app(ParcelLoadService::class)->summary($rider)['count']);
    }

    public function test_seller_dashboard_reports_actual_discounted_earnings_and_ratings(): void
    {
        $order = $this->order(['status' => 'COMPLETED', 'discount_amount' => 20, 'commission_fee' => 10]);
        $product = $this->product($order->seller);
        $this->signIn($order->seller)->get(route('seller.dashboard'))->assertViewHas('stats', fn ($stats) => $stats['rating'] === 'Unrated' && (float) $stats['total_sales'] === 70.0);
        Review::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'product_id' => $product->id, 'rating' => 1, 'comment' => 'Needs improvement.']);
        $this->get(route('seller.dashboard'))->assertViewHas('stats', fn ($stats) => $stats['rating'] === '1.0' && $stats['review_count'] === 1);
        $order->forceFill(['platform_discount_amount' => 10])->save();
        $this->get(route('seller.dashboard'))->assertViewHas('stats', fn ($stats) => (float) $stats['total_sales'] === 80.0);
    }

    public function test_api_quote_accepts_one_shop_bulk_items_and_rejects_mixed_shops_early(): void
    {
        $seller = $this->user('seller');
        $one = $this->product($seller);
        $two = $this->product($seller);
        $other = $this->product($this->user('seller'));
        $this->postJson('/api/cart/quote', ['items' => [['product_id' => $one->id, 'quantity' => 2], ['product_id' => $two->id, 'quantity' => 1]]])->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/cart/quote', ['items' => [['product_id' => $one->id, 'quantity' => 1], ['product_id' => $other->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonPath('success', false)->assertJsonMissingPath('data.checkout_token');
    }

    public function test_expired_license_blocks_outgoing_load_but_renewed_license_is_eligible(): void
    {
        $order = $this->order();
        $rider = $this->user('courier', ['license_expires_at' => today()->subDay()]);
        try {
            DB::transaction(fn () => app(ParcelLoadService::class)->assertFits($order, $rider));
            $this->fail('Expired license was allowed to dispatch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('license_expires_at', $exception->errors());
        }
        $rider->forceFill(['license_expires_at' => today()])->save();
        DB::transaction(fn () => app(ParcelLoadService::class)->assertFits($order, $rider));
        $this->assertSame('approved', $rider->fresh()->status);
    }

    public function test_logistics_applications_are_oldest_first_searchable_and_all_pages_are_reachable(): void
    {
        $pending = collect();
        for ($i = 0; $i < 26; $i++) {
            $pending->push($this->user('courier', ['status' => 'pending', 'first_name' => 'Applicant'.$i, 'created_at' => now()->subMinutes(30 - $i)]));
        }
        $this->signIn($this->user('logistics_admin'))->get(route('logistics-admin.dashboard'))->assertOk()
            ->assertViewHas('pendingUsers', fn ($users) => $users->total() === 26 && $users->first()->id === $pending->first()->id && $users->lastPage() === 2);
        $this->get(route('logistics-admin.dashboard', ['applications_page' => 2]))->assertOk()->assertViewHas('pendingUsers', fn ($users) => $users->count() === 1 && $users->first()->id === $pending->last()->id);
        $this->get(route('logistics-admin.dashboard', ['application_search' => 'Applicant25']))->assertOk()->assertViewHas('pendingUsers', fn ($users) => $users->total() === 1);
    }

    private function user(string $role, array $extra = []): User
    {
        $extra += ['email_verified_at' => now()];

        return User::forceCreate(array_merge(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Test', 'last_name' => $role, 'business_name' => 'Test '.$role, 'contact_no' => '09123456789', 'vehicle_capacity_grams' => 100000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear()], $extra));
    }

    private function order(array $extra = []): Order
    {
        return Order::forceCreate(array_merge(['order_number' => 'REV-'.Str::upper(Str::random(10)), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'recipient_name' => 'Buyer', 'recipient_contact' => '09123456789', 'province' => 'Audit Province', 'municipality' => 'Audit Town', 'barangay' => 'Audit Barangay', 'street_address' => 'Audit street', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'SORTED', 'parcel_weight_grams' => 1000], $extra));
    }

    private function product(User $seller, array $extra = []): Product
    {
        return Product::forceCreate(array_merge(['user_id' => $seller->id, 'name' => 'Test product', 'price' => 100, 'stock' => 20, 'category' => 'Home and Garden', 'compliance_status' => 'approved', 'weight_grams' => 1000], $extra));
    }

    private function network(): array
    {
        $source = $this->user('sorting_center');
        $destination = $this->user('sorting_center');
        $area = Area::create(['code' => 'DEST', 'name' => 'Destination', 'is_active' => true, 'sorting_center_id' => $destination->id]);
        AreaMunicipality::create(['area_id' => $area->id, 'province' => 'Audit Province', 'municipality' => 'Audit Town', 'province_normalized' => 'audit province', 'municipality_normalized' => 'audit town']);

        return [$source, $destination, $area, $this->user('courier', ['logistics_owner_id' => $source->id]), $this->user('courier', ['hub_transfer_only' => true, 'logistics_owner_id' => $source->id])];
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
