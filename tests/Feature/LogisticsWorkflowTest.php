<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\DeliveryAssignment;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelTrackingEvent;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Notifications\OrderWorkflowNotification;
use App\Services\OrderTransitionService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LogisticsWorkflowTest extends TestCase
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

    public function test_pickup_claim_is_exclusive_and_parcel_intake_persists_events(): void
    {
        $courier = $this->user('courier', 'approved');
        $otherCourier = $this->user('courier', 'approved');
        $center = $this->user('sorting_center', 'approved');
        $declinedOrder = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now(), 'pickup_courier_id' => $courier->id]);
        $this->actingAsUser($courier)->post(route('courier.orders.declinePickup', $declinedOrder), ['reason' => 'Outside assigned coverage.'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $declinedOrder->id, 'pickup_courier_id' => null]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $declinedOrder->id, 'event_type' => 'pickup_declined', 'notes' => 'Outside assigned coverage.']);
        $order = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now(), 'pickup_scheduled_for' => now()->addDay(), 'pickup_window' => 'Morning (8 AM–12 PM)']);

        $this->actingAsUser($center)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $courier->id])->assertRedirect();
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_assigned']);
        $this->assertContains('pickup_assigned', $courier->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
        $this->assertContains('pickup_assigned', $order->seller->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
        $this->actingAsUser($courier)->post(route('courier.orders.claim', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'pickup_courier_id' => $courier->id, 'status' => 'READY_FOR_PICKUP']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_accepted']);
        $this->assertContains('pickup_accepted', $order->seller->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());

        $this->actingAsUser($otherCourier)->post(route('courier.orders.claim', $order))->assertForbidden();
        $this->actingAsUser($courier)->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->assertContains('pickup_arrived', $order->seller->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmHandover', $order))->assertRedirect();
        $this->assertContains('seller_handover_confirmed', $courier->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
        $this->actingAsUser($courier)->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PICKED_UP']);

        $this->actingAsUser($center)->post(route('logistics.orders.receive', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $center->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'hub_received']);
    }

    public function test_logistics_can_reassign_only_unclaimed_pickups_and_preserve_history(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $suspendedRider = $this->user('courier', 'suspended');
        $replacementRider = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'READY_FOR_PICKUP',
            'pickup_requested_at' => now(),
            'pickup_courier_id' => $suspendedRider->id,
        ]);

        $this->actingAsUser($center)->get(route('logistics.pickupRequests'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Reassign pickup');
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $replacementRider->id])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'pickup_courier_id' => $replacementRider->id]);
        $this->assertDatabaseHas('parcel_tracking_events', [
            'order_id' => $order->id,
            'event_type' => 'pickup_reassigned',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $suspendedRider->id,
            'type' => OrderWorkflowNotification::class,
        ]);
        $this->assertContains('pickup_assigned', $replacementRider->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());

        $order->forceFill(['pickup_claimed_at' => now()])->save();
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $this->user('courier', 'approved')->id])
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'pickup_courier_id' => $replacementRider->id]);
    }

    public function test_parcel_scan_uses_the_same_error_for_unknown_and_ineligible_references(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $ineligibleOrder = $this->order(['status' => 'PLACED']);

        $this->actingAsUser($center)
            ->from(route('logistics.intake'))
            ->post(route('logistics.scan'), ['reference' => 'EZC-UNKNOWN'])
            ->assertRedirect(route('logistics.intake'))
            ->assertSessionHasErrors(['reference' => 'The parcel reference could not be processed.']);

        $this->actingAsUser($center)
            ->from(route('logistics.intake'))
            ->post(route('logistics.scan'), ['reference' => $ineligibleOrder->order_number])
            ->assertRedirect(route('logistics.intake'))
            ->assertSessionHasErrors(['reference' => 'The parcel reference could not be processed.']);

        $this->assertDatabaseHas('orders', ['id' => $ineligibleOrder->id, 'status' => 'PLACED']);
    }

    public function test_logistics_order_search_treats_like_wildcards_as_literal_characters(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $intakeMatch = $this->order(['status' => 'PICKED_UP', 'order_number' => 'EZC-TEST_100%']);
        $this->order(['status' => 'PICKED_UP', 'order_number' => 'EZC-TESTX100Y']);
        $trackingMatch = $this->order(['status' => 'OUT_FOR_DELIVERY', 'order_number' => 'EZC-TRACK_77%']);
        $this->order(['status' => 'OUT_FOR_DELIVERY', 'order_number' => 'EZC-TRACKX77Y']);

        $this->actingAsUser($center)
            ->get(route('logistics.intake', ['search' => 'TEST_100%']))
            ->assertOk()
            ->assertViewHas('parcels', fn ($parcels): bool => collect($parcels->items())->pluck('id')->all() === [$intakeMatch->id]);
        $this->get(route('logistics.tracking', ['search' => 'TRACK_77%']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => collect($orders->items())->pluck('id')->all() === [$trackingMatch->id]);
    }

    public function test_dispute_submission_rechecks_order_eligibility_before_create(): void
    {
        $order = $this->order(['status' => 'PREPARING']);

        $this->actingAsUser($order->buyer)
            ->post(route('buyer.orders.dispute.store', $order), [
                'reason' => 'Other',
                'description' => 'This submission should be rejected before delivery.',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('disputes', ['order_id' => $order->id]);
    }

    public function test_order_cannot_receive_a_second_dispute_submission(): void
    {
        Storage::fake('private');
        $order = $this->order(['status' => 'COMPLETED']);
        $payload = [
            'reason' => 'Other',
            'description' => 'A sufficiently detailed dispute description.',
            'evidence_file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ];

        $this->actingAsUser($order->buyer)
            ->post(route('buyer.orders.dispute.store', $order), $payload)
            ->assertRedirect(route('buyer.dashboard'));
        $evidencePath = Dispute::query()->where('order_id', $order->id)->value('evidence_path');
        $this->assertIsString($evidencePath);
        Storage::disk('private')->assertExists($evidencePath);

        $this->actingAsUser($order->buyer)
            ->post(route('buyer.orders.dispute.store', $order), [
                'reason' => 'Other',
                'description' => 'A second sufficiently detailed dispute description.',
                'evidence_file' => UploadedFile::fake()->create('second-evidence.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable();

        $this->assertSame(1, Dispute::query()->where('order_id', $order->id)->count());
        Storage::disk('private')->assertExists($evidencePath);
        $this->assertCount(1, Storage::disk('private')->allFiles('disputes/evidence'));
    }

    public function test_database_rejects_multiple_disputes_for_one_order(): void
    {
        $order = $this->order(['status' => 'COMPLETED']);
        $dispute = [
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'reason' => 'Other',
            'description' => 'A sufficiently detailed dispute description.',
            'status' => 'PENDING',
        ];
        Dispute::query()->create($dispute);

        $this->expectException(QueryException::class);

        Dispute::query()->create($dispute);
    }

    public function test_unique_dispute_migration_stops_when_existing_duplicate_history_needs_review(): void
    {
        $order = $this->order(['status' => 'COMPLETED']);
        Schema::table('disputes', fn (Blueprint $table) => $table->dropUnique('disputes_order_id_unique'));
        $dispute = [
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'reason' => 'Other',
            'description' => 'Legacy duplicate dispute data needs review.',
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('disputes')->insert([$dispute, $dispute]);
        $migration = require database_path('migrations/2026_10_05_013448_add_unique_order_id_to_disputes_table.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add one-dispute-per-order constraint');

        $migration->up();
    }

    public function test_professor_order_flow_completes_from_seller_acceptance_through_buyer_confirmation(): void
    {
        Storage::fake('private');
        $this->post(route('register.post'), [
            'first_name' => 'Professor', 'last_name' => 'Flow', 'sex' => 'Other',
            'email' => 'professor-flow@example.test', 'contact_no' => '09123456789',
            'birthday' => '1990-01-01', 'province' => 'Laguna', 'municipality' => 'Majayjay',
            'barangay' => 'Poblacion', 'street_address' => 'Test street',
            'id_document' => UploadedFile::fake()->image('buyer-id.png'),
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'admin', 'status' => 'approved',
        ])->assertRedirect(route('login'));
        $buyer = User::query()->where('email', 'professor-flow@example.test')->firstOrFail();
        $this->assertSame('pending', $buyer->status);
        $this->assertSame('buyer', $buyer->role);

        $admin = $this->user('admin', 'approved');
        $this->actingAsUser($admin)->post(route('admin.registrations.approve', $buyer))->assertRedirect();
        $buyer->refresh();
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'password123'])
            ->assertRedirect(route('buyer.dashboard'));

        $seller = $this->user('seller', 'approved');
        $product = Product::query()->create([
            'user_id' => $seller->id,
            'name' => 'Professor Flow Product',
            'description' => 'Approved test listing',
            'category' => 'Test',
            'price' => 100,
            'stock' => 3,
            'is_archived' => false,
        ]);
        $product->forceFill(['compliance_status' => 'approved'])->save();
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect(route('cart.index'));
        $this->post(route('checkout.process'), [
            'recipient_name' => 'Professor Flow',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '2 Test Street',
            'payment_method' => 'GCash',
            'buyer_id' => $admin->id,
            'seller_id' => $admin->id,
            'delivery_courier_id' => $admin->id,
            'status' => 'COMPLETED',
            'total_amount' => 0,
            'order_number' => 'EZC-SPOOFED',
        ])->assertRedirect(route('buyer.dashboard'));
        $order = Order::query()->where('buyer_id', $buyer->id)->where('seller_id', $seller->id)->firstOrFail();
        $this->assertSame('PLACED', $order->status);
        $this->assertSame(150.0, (float) $order->total_amount);
        $this->assertNull($order->delivery_courier_id);
        $this->assertNotSame('EZC-SPOOFED', $order->order_number);
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);

        $this->actingAsUser($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertRedirect();
        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'PREPARING'])->assertRedirect();
        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'READY_FOR_PICKUP'])->assertRedirect();
        $this->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => now()->addDay()->format('Y-m-d\\TH:i'),
            'pickup_window' => 'Morning (8 AM–12 PM)',
        ])->assertRedirect();
        $this->assertContains('pickup_requested', $center->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());

        $this->actingAsUser($center)->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $rider->id])->assertRedirect();
        $this->actingAsUser($rider)->post(route('courier.orders.claim', $order))->assertRedirect();
        $this->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->actingAsUser($seller)->post(route('seller.orders.confirmHandover', $order))->assertRedirect();
        $this->actingAsUser($rider)->post(route('courier.orders.confirmPickup', $order))->assertRedirect();
        $this->actingAsUser($center)->post(route('logistics.orders.receive', $order))->assertRedirect();
        $this->post(route('logistics.orders.sort', $order), [])->assertRedirect();
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertRedirect();
        $this->actingAsUser($center)->get(route('logistics.dispatch'))->assertSee('Confirm hub handoff to rider');
        $this->actingAsUser($rider)->get(route('courier.dashboard'))->assertSee('Waiting for Logistics hub release');
        $this->actingAsUser($rider)->post(route('logistics.orders.releaseToRider', $order))->assertForbidden();
        $this->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->actingAsUser($center)->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->get(route('logistics.dispatch'))->assertSee('Hub release recorded');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ASSIGNED_TO_RIDER']);
        $this->assertNotNull($order->fresh()->hub_released_at);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'hub_released_to_rider', 'actor_id' => $center->id]);
        $this->post(route('logistics.orders.releaseToRider', $order))->assertUnprocessable();
        $this->actingAsUser($rider)->post(route('courier.orders.startDelivery', $order))->assertRedirect();
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Test Buyer',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertRedirect();
        $this->actingAsUser($buyer)->post(route('buyer.orders.confirm', $order))->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'outcome' => 'delivered']);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $rider->id, 'status' => 'completed']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'order_completed']);
    }

    public function test_buyer_registration_rejects_address_and_name_values_longer_than_database_columns(): void
    {
        Storage::fake('private');

        $this->post(route('register.post'), [
            'first_name' => str_repeat('A', 101),
            'last_name' => 'Bounded',
            'sex' => 'Other',
            'email' => 'bounded-registration@example.test',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'province' => str_repeat('P', 256),
            'municipality' => str_repeat('M', 256),
            'barangay' => str_repeat('B', 256),
            'street_address' => str_repeat('S', 256),
            'id_document' => UploadedFile::fake()->image('buyer-id.png'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['first_name', 'province', 'municipality', 'barangay', 'street_address']);

        $this->assertDatabaseMissing('users', ['email' => 'bounded-registration@example.test']);
    }

    public function test_sorting_and_dispatch_require_destination_and_eligible_rider(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'AT_SORTING_CENTER', 'province' => '  LAGUNA  ', 'municipality' => '  MAJAYJAY  ']);

        $this->actingAsUser($center)->post(route('logistics.orders.sort', $order), ['delivery_area' => 'Untrusted free text'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'destination_area_id' => $order->destination_area_id, 'delivery_area' => 'Majayjay']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'sorted']);

        $pendingRider = $this->user('courier', 'pending');
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $pendingRider->id])->assertUnprocessable();

        $wrongAreaRider = $this->user('courier', 'approved', ['assigned_area' => 'Calamba']);
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $wrongAreaRider->id])->assertUnprocessable();

        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_courier_id' => $rider->id, 'status' => 'ASSIGNED_TO_RIDER']);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $rider->id, 'status' => 'active', 'assigned_by' => $center->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'rider_assigned']);
    }

    public function test_logistics_cannot_reassign_a_delivery_after_hub_handoff(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $currentRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $replacementRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $order = $this->order([
            'status' => 'ASSIGNED_TO_RIDER',
            'delivery_courier_id' => $currentRider->id,
            'assigned_at' => now()->subMinute(),
            'hub_released_at' => now(),
        ]);
        DeliveryAssignment::query()->create([
            'order_id' => $order->id,
            'active_order_id' => $order->id,
            'rider_id' => $currentRider->id,
            'status' => 'active',
            'assigned_at' => $order->assigned_at,
        ]);

        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), [
            'delivery_courier_id' => $replacementRider->id,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'ASSIGNED_TO_RIDER',
            'delivery_courier_id' => $currentRider->id,
        ]);
        $this->get(route('logistics.dispatch'))
            ->assertOk()
            ->assertSee('Confirm physical recovery before dispatching this parcel to another rider');

        $this->post(route('logistics.orders.recoverReleasedParcel', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'SORTED',
            'delivery_courier_id' => null,
            'hub_released_at' => null,
        ]);
        $this->assertNotNull($order->fresh()->delivery_recovered_at);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'rider_id' => $currentRider->id,
            'status' => 'reassigned',
            'active_order_id' => null,
        ]);
        $this->assertDatabaseHas('parcel_tracking_events', [
            'order_id' => $order->id,
            'event_type' => 'delivery_parcel_recovered_at_hub',
            'actor_id' => $center->id,
        ]);

        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $replacementRider->id])->assertRedirect();
        $this->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'ASSIGNED_TO_RIDER',
            'delivery_courier_id' => $replacementRider->id,
        ]);
        $this->assertNotNull($order->fresh()->hub_released_at);
    }

    public function test_database_prevents_multiple_active_delivery_assignments_for_one_order(): void
    {
        $order = $this->order(['status' => 'ASSIGNED_TO_RIDER']);
        $firstRider = $this->user('courier', 'approved');
        $secondRider = $this->user('courier', 'approved');

        DeliveryAssignment::query()->create([
            'order_id' => $order->id,
            'active_order_id' => $order->id,
            'rider_id' => $firstRider->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DeliveryAssignment::query()->create([
            'order_id' => $order->id,
            'active_order_id' => $order->id,
            'rider_id' => $secondRider->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);
    }

    public function test_order_records_cannot_be_deleted_while_tracking_history_exists(): void
    {
        $order = $this->order();
        $actor = $this->user('sorting_center', 'approved');
        ParcelTrackingEvent::query()->create([
            'order_id' => $order->id,
            'actor_id' => $actor->id,
            'event_type' => 'hub_received',
            'status' => 'AT_SORTING_CENTER',
        ]);

        $this->expectException(QueryException::class);
        $order->delete();
    }

    public function test_buyer_accounts_cannot_be_deleted_while_order_history_exists(): void
    {
        $order = $this->order();

        $this->expectException(QueryException::class);
        $order->buyer->delete();
    }

    public function test_rider_can_complete_only_owned_delivery_and_failure_requires_reason(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'approved');
        $otherRider = $this->user('courier', 'approved');
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);

        $this->actingAsUser($otherRider)->patch(route('courier.orders.completeDelivery', $order), ['recipient_confirmation' => 'Recipient'])->assertForbidden();
        $this->actingAsUser($rider)->patch(route('courier.orders.failDelivery', $order), [])->assertSessionHasErrors('failure_reason');
        $this->actingAsUser($rider)->patch(route('courier.orders.failDelivery', $order), ['failure_reason' => 'recipient_unavailable'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'DELIVERY_FAILED', 'delivery_failure_reason' => 'recipient_unavailable']);

        $deliveryOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);
        $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $deliveryOrder), [
            'recipient_confirmation' => 'A. Buyer',
            'delivery_notes' => 'Left at door',
        ])->assertSessionHasErrors('proof_file');
        $this->patch(route('courier.orders.completeDelivery', $deliveryOrder), [
            'recipient_confirmation' => 'A. Buyer',
            'delivery_notes' => 'Left at door',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $deliveryOrder->id, 'status' => 'DELIVERED']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $deliveryOrder->id, 'event_type' => 'delivered']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $deliveryOrder->id, 'outcome' => 'delivered', 'attempt_no' => 1]);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'outcome' => 'failed', 'attempt_no' => 1]);
    }

    public function test_rider_tracking_only_shows_orders_assigned_to_that_rider(): void
    {
        $rider = $this->user('courier', 'approved');
        $otherRider = $this->user('courier', 'approved');
        $ownOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);
        $otherOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $otherRider->id]);

        $this->actingAsUser($rider)->get(route('courier.tracking'))
            ->assertOk()->assertSee($ownOrder->order_number)->assertDontSee($otherOrder->order_number);
    }

    public function test_logistics_can_approve_reject_reassign_and_return_failed_parcels(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $pendingRider = $this->user('courier', 'pending');
        $rejectedRider = $this->user('courier', 'pending');
        $firstRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $nextRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);

        $this->actingAsUser($center)->post(route('logistics.riders.approve', $pendingRider))->assertRedirect();
        $this->actingAsUser($center)->post(route('logistics.riders.reject', $rejectedRider))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $pendingRider->id, 'status' => 'approved']);
        $this->assertDatabaseHas('users', ['id' => $rejectedRider->id, 'status' => 'rejected']);

        $order = $this->order(['status' => 'SORTED', 'delivery_area' => 'Majayjay']);
        $product = Product::create([
            'user_id' => $order->seller_id, 'name' => 'Return stock item', 'description' => 'Item',
            'category' => 'Test', 'price' => 10, 'stock' => 2, 'is_archived' => false,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => 'Return stock item', 'unit_price' => 10, 'quantity' => 3, 'item_total' => 30,
        ]);
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $firstRider->id])->assertRedirect();
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $nextRider->id])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_courier_id' => $nextRider->id]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $firstRider->id, 'status' => 'reassigned']);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $nextRider->id, 'status' => 'active', 'assigned_by' => $center->id]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $firstRider->id, 'active_order_id' => null]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $nextRider->id, 'active_order_id' => $order->id]);

        $order->forceFill(['status' => 'DELIVERY_FAILED'])->save();
        $this->actingAsUser($center)->post(route('logistics.orders.return', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT', 'delivery_courier_id' => $nextRider->id]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $nextRider->id, 'status' => 'active', 'active_order_id' => $order->id]);
        $this->actingAsUser($nextRider)->get(route('courier.dashboard'))
            ->assertSee($order->order_number)
            ->assertSee($order->seller->street_address);
        $this->get(route('courier.tracking'))->assertSee($order->order_number);
        $this->actingAsUser($order->seller)->get(route('seller.orders.index'))
            ->assertSee('Awaiting courier handoff')
            ->assertDontSee('Confirm returned parcel received');
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order))->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->actingAsUser($firstRider)->post(route('courier.orders.confirmReturnDelivery', $order))->assertForbidden();
        $this->actingAsUser($nextRider)->post(route('courier.orders.confirmReturnDelivery', $order))->assertRedirect();
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'return_handed_to_seller', 'actor_id' => $nextRider->id]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $nextRider->id, 'status' => 'returned', 'active_order_id' => null]);
        $this->post(route('courier.orders.confirmReturnDelivery', $order))->assertUnprocessable();
        $this->actingAsUser($order->seller)->get(route('seller.orders.index'))
            ->assertSee('Confirm returned parcel received');
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURNED_TO_SELLER', 'delivery_courier_id' => $nextRider->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'returned_to_seller']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order))->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_failed_delivery_retry_preserves_attempt_and_assignment_history_until_return(): void
    {
        config()->set('logistics.maximum_delivery_attempts', 2);
        $center = $this->user('sorting_center', 'approved');
        $firstRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $secondRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $order = $this->order(['status' => 'SORTED']);
        $product = Product::query()->create([
            'user_id' => $order->seller_id,
            'name' => 'Failed delivery return stock',
            'description' => 'Inventory restored on seller receipt',
            'category' => 'Test',
            'price' => 10,
            'stock' => 2,
            'is_archived' => false,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 10,
            'quantity' => 3,
            'item_total' => 30,
        ]);

        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $firstRider->id])->assertRedirect();
        $this->actingAsUser($center)->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->actingAsUser($firstRider)->post(route('courier.orders.startDelivery', $order))->assertRedirect();
        $this->patch(route('courier.orders.failDelivery', $order), [
            'failure_reason' => 'recipient_unavailable',
            'delivery_notes' => 'Gate code missing.',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'DELIVERY_FAILED']);
        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'attempt_no' => 1,
            'outcome' => 'failed',
            'notes' => 'Gate code missing.',
        ]);

        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $secondRider->id])
            ->assertSessionHasErrors('scheduled_at');
        $scheduledAt = now()->addDay()->format('Y-m-d H:i:s');
        $this->post(route('logistics.orders.assignRider', $order), [
            'delivery_courier_id' => $secondRider->id,
            'scheduled_at' => $scheduledAt,
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ASSIGNED_TO_RIDER', 'delivery_notes' => null]);
        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'attempt_no' => 1,
            'notes' => 'Gate code missing.',
        ]);
        $this->actingAsUser($center)->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->actingAsUser($firstRider)->get(route('courier.history'))
            ->assertSee($order->order_number)
            ->assertDontSee($order->recipient_contact);
        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'rider_id' => $secondRider->id,
            'attempt_no' => 2,
            'outcome' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);
        $this->actingAsUser($secondRider)->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->travelTo(now()->addDays(2));
        $this->post(route('courier.orders.startDelivery', $order))->assertRedirect();
        $this->patch(route('courier.orders.failDelivery', $order), ['failure_reason' => 'incorrect_address'])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT', 'delivery_courier_id' => $secondRider->id]);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'rider_id' => $firstRider->id, 'attempt_no' => 1, 'outcome' => 'failed']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'rider_id' => $secondRider->id, 'attempt_no' => 2, 'outcome' => 'failed', 'scheduled_at' => $scheduledAt]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $firstRider->id, 'status' => 'reassigned']);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $secondRider->id, 'status' => 'active', 'active_order_id' => $order->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);

        $this->actingAsUser($secondRider)->post(route('courier.orders.confirmReturnDelivery', $order))->assertRedirect();
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $secondRider->id, 'status' => 'returned', 'active_order_id' => null]);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURNED_TO_SELLER']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_logistics_workspace_pages_render_with_live_records(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $this->order(['status' => 'AT_SORTING_CENTER']);

        $this->actingAsUser($center)->get(route('logistics.dashboard'))->assertOk();
        $this->get(route('logistics.intake'))->assertOk();
        $this->get(route('logistics.pickupRequests'))->assertOk();
        $this->get(route('logistics.sorting'))->assertOk();
        $this->get(route('logistics.dispatch'))->assertOk();
        $this->get(route('logistics.tracking'))->assertOk();
        $this->get(route('logistics.riders'))->assertOk();
        $this->get(route('logistics.reports'))->assertOk();
    }

    public function test_logistics_reports_filter_events_by_event_timestamp(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $order->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAsUser($center)->get(route('logistics.reports', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]))->assertOk()->assertViewHas('stats', fn (array $stats): bool => $stats['delivered'] === 1);
    }

    public function test_courier_workspace_pages_render_and_detail_is_owner_scoped(): void
    {
        $courier = $this->user('courier', 'approved');
        $assignedOrder = $this->order(['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $courier->id, 'delivery_area' => 'Area Z', 'assigned_at' => now()]);
        $nextAreaOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $courier->id, 'delivery_area' => 'Area A', 'assigned_at' => now()->addMinute()]);
        $completedOrder = $this->order(['status' => 'COMPLETED', 'delivery_courier_id' => $courier->id]);
        $otherOrder = $this->order(['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $this->user('courier', 'approved')->id]);

        $this->actingAsUser($courier)->get(route('courier.dashboard'))
            ->assertOk()
            ->assertViewHas('myDeliveryAssignments', fn ($assignments): bool => collect($assignments->items())->pluck('id')->all() === [$nextAreaOrder->id, $assignedOrder->id]);
        $this->get(route('courier.tracking'))->assertOk();
        $this->get(route('courier.history'))->assertOk();
        $this->get(route('courier.orders.show', $assignedOrder))->assertOk()->assertSee($assignedOrder->order_number);
        $this->get(route('courier.orders.show', $completedOrder))->assertForbidden();
        $this->get(route('courier.orders.show', $otherOrder))->assertForbidden();
    }

    public function test_cod_delivery_requires_exact_amount_in_centavos(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'COD', 'total_amount' => 150.25, 'delivery_courier_id' => $rider->id]);

        $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertSessionHasErrors('cod_collected_amount');
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'cod_collected_amount' => '150.26',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertSessionHasErrors('cod_collected_amount');
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'cod_collected_amount' => '150.25',
            'proof_file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'DELIVERED', 'cod_collected_amount' => '150.25']);
        $attempt = $order->deliveryAttempts()->firstOrFail();
        Storage::disk('private')->assertExists($attempt->proof_path);
        $this->get(route('delivery-attempts.proof', $attempt))->assertOk();
        $otherBuyer = $this->user('buyer', 'approved');
        $this->actingAsUser($otherBuyer)->get(route('delivery-attempts.proof', $attempt))->assertForbidden();
        $this->actingAsUser($order->buyer)->get(route('delivery-attempts.proof', $attempt))->assertOk();
        $this->get(route('buyer.orders.show', $order))->assertOk()->assertSee('Download proof');
    }

    public function test_non_cod_delivery_needs_no_cash_and_rejects_a_cod_amount(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'approved');
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'GCash', 'delivery_courier_id' => $rider->id]);

        $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'DELIVERED',
            'cod_collected_amount' => null,
        ]);

        $secondOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'GCash', 'delivery_courier_id' => $rider->id]);
        $this->patch(route('courier.orders.completeDelivery', $secondOrder), [
            'recipient_confirmation' => 'Buyer',
            'cod_collected_amount' => '150.00',
            'proof_file' => UploadedFile::fake()->image('delivery-proof-2.jpg'),
        ])->assertSessionHasErrors('cod_collected_amount');

        $this->assertDatabaseHas('orders', ['id' => $secondOrder->id, 'status' => 'OUT_FOR_DELIVERY']);
    }

    public function test_buyer_registration_stays_blocked_until_admin_approval_and_documents_are_private(): void
    {
        Storage::fake('private');

        $this->post(route('register.post'), [
            'first_name' => 'New', 'last_name' => 'Buyer', 'sex' => 'Other',
            'email' => 'new-buyer@example.test', 'contact_no' => '09123456789',
            'birthday' => '1990-01-01', 'province' => 'Laguna', 'municipality' => 'Majayjay',
            'barangay' => 'Poblacion', 'street_address' => 'Test street',
            'id_document' => UploadedFile::fake()->image('id.png'),
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $buyer = User::where('email', 'new-buyer@example.test')->firstOrFail();
        $this->assertSame('pending', $buyer->status);
        Storage::disk('private')->assertExists($buyer->id_path);
        $this->assertFileDoesNotExist(storage_path('app/public/'.$buyer->id_path));

        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'password123'])
            ->assertSessionHas('error');
        $this->assertGuest();

        $admin = $this->user('admin', 'approved');
        $this->actingAsUser($admin)->post(route('admin.registrations.approve', $buyer))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'status' => 'approved']);
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login.post'), ['email' => $buyer->email, 'password' => 'password123'])
            ->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($buyer->fresh());
    }

    public function test_inactive_sessions_are_logged_out_and_rejected_users_cannot_be_reactivated(): void
    {
        $buyer = $this->user('buyer', 'suspended');
        $this->actingAsUser($buyer)->get(route('buyer.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $pendingBuyer = $this->user('buyer', 'pending');
        $this->actingAsUser($pendingBuyer)->get(route('buyer.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $admin = $this->user('admin', 'approved');
        $rejected = $this->user('seller', 'rejected');
        $pendingSeller = $this->user('seller', 'pending');
        $this->actingAsUser($admin)->post(route('admin.moderation.reactivate', $rejected))->assertUnprocessable();
        $this->post(route('admin.moderation.reactivate', $pendingSeller))->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $rejected->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('users', ['id' => $pendingSeller->id, 'status' => 'pending']);
    }

    public function test_database_seeder_can_create_accounts_with_protected_role_and_status_fields(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@ezicart.com',
            'role' => 'admin',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'pending.seller@ezicart.com',
            'role' => 'seller',
            'status' => 'pending',
        ]);
    }

    public function test_order_transition_service_rejects_attributes_outside_the_target_allow_list(): void
    {
        $order = $this->order();
        $otherBuyer = $this->user('buyer', 'approved');

        try {
            app(OrderTransitionService::class)->transition(
                $order,
                $order->seller,
                OrderStatus::Confirmed,
                'confirmed',
                attributes: ['buyer_id' => $otherBuyer->id, 'total_amount' => 1],
            );
            $this->fail('The transition should reject protected attributes.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'PLACED',
            'buyer_id' => $order->buyer_id,
            'total_amount' => 150,
        ]);
    }

    public function test_checkout_does_not_expose_database_exception_details(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');
        $product = Product::query()->forceCreate([
            'user_id' => $seller->id,
            'name' => 'Checkout failure item',
            'description' => 'Test item',
            'category' => 'Test',
            'price' => 20,
            'stock' => 2,
            'compliance_status' => 'approved',
            'is_archived' => false,
        ]);
        $this->order(['order_number' => 'EZC-'.str_repeat('A', 10)]);
        $cart = [
            (string) $product->id => [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 20,
                'quantity' => 1,
                'seller_id' => $seller->id,
            ],
        ];
        Str::createRandomStringsUsing(fn (int $length): string => str_repeat('A', $length));

        try {
            $response = $this->actingAsUser($buyer)->withSession(['cart' => $cart])->post(route('checkout.process'), [
                'recipient_name' => 'Test Buyer',
                'recipient_contact' => '09123456789',
                'province' => 'Laguna',
                'municipality' => 'Majayjay',
                'barangay' => 'Poblacion',
                'street_address' => '1 Test Street',
                'payment_method' => 'COD',
            ]);
        } finally {
            Str::createRandomStringsNormally();
        }

        $response->assertSessionHas('error', "We couldn't place your order right now. Please try again.");
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
    }

    public function test_role_workspaces_deny_cross_role_access(): void
    {
        $workspaceRoutes = [
            'buyer' => route('buyer.dashboard'),
            'seller' => route('seller.dashboard'),
            'courier' => route('courier.dashboard'),
            'sorting_center' => route('logistics.dashboard'),
            'admin' => route('admin.dashboard'),
        ];

        foreach (['buyer', 'seller', 'courier', 'sorting_center', 'admin'] as $role) {
            $actor = $this->user($role, 'approved');

            foreach ($workspaceRoutes as $requiredRole => $url) {
                if ($role !== $requiredRole) {
                    $this->actingAsUser($actor)->get($url)->assertForbidden();
                }
            }
        }
    }

    public function test_login_endpoint_throttles_repeated_failed_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.post'), ['email' => 'missing@example.test', 'password' => 'bad-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login.post'), ['email' => 'missing@example.test', 'password' => 'bad-password'])
            ->assertTooManyRequests();
    }

    public function test_admin_account_decisions_send_email_and_in_app_notifications(): void
    {
        Notification::fake();
        $admin = $this->user('admin', 'approved');
        $approvedUser = $this->user('buyer', 'pending');
        $rejectedUser = $this->user('seller', 'pending');
        $center = $this->user('sorting_center', 'approved');
        $approvedRider = $this->user('courier', 'pending');
        $rejectedRider = $this->user('courier', 'pending');

        $this->actingAsUser($admin)->post(route('admin.registrations.approve', $approvedUser))->assertRedirect();
        $this->post(route('admin.registrations.reject', $rejectedUser))->assertRedirect();
        $this->actingAsUser($center)->post(route('logistics.riders.approve', $approvedRider))->assertRedirect();
        $this->post(route('logistics.riders.reject', $rejectedRider))->assertRedirect();

        Notification::assertSentTo($approvedUser, AccountStatusNotification::class, function (AccountStatusNotification $notification) use ($approvedUser): bool {
            return $notification->status === 'approved'
                && in_array('mail', $notification->via($approvedUser), true)
                && in_array('database', $notification->via($approvedUser), true)
                && str_contains($notification->toMail($approvedUser)->subject, 'approved')
                && $notification->toDatabase($approvedUser)['url'] === route('login');
        });
        Notification::assertSentTo($rejectedUser, AccountStatusNotification::class, function (AccountStatusNotification $notification) use ($rejectedUser): bool {
            return $notification->status === 'rejected'
                && in_array('mail', $notification->via($rejectedUser), true)
                && in_array('database', $notification->via($rejectedUser), true)
                && str_contains($notification->toMail($rejectedUser)->subject, 'not approved')
                && $notification->toDatabase($rejectedUser)['url'] === '';
        });
        Notification::assertSentTo($approvedRider, AccountStatusNotification::class);
        Notification::assertSentTo($rejectedRider, AccountStatusNotification::class);
        $this->assertDatabaseHas('users', ['id' => $approvedUser->id, 'status' => 'approved']);
        $this->assertDatabaseHas('users', ['id' => $rejectedUser->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('users', ['id' => $approvedRider->id, 'status' => 'approved']);
        $this->assertDatabaseHas('users', ['id' => $rejectedRider->id, 'status' => 'rejected']);
    }

    public function test_password_reset_uses_one_time_expiring_broker_tokens_and_neutral_responses(): void
    {
        Notification::fake();
        $user = $this->user('buyer', 'approved');
        $user->forceFill(['remember_token' => 'old-remember-token'])->save();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', 'If an account matches that email address, a password reset link has been sent.');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertIsString($token);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-password-123',
            'password_confirmation' => 'another-password-123',
        ])->assertSessionHasErrors('email');

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertSessionHas('status', 'If an account matches that email address, a password reset link has been sent.');
    }

    public function test_account_management_updates_only_allowed_profile_fields_and_verifies_password_change(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $buyer->forceFill(['remember_token' => 'current-remember-token'])->save();

        $this->actingAsUser($buyer)->get(route('account.profile.edit'))->assertOk();
        $staleSessionPasswordHash = app('session')->get('password_hash_web');
        $this->assertIsString($staleSessionPasswordHash);
        $this->patch(route('account.profile.update'), [
            'first_name' => 'Updated',
            'last_name' => $buyer->last_name,
            'middle_initial' => 'Q',
            'contact_no' => '09999999999',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '9 New Street',
            'role' => 'admin',
            'status' => 'approved',
            'id_path' => 'attacker-controlled.pdf',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'first_name' => 'Updated',
            'street_address' => '9 New Street',
            'role' => 'buyer',
            'status' => 'approved',
            'id_path' => null,
        ]);
        $this->patch(route('account.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-account-password',
            'password_confirmation' => 'new-account-password',
        ])->assertSessionHasErrors('current_password');
        $this->patch(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'new-account-password',
            'password_confirmation' => 'new-account-password',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-account-password', $buyer->fresh()->password));
        $this->assertNotSame('current-remember-token', $buyer->fresh()->remember_token);

        $this->withSession(['password_hash_web' => $staleSessionPasswordHash])
            ->get(route('notifications.index'));
        $this->assertGuest();
    }

    public function test_buyer_can_cancel_before_shipment_and_inventory_restores_only_once(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');
        $product = Product::create([
            'user_id' => $seller->id, 'name' => 'Test item', 'description' => 'Item',
            'category' => 'Test', 'price' => 10, 'stock' => 2, 'is_archived' => false,
        ]);
        $order = $this->order(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => 'Test item', 'unit_price' => 10, 'quantity' => 3, 'item_total' => 30,
        ]);

        $this->actingAsUser($buyer)->post(route('buyer.orders.cancel', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'cancelled']);
        $this->post(route('buyer.orders.cancel', $order))->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_seller_confirmation_and_preparation_are_separate_locked_transitions(): void
    {
        $order = $this->order();
        $seller = $order->seller;

        $this->actingAsUser($seller)->get(route('seller.orders.index'))->assertOk();
        $this->get(route('seller.orders.show', $order))->assertOk();
        $this->actingAsUser($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CONFIRMED']);
        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'PREPARING'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PREPARING']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'confirmed']);
    }

    public function test_order_notifications_are_visible_only_to_the_notifiable_account(): void
    {
        $order = $this->order();
        $buyer = $order->buyer;
        $seller = $order->seller;

        $this->actingAsUser($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertRedirect();

        $buyerNotification = $buyer->notifications()->firstOrFail();
        $this->assertSame('CONFIRMED', $buyerNotification->data['status']);
        $this->assertSame(route('buyer.orders.show', $order), $buyerNotification->data['url']);
        $this->actingAsUser($buyer)->get(route('notifications.index'))->assertOk()->assertSee($order->order_number);
        $this->post(route('notifications.read', $buyerNotification->id))->assertRedirect();
        $this->assertNotNull($buyerNotification->fresh()->read_at);

        $otherBuyer = $this->user('buyer', 'approved');
        $this->actingAsUser($otherBuyer)->post(route('notifications.read', $buyerNotification->id))->assertNotFound();
    }

    public function test_seller_schedules_pickup_before_logistics_can_assign_a_rider(): void
    {
        $order = $this->order(['status' => 'READY_FOR_PICKUP']);
        $seller = $order->seller;
        $scheduledFor = now()->addDay()->format('Y-m-d\\TH:i');

        $this->actingAsUser($seller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => $scheduledFor,
            'pickup_window' => 'Morning (8 AM–12 PM)',
            'pickup_notes' => 'Use the rear loading entrance.',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'pickup_window' => 'Morning (8 AM–12 PM)',
            'pickup_notes' => 'Use the rear loading entrance.',
        ]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'pickup_requested']);
        $this->actingAsUser($this->user('sorting_center', 'approved'))
            ->get(route('logistics.pickupRequests'))->assertOk()->assertSee($order->order_number);

        $otherSeller = $this->user('seller', 'approved');
        $this->actingAsUser($otherSeller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => $scheduledFor,
            'pickup_window' => 'Morning',
        ])->assertForbidden();
        $this->actingAsUser($seller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => $scheduledFor,
            'pickup_window' => 'Morning',
        ])->assertUnprocessable();
    }

    public function test_sensitive_documents_are_private_and_logistics_access_is_rider_scoped(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'pending', ['id_path' => 'documents/ids/rider.png']);
        Storage::disk('private')->put($rider->id_path, 'private bytes');
        $center = $this->user('sorting_center', 'approved');
        $buyer = $this->user('buyer', 'approved', ['id_path' => 'documents/ids/buyer.png']);
        Storage::disk('private')->put($buyer->id_path, 'private bytes');

        $this->actingAsUser($center)->get(route('logistics.riders.documents.show', [$rider, 'identity']))->assertOk();
        $this->get(route('logistics.riders.documents.show', [$buyer, 'identity']))->assertForbidden();
        $this->get(route('logistics.riders.documents.show', [$rider, 'unknown']))->assertNotFound();
        $this->actingAsUser($buyer)->get(route('account.documents.show', 'identity'))->assertOk();
        $otherBuyer = $this->user('buyer', 'approved');
        $this->actingAsUser($otherBuyer)->get(route('account.documents.show', 'identity'))->assertNotFound();
    }

    public function test_logistics_enforces_rider_capacity_and_delivery_attempt_limit(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $activeOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $rider->id]);
        $dispatchOrder = $this->order(['status' => 'SORTED', 'delivery_area' => 'Majayjay']);
        config()->set('logistics.maximum_active_deliveries_per_rider', 1);
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $dispatchOrder), ['delivery_courier_id' => $rider->id])->assertUnprocessable();

        config()->set('logistics.maximum_delivery_attempts', 1);
        $this->actingAsUser($rider)->patch(route('courier.orders.failDelivery', $activeOrder), ['failure_reason' => 'recipient_unavailable'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $activeOrder->id, 'status' => 'RETURN_IN_TRANSIT']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $activeOrder->id, 'attempt_no' => 1, 'outcome' => 'failed']);
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $dispatchOrder), ['delivery_courier_id' => $rider->id])->assertUnprocessable();
    }

    public function test_assigned_rider_can_decline_before_hub_release_and_preserve_assignment_history(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $replacementRider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $order = $this->order(['status' => 'SORTED']);
        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), [
            'delivery_courier_id' => $rider->id,
        ])->assertRedirect();

        $this->actingAsUser($replacementRider)->post(route('courier.orders.declineDeliveryAssignment', $order), [
            'reason' => 'Wrong rider',
        ])->assertForbidden();
        $this->actingAsUser($rider)->get(route('courier.dashboard'))->assertSee('Decline assignment');
        $this->post(route('courier.orders.declineDeliveryAssignment', $order), [
            'reason' => 'Vehicle issue',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'SORTED',
            'delivery_courier_id' => null,
            'assigned_at' => null,
            'hub_released_at' => null,
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'declined',
            'active_order_id' => null,
        ]);
        $this->assertDatabaseHas('parcel_tracking_events', [
            'order_id' => $order->id,
            'actor_id' => $rider->id,
            'event_type' => 'delivery_assignment_declined',
            'notes' => 'The assigned rider declined before hub release: Vehicle issue',
        ]);
        $this->assertContains(
            'delivery_assignment_declined',
            $center->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all(),
        );

        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $order), [
            'delivery_courier_id' => $replacementRider->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'rider_id' => $replacementRider->id,
            'status' => 'active',
            'active_order_id' => $order->id,
        ]);
        $this->actingAsUser($center)->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->actingAsUser($replacementRider)->post(route('courier.orders.declineDeliveryAssignment', $order))->assertUnprocessable();
    }

    public function test_delivery_attempt_migration_backfills_known_legacy_failures_and_deliveries(): void
    {
        $rider = $this->user('courier', 'approved');
        $failedAt = now()->subDays(2)->startOfSecond();
        $deliveredAt = now()->subDay()->startOfSecond();
        $order = $this->order([
            'status' => 'COMPLETED',
            'delivery_courier_id' => $rider->id,
            'failed_at' => $failedAt,
            'delivered_at' => $deliveredAt,
            'delivery_failure_reason' => 'recipient_unavailable',
            'delivery_notes' => 'Recipient confirmed delivery later.',
        ]);

        Schema::dropIfExists('delivery_attempts');
        $migration = require database_path('migrations/2026_10_04_161550_add_delivery_attempts_table.php');
        $migration->up();

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'attempt_no' => 1,
            'outcome' => 'failed',
            'reason' => 'recipient_unavailable',
            'attempted_at' => $failedAt,
        ]);
        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'attempt_no' => 2,
            'outcome' => 'delivered',
            'attempted_at' => $deliveredAt,
        ]);
    }

    public function test_area_migration_links_legacy_riders_when_order_address_created_the_mapping_first(): void
    {
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'majayjay']);
        $order = $this->order(['province' => 'Laguna', 'municipality' => 'Majayjay']);

        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('destination_area_id'));
        Schema::dropIfExists('area_user');
        Schema::dropIfExists('area_municipalities');
        Schema::dropIfExists('areas');
        $migration = require database_path('migrations/2026_10_04_172834_create_areas_and_rider_area_assignments.php');
        $migration->up();

        $areaId = DB::table('area_municipalities')
            ->where('province_normalized', 'laguna')
            ->where('municipality_normalized', 'majayjay')
            ->value('area_id');

        $this->assertNotNull($areaId);
        $this->assertDatabaseHas('area_user', [
            'user_id' => $rider->id,
            'area_id' => $areaId,
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'destination_area_id' => $areaId]);
    }

    public function test_return_handoff_releases_courier_capacity_before_seller_receipt_confirmation(): void
    {
        config()->set('logistics.maximum_active_deliveries_per_rider', 1);
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $area = $this->area('Laguna', 'Majayjay');
        $returnedOrder = $this->order([
            'status' => 'RETURN_IN_TRANSIT',
            'delivery_courier_id' => $rider->id,
            'return_handed_to_seller_at' => now(),
        ]);
        DeliveryAssignment::query()->create([
            'order_id' => $returnedOrder->id,
            'active_order_id' => null,
            'rider_id' => $rider->id,
            'status' => 'returned',
            'assigned_at' => now()->subHour(),
            'released_at' => now(),
        ]);
        $dispatchOrder = $this->order([
            'status' => 'SORTED',
            'destination_area_id' => $area->id,
            'delivery_area' => $area->name,
        ]);

        $this->actingAsUser($center)->post(route('logistics.orders.assignRider', $dispatchOrder), [
            'delivery_courier_id' => $rider->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $dispatchOrder->id,
            'rider_id' => $rider->id,
            'status' => 'active',
            'active_order_id' => $dispatchOrder->id,
        ]);
    }

    public function test_rider_area_assignments_are_structured_and_area_filtered(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved');
        $primaryArea = $this->area('Laguna', 'Majayjay');
        $secondaryArea = $this->area('Laguna', 'Calamba');

        $this->actingAsUser($center)->patch(route('logistics.riders.areas.update', $rider), [
            'area_ids' => [$primaryArea->id, $secondaryArea->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('area_user', ['user_id' => $rider->id, 'area_id' => $primaryArea->id, 'is_primary' => true, 'is_active' => true]);
        $this->assertDatabaseHas('area_user', ['user_id' => $rider->id, 'area_id' => $secondaryArea->id, 'is_primary' => false, 'is_active' => true]);
    }

    private function user(string $role, string $status, array $extra = []): User
    {
        $user = User::query()->forceCreate(array_merge([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
            'status' => $status,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => 'Other',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Street',
        ], $extra));

        if ($role === 'courier' && filled($extra['assigned_area'] ?? null)) {
            $area = $this->area((string) $user->province, (string) $extra['assigned_area']);
            $user->serviceAreas()->attach($area->id, ['is_primary' => true, 'is_active' => true]);
        }

        return $user;
    }

    private function order(array $extra = []): Order
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');
        $area = $this->area('Laguna', 'Majayjay');

        return Order::query()->forceCreate(array_merge([
            'order_number' => 'EZC-'.strtoupper(fake()->unique()->bothify('??????????')),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'recipient_name' => 'Recipient Test',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'destination_area_id' => $area->id,
            'barangay' => 'Poblacion',
            'street_address' => '2 Test Street',
            'subtotal' => 100,
            'shipping_fee' => 50,
            'commission_fee' => 10,
            'total_amount' => 150,
            'payment_method' => 'GCash',
            'status' => 'PLACED',
        ], $extra));
    }

    private function area(string $province, string $municipality): Area
    {
        $normalize = fn (string $value): string => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
        $provinceNormalized = $normalize($province);
        $municipalityNormalized = $normalize($municipality);
        $mapping = AreaMunicipality::query()
            ->where('province_normalized', $provinceNormalized)
            ->where('municipality_normalized', $municipalityNormalized)
            ->first();

        if ($mapping !== null) {
            return $mapping->area;
        }

        $area = Area::query()->create([
            'name' => trim($municipality),
            'code' => strtolower(str_replace(' ', '-', $province.'-'.$municipality)),
            'is_active' => true,
        ]);

        return AreaMunicipality::query()->create([
            'area_id' => $area->id,
            'province' => $province,
            'municipality' => trim($municipality),
            'province_normalized' => $provinceNormalized,
            'municipality_normalized' => $municipalityNormalized,
        ])->area;
    }
}
