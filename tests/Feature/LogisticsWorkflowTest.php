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
use App\Notifications\CourierApplicationSubmittedNotification;
use App\Notifications\OrderMessageNotification;
use App\Notifications\OrderStatusNotification;
use App\Notifications\OrderWorkflowNotification;
use App\Notifications\ProductComplianceNotification;
use App\Services\InventoryRestorationService;
use App\Services\OrderTransitionService;
use App\Services\RiderBadgeService;
use App\Services\TransactionAwareNotificationSender;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $pickupBadge = app(RiderBadgeService::class)->ensure($courier);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmHandover', $order), [
            'rider_badge' => 'EZR:'.$pickupBadge,
        ])->assertRedirect();
        $this->assertContains('seller_handover_confirmed', $courier->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
        $this->actingAsUser($courier)->post(route('courier.orders.confirmPickup', $order), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'method' => 'handheld',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PICKED_UP']);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $courier->id,
            'station' => 'seller_pickup',
            'result' => 'accepted',
            'method' => 'handheld',
        ]);

        $this->actingAsUser($center)->post(route('logistics.orders.receive', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $center->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'hub_received']);
    }

    public function test_hub_area_ownership_scopes_intake_and_blocks_cross_hub_receipt(): void
    {
        $hubA = $this->user('sorting_center', 'approved');
        $hubB = $this->user('sorting_center', 'approved');
        $area = $this->area('Laguna', 'Majayjay');
        $area->forceFill(['sorting_center_id' => $hubA->id])->save();
        $order = $this->order(['status' => 'PICKED_UP']);

        $this->actingAsUser($hubA)->get(route('logistics.intake'))->assertOk()->assertSee($order->order_number);
        $this->actingAsUser($hubB)->get(route('logistics.intake'))->assertOk()->assertDontSee($order->order_number);
        $this->actingAsUser($hubB)->post(route('logistics.orders.receive', $order))->assertForbidden();
        $this->actingAsUser($hubA)->post(route('logistics.orders.receive', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'sorting_center_id' => $hubA->id]);
    }

    public function test_checkout_records_the_server_resolved_destination_hub_area(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $seller = $this->user('seller', 'approved');
        $area = $this->area('Laguna', 'Majayjay');
        $hub = $this->user('sorting_center', 'approved');
        $area->forceFill(['sorting_center_id' => $hub->id])->save();
        $product = Product::query()->create([
            'user_id' => $seller->id,
            'name' => 'Hub-routed checkout product',
            'description' => 'Destination area must be resolved on the server.',
            'category' => 'Test',
            'price' => 100,
            'stock' => 2,
            'is_archived' => false,
        ]);
        $product->forceFill(['compliance_status' => 'approved'])->save();

        $this->actingAsUser($buyer)->withSession(['cart' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 100,
            'quantity' => 1,
            'variation_id' => null,
        ]]])->post(route('checkout.process'), [
            'recipient_name' => 'Hub Routed Buyer',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '3 Test Street',
            'payment_method' => 'COD',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'destination_area_id' => $area->id,
        ]);
    }

    public function test_pickup_notifications_are_sent_only_to_the_area_owner_hub(): void
    {
        $hubA = $this->user('sorting_center', 'approved');
        $hubB = $this->user('sorting_center', 'approved');
        $seller = $this->user('seller', 'approved');
        $area = $this->area('Laguna', 'Majayjay');
        $area->forceFill(['sorting_center_id' => $hubA->id])->save();
        $order = $this->order([
            'seller_id' => $seller->id,
            'destination_area_id' => $area->id,
            'status' => 'READY_FOR_PICKUP',
        ]);

        $this->actingAsUser($seller)->post(route('seller.orders.schedulePickup', $order), [
            'pickup_scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'pickup_window' => 'Morning',
        ])->assertRedirect();

        $this->assertContains('pickup_requested', $hubA->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
        $this->assertNotContains('pickup_requested', $hubB->notifications()->where('type', OrderWorkflowNotification::class)->get()->pluck('data.event_type')->all());
    }

    public function test_multi_hub_rider_profiles_actions_and_private_documents_are_scoped(): void
    {
        Storage::fake('private');
        $hubA = $this->user('sorting_center', 'approved');
        $hubB = $this->user('sorting_center', 'approved');
        $areaA = $this->area('Laguna', 'Majayjay');
        $areaB = $this->area('Laguna', 'Calamba');
        $areaA->forceFill(['sorting_center_id' => $hubA->id])->save();
        $areaB->forceFill(['sorting_center_id' => $hubB->id])->save();
        $riderA = $this->user('courier', 'approved', ['id_path' => 'documents/ids/hub-a-rider.png']);
        $riderA->serviceAreas()->attach($areaA->id, ['is_primary' => true, 'is_active' => true]);
        $riderB = $this->user('courier', 'approved');
        $riderB->serviceAreas()->attach($areaB->id, ['is_primary' => true, 'is_active' => true]);
        $activeOrder = $this->order([
            'status' => 'OUT_FOR_DELIVERY',
            'sorting_center_id' => $hubA->id,
            'destination_area_id' => $areaA->id,
            'delivery_courier_id' => $riderA->id,
        ]);
        Storage::disk('private')->put($riderA->id_path, 'hub A private document');

        $this->actingAsUser($hubA)->get(route('logistics.riders'))
            ->assertOk()->assertSee($riderA->email)->assertDontSee($riderB->email);
        $this->actingAsUser($hubB)->get(route('logistics.riders'))
            ->assertOk()->assertSee($riderB->email)->assertDontSee($riderA->email);
        $this->actingAsUser($hubA)->get(route('logistics.riders.documents.show', [$riderA, 'identity']))->assertOk();
        $this->actingAsUser($hubB)->get(route('logistics.riders.documents.show', [$riderA, 'identity']))->assertForbidden();
        $this->actingAsUser($hubA)->patch(route('logistics.riders.areas.update', $riderA), [
            'area_ids' => [],
        ])->assertUnprocessable();
        $this->actingAsUser($hubB)->patch(route('logistics.riders.areas.update', $riderA), [
            'area_ids' => [$areaB->id],
        ])->assertForbidden();
        $this->actingAsUser($hubB)->post(route('logistics.riders.suspend', $riderA), [
            'reason' => 'Cross-hub suspension attempt.',
        ])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $riderA->id, 'status' => 'approved']);
        $this->assertDatabaseHas('orders', ['id' => $activeOrder->id, 'status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $riderA->id]);
    }

    public function test_rider_badge_is_displayed_rotatable_and_verified_at_hub_release(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved');
        $firstBadge = app(RiderBadgeService::class)->ensure($rider);

        $this->actingAsUser($rider)->get(route('courier.dashboard'))
            ->assertOk()->assertSee('EZR:'.$firstBadge)->assertSee('<svg', false);
        $this->post(route('courier.badge.rotate'))->assertRedirect();
        $rotatedBadge = app(RiderBadgeService::class)->ensure($rider);
        $this->assertNotSame($firstBadge, $rotatedBadge);

        $order = $this->order([
            'status' => 'ASSIGNED_TO_RIDER',
            'sorting_center_id' => $center->id,
            'delivery_courier_id' => $rider->id,
        ]);
        $this->actingAsUser($center)->post(route('logistics.orders.releaseToRider', $order), [
            'rider_badge' => 'EZR:WRONG-CODE',
        ])->assertSessionHasErrors('rider_badge');
        $this->assertNull($order->fresh()->hub_released_at);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $center->id,
            'station' => 'dispatch_release',
            'result' => 'rejected',
        ]);

        $this->post(route('logistics.orders.releaseToRider', $order), [
            'rider_badge' => 'EZR:'.$rotatedBadge,
        ])->assertRedirect();
        $this->assertNotNull($order->fresh()->hub_released_at);
    }

    public function test_seller_handover_verifies_and_audits_a_scanned_rider_badge(): void
    {
        $seller = $this->user('seller', 'approved');
        $rider = $this->user('courier', 'approved');
        $order = $this->order([
            'seller_id' => $seller->id,
            'status' => 'READY_FOR_PICKUP',
            'pickup_courier_id' => $rider->id,
            'pickup_requested_at' => now(),
            'pickup_arrived_at' => now(),
        ]);
        $badgeCode = app(RiderBadgeService::class)->ensure($rider);

        $this->actingAsUser($seller)->post(route('seller.orders.confirmHandover', $order), [
            'rider_badge' => 'EZR:WRONG-CODE',
            'method' => 'handheld',
        ])->assertSessionHasErrors('rider_badge');
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'station' => 'seller_handover',
            'result' => 'rejected',
            'method' => 'handheld',
        ]);
        $this->assertNull($order->fresh()->seller_handover_at);

        $this->post(route('seller.orders.confirmHandover', $order), [
            'rider_badge' => 'EZR:'.$badgeCode,
            'method' => 'handheld',
        ])->assertRedirect();
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'station' => 'seller_handover',
            'result' => 'accepted',
            'method' => 'handheld',
        ]);
        $this->assertNotNull($order->fresh()->seller_handover_at);
    }

    public function test_rider_must_scan_the_assigned_parcel_before_confirming_pickup_possession(): void
    {
        $courier = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'READY_FOR_PICKUP',
            'pickup_courier_id' => $courier->id,
            'pickup_claimed_at' => now(),
            'pickup_arrived_at' => now(),
            'seller_handover_at' => now(),
        ]);

        $this->actingAsUser($courier)->post(route('courier.orders.confirmPickup', $order), [
            'parcel_reference' => 'EZP:WRONG-PARCEL',
            'method' => 'handheld',
        ])->assertSessionHasErrors('parcel_reference');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'READY_FOR_PICKUP']);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $courier->id,
            'station' => 'seller_pickup',
            'result' => 'rejected',
            'method' => 'handheld',
        ]);

        $this->post(route('courier.orders.confirmPickup', $order), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'method' => 'camera',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PICKED_UP']);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $courier->id,
            'station' => 'seller_pickup',
            'result' => 'accepted',
            'method' => 'camera',
        ]);
    }

    public function test_delivery_start_scans_the_assigned_parcel_and_audits_rejections(): void
    {
        $rider = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'ASSIGNED_TO_RIDER',
            'delivery_courier_id' => $rider->id,
            'hub_released_at' => now(),
        ]);

        $this->actingAsUser($rider)->post(route('courier.orders.startDelivery', $order), [
            'parcel_reference' => 'EZP:WRONG-PARCEL',
            'method' => 'handheld',
        ])->assertSessionHasErrors('parcel_reference');
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'station' => 'delivery_start',
            'result' => 'rejected',
            'method' => 'handheld',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ASSIGNED_TO_RIDER']);

        $this->post(route('courier.orders.startDelivery', $order), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'method' => 'handheld',
        ])->assertRedirect();
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'station' => 'delivery_start',
            'result' => 'accepted',
            'method' => 'handheld',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'OUT_FOR_DELIVERY']);
    }

    public function test_order_workflow_notifications_send_email_and_in_app_links_for_the_recipient_role(): void
    {
        Notification::fake();
        $courier = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'READY_FOR_PICKUP',
            'pickup_requested_at' => now(),
            'pickup_courier_id' => $courier->id,
        ]);

        $this->actingAsUser($courier)->post(route('courier.orders.claim', $order))->assertRedirect();

        $seller = $order->seller;
        Notification::assertSentTo($seller, OrderWorkflowNotification::class, function (OrderWorkflowNotification $notification) use ($seller, $order): bool {
            return $notification->eventType === 'pickup_accepted'
                && in_array('mail', $notification->via($seller), true)
                && in_array('database', $notification->via($seller), true)
                && $notification->toMail($seller)->actionUrl === route('seller.orders.show', $order)
                && $notification->toDatabase($seller)['url'] === route('seller.orders.show', $order);
        });
    }

    public function test_courier_registration_notifies_admin_and_logistics_to_review_the_application(): void
    {
        Storage::fake('private');
        Notification::fake();
        $admin = $this->user('admin', 'approved');
        $logistics = $this->user('sorting_center', 'approved');
        $buyer = $this->user('buyer', 'approved');

        $this->post(route('register.courier.post'), [
            'first_name' => 'Rider',
            'last_name' => 'Applicant',
            'sex' => 'Other',
            'email' => 'courier-review@example.test',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Street',
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'ABC1234',
            'id_document' => UploadedFile::fake()->image('license.png'),
            'or_cr_document' => UploadedFile::fake()->image('or-cr.png'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'status' => 'approved',
        ])->assertRedirect(route('login'));

        $applicant = User::query()->where('email', 'courier-review@example.test')->firstOrFail();
        $this->assertSame('courier', $applicant->role);
        $this->assertSame('pending', $applicant->status);
        $notificationMatchesReviewPage = function (CourierApplicationSubmittedNotification $notification, array $channels, User $notifiable) use ($applicant): bool {
            $reviewUrl = $notifiable->role === 'admin'
                ? route('admin.registrations.index')
                : route('logistics.riders');

            return $notification->applicant->is($applicant)
                && $notification->toMail($notifiable)->actionUrl === $reviewUrl
                && $notification->toDatabase($notifiable)['url'] === $reviewUrl;
        };

        foreach ([$admin, $logistics] as $reviewer) {
            Notification::assertSentTo($reviewer, CourierApplicationSubmittedNotification::class, fn (CourierApplicationSubmittedNotification $notification, array $channels, User $notifiable): bool => $channels === ['database'] && $notificationMatchesReviewPage($notification, $channels, $notifiable));
            Notification::assertSentTo($reviewer, CourierApplicationSubmittedNotification::class, fn (CourierApplicationSubmittedNotification $notification, array $channels, User $notifiable): bool => $channels === ['mail'] && $notificationMatchesReviewPage($notification, $channels, $notifiable));
        }

        Notification::assertNotSentTo($buyer, CourierApplicationSubmittedNotification::class);
    }

    public function test_order_status_notifications_send_email_and_in_app_links_to_the_buyer(): void
    {
        Notification::fake();
        $order = $this->order(['status' => 'PLACED']);
        $this->addOrderInventoryItem($order);

        $this->actingAsUser($order->seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])
            ->assertRedirect();

        $buyer = $order->buyer;
        Notification::assertSentTo($buyer, OrderStatusNotification::class, function (OrderStatusNotification $notification) use ($buyer, $order): bool {
            return $notification->status === 'CONFIRMED'
                && in_array('mail', $notification->via($buyer), true)
                && in_array('database', $notification->via($buyer), true)
                && $notification->toMail($buyer)->actionUrl === route('buyer.orders.show', $order)
                && $notification->toDatabase($buyer)['url'] === route('buyer.orders.show', $order);
        });
    }

    public function test_order_message_notification_sends_email_and_in_app_conversation_link(): void
    {
        Notification::fake();
        $order = $this->order();

        $this->actingAsUser($order->seller)->post(route('seller.orders.messages.store', $order), ['body' => 'Your parcel is ready.'])
            ->assertRedirect();

        $buyer = $order->buyer;
        Notification::assertSentTo($buyer, OrderMessageNotification::class, function (OrderMessageNotification $notification) use ($buyer, $order): bool {
            return in_array('mail', $notification->via($buyer), true)
                && in_array('database', $notification->via($buyer), true)
                && $notification->toMail($buyer)->actionUrl === route('buyer.orders.messages.show', $order)
                && $notification->toDatabase($buyer)['url'] === route('buyer.orders.messages.show', $order);
        });
    }

    public function test_product_compliance_notice_sends_email_and_in_app_notification_to_seller(): void
    {
        Notification::fake();
        $admin = $this->user('admin', 'approved');
        $seller = $this->user('seller', 'approved');
        $product = Product::query()->create([
            'user_id' => $seller->id,
            'name' => 'Restricted sample listing',
            'description' => 'Listing requires an edit.',
            'category' => 'Test',
            'price' => 10,
            'stock' => 1,
            'is_archived' => false,
        ]);

        $this->actingAsUser($admin)->patch(route('admin.compliance.products.review', $product), [
            'compliance_status' => 'flagged',
            'compliance_note' => 'Update the listing details before selling.',
        ])->assertRedirect();

        Notification::assertSentTo($seller, ProductComplianceNotification::class, function (ProductComplianceNotification $notification) use ($seller): bool {
            return $notification->status === 'flagged'
                && in_array('mail', $notification->via($seller), true)
                && in_array('database', $notification->via($seller), true)
                && str_contains($notification->toMail($seller)->introLines[0], 'flagged')
                && $notification->toDatabase($seller)['url'] === route('seller.products.index');
        });
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
        $this->assertDatabaseHas('scan_events', ['station' => 'intake', 'result' => 'rejected', 'method' => 'manual']);
    }

    public function test_parcel_qr_intake_records_the_scan_and_dispute_resolution_starts_return_workflow(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $parcel = $this->order(['status' => 'PICKED_UP']);

        $this->actingAsUser($parcel->seller)
            ->get(route('seller.orders.waybill', $parcel))
            ->assertOk()
            ->assertSee($parcel->parcel_code)
            ->assertSee('<svg', false);

        $this->actingAsUser($center)->post(route('logistics.scan'), [
            'reference' => 'EZP:'.$parcel->parcel_code,
            'method' => 'handheld',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $parcel->id, 'status' => 'AT_SORTING_CENTER']);
        $this->assertDatabaseHas('scan_events', ['order_id' => $parcel->id, 'station' => 'intake', 'result' => 'accepted', 'method' => 'handheld']);

        $order = $this->order(['status' => 'DELIVERED']);
        $dispute = Dispute::query()->create([
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'reason' => 'Damaged Item',
            'description' => 'The product arrived damaged and cannot be used as intended.',
            'status' => 'PENDING',
        ]);
        $this->actingAsUser($order->buyer)->post(route('buyer.orders.confirm', $order))->assertUnprocessable();

        $admin = $this->user('admin', 'approved');
        $this->actingAsUser($admin)->patch(route('admin.disputes.resolve', $dispute), [
            'status' => 'REFUND_APPROVED',
            'admin_notes' => 'Return required before a manual refund reconciliation.',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT']);
        $this->assertDatabaseHas('disputes', ['id' => $dispute->id, 'status' => 'REFUND_APPROVED', 'resolved_by' => $admin->id]);
        $this->actingAsUser($admin)->patch(route('admin.disputes.resolve', $dispute), [
            'status' => 'UNDER_REVIEW',
            'admin_notes' => 'Attempt to reopen.',
        ])->assertUnprocessable();
    }

    public function test_sorting_center_cannot_operate_on_another_centers_parcel(): void
    {
        $ownerCenter = $this->user('sorting_center', 'approved');
        $otherCenter = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $ownerCenter->id]);

        $this->actingAsUser($otherCenter)
            ->post(route('logistics.orders.sort', $order))
            ->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $ownerCenter->id]);
    }

    public function test_storage_putaway_enforces_hub_area_capacity_and_cycle_count_tracks_differences(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $firstParcel = $this->order(['status' => 'SORTED', 'sorting_center_id' => $center->id]);
        $secondParcel = $this->order(['status' => 'SORTED', 'sorting_center_id' => $center->id]);
        $thirdParcel = $this->order(['status' => 'SORTED', 'sorting_center_id' => $center->id]);
        $locationId = DB::table('storage_locations')->insertGetId([
            'hub_id' => $center->id,
            'code' => 'LOC-TEST-01',
            'label' => 'Laguna shelf one',
            'type' => 'STAGING',
            'area_id' => $firstParcel->destination_area_id,
            'capacity' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($center)->post(route('logistics.storage.putaway'), [
            'parcel_reference' => 'EZP:'.$firstParcel->parcel_code,
            'location_reference' => 'EZL:LOC-TEST-01',
            'method' => 'handheld',
        ])->assertRedirect();
        $this->post(route('logistics.storage.putaway'), [
            'parcel_reference' => 'EZP:'.$secondParcel->parcel_code,
            'location_reference' => 'EZL:LOC-TEST-01',
        ])->assertRedirect();
        $this->post(route('logistics.storage.putaway'), [
            'parcel_reference' => 'EZP:'.$thirdParcel->parcel_code,
            'location_reference' => 'EZL:LOC-TEST-01',
        ])->assertSessionHasErrors('parcel_reference');
        $this->assertDatabaseHas('parcel_placements', [
            'order_id' => $firstParcel->id,
            'active_order_id' => $firstParcel->id,
            'location_id' => $locationId,
            'removed_at' => null,
        ]);

        $this->post(route('logistics.storage.cycle-count'), [
            'location_reference' => 'EZL:LOC-TEST-01',
            'scanned_parcels' => "EZP:{$firstParcel->parcel_code}\nEZP:UNKNOWN-PARCEL",
        ])->assertSessionHas('cycle_report', function (array $report) use ($firstParcel, $secondParcel): bool {
            return $report['correct'] === [$firstParcel->parcel_code]
                && $report['missing'] === [$secondParcel->parcel_code]
                && $report['unexpected'] === ['UNKNOWN-PARCEL'];
        });
        $this->assertDatabaseHas('scan_events', ['station' => 'cycle_count', 'result' => 'missing', 'order_id' => $secondParcel->id]);
        $this->assertDatabaseHas('scan_events', ['station' => 'cycle_count', 'result' => 'unexpected', 'order_id' => null]);

        $this->post(route('logistics.storage.pick'), [
            'parcel_reference' => 'EZP:'.$firstParcel->parcel_code,
            'location_reference' => 'EZL:LOC-TEST-01',
        ])->assertRedirect();
        $this->assertDatabaseHas('parcel_placements', [
            'order_id' => $firstParcel->id,
            'active_order_id' => null,
            'location_id' => $locationId,
        ]);

        $wrongArea = $this->area('Cavite', 'Imus');
        $wrongAreaLocation = DB::table('storage_locations')->insertGetId([
            'hub_id' => $center->id,
            'code' => 'LOC-TEST-02',
            'label' => 'Wrong area shelf',
            'type' => 'SORTING',
            'area_id' => $wrongArea->id,
            'capacity' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->post(route('logistics.storage.putaway'), [
            'parcel_reference' => 'EZP:'.$firstParcel->parcel_code,
            'location_reference' => 'EZL:LOC-TEST-02',
        ])->assertSessionHasErrors('parcel_reference');
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $firstParcel->id,
            'location_id' => $wrongAreaLocation,
            'station' => 'putaway',
            'result' => 'rejected',
        ]);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $thirdParcel->id,
            'location_id' => $locationId,
            'station' => 'putaway',
            'result' => 'rejected',
        ]);
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
            'payment_method' => 'COD',
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
        $sellerOrderNotice = $seller->notifications()
            ->where('type', OrderWorkflowNotification::class)
            ->get()
            ->first(fn ($notification): bool => $notification->data['event_type'] === 'order_placed');
        $this->assertNotNull($sellerOrderNotice);
        $this->assertSame($order->id, $sellerOrderNotice->data['order_id']);
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
        $pickupBadge = app(RiderBadgeService::class)->ensure($rider);
        $this->actingAsUser($seller)->post(route('seller.orders.confirmHandover', $order), [
            'rider_badge' => 'EZR:'.$pickupBadge,
        ])->assertRedirect();
        $this->actingAsUser($rider)->post(route('courier.orders.confirmPickup', $order), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'method' => 'manual',
        ])->assertRedirect();
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
        $deliveryCode = Crypt::decryptString($order->fresh()->delivery_code_encrypted);
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Test Buyer',
            'delivery_code' => $deliveryCode,
            'cod_collected_amount' => $order->total_amount,
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

    public function test_logistics_can_configure_areas_and_route_multiple_municipalities_to_one_area(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $order = $this->order(['status' => 'AT_SORTING_CENTER']);
        $order->forceFill(['province' => 'Quezon', 'municipality' => 'Lucban'])->save();

        $this->actingAsUser($center)->post(route('logistics.orders.sort', $order))
            ->assertUnprocessable();
        $this->assertDatabaseMissing('area_municipalities', [
            'province_normalized' => 'quezon',
            'municipality_normalized' => 'lucban',
        ]);

        $this->get(route('logistics.areas'))->assertOk()->assertSee('Routing areas');
        $this->post(route('logistics.areas.store'), [
            'name' => 'South Quezon',
            'code' => 'south-quezon',
        ])->assertRedirect();
        $area = Area::query()->where('code', 'SOUTH-QUEZON')->firstOrFail();

        foreach (['Lucban', 'Tayabas'] as $municipality) {
            $this->post(route('logistics.areas.municipalities.store'), [
                'area_id' => $area->id,
                'province' => 'Quezon',
                'municipality' => $municipality,
            ])->assertRedirect();
        }

        $this->post(route('logistics.areas.municipalities.store'), [
            'area_id' => $area->id,
            'province' => ' QUEZON ',
            'municipality' => 'lucban',
        ])->assertSessionHasErrors('municipality');

        $otherOrder = $this->order(['status' => 'AT_SORTING_CENTER']);
        $otherOrder->forceFill(['province' => 'Quezon', 'municipality' => 'Tayabas'])->save();
        $this->post(route('logistics.orders.sort', $order))->assertRedirect();
        $this->post(route('logistics.orders.sort', $otherOrder))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'destination_area_id' => $area->id]);
        $this->assertDatabaseHas('orders', ['id' => $otherOrder->id, 'destination_area_id' => $area->id]);
        $this->get(route('logistics.areas'))->assertSee('Lucban, Quezon')->assertSee('Tayabas, Quezon');

        $this->actingAsUser($this->user('buyer', 'approved'))->get(route('logistics.areas'))->assertForbidden();
    }

    public function test_logistics_can_correct_an_existing_municipality_area_mapping(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $this->actingAsUser($center)->post(route('logistics.areas.store'), ['name' => 'Zone One', 'code' => 'zone-one'])->assertRedirect();
        $this->post(route('logistics.areas.store'), ['name' => 'Zone Two', 'code' => 'zone-two'])->assertRedirect();
        $firstArea = Area::query()->where('code', 'ZONE-ONE')->firstOrFail();
        $secondArea = Area::query()->where('code', 'ZONE-TWO')->firstOrFail();
        $this->post(route('logistics.areas.municipalities.store'), [
            'area_id' => $firstArea->id,
            'province' => 'Laguna',
            'municipality' => 'Liliw',
        ])->assertRedirect();
        $mapping = AreaMunicipality::query()->where('province_normalized', 'laguna')->where('municipality_normalized', 'liliw')->firstOrFail();

        $this->patch(route('logistics.areas.municipalities.update', $mapping), ['area_id' => $secondArea->id])->assertRedirect();

        $this->assertDatabaseHas('area_municipalities', ['id' => $mapping->id, 'area_id' => $secondArea->id]);
        $order = $this->order(['status' => 'AT_SORTING_CENTER', 'province' => 'Laguna', 'municipality' => 'Liliw']);
        $this->post(route('logistics.orders.sort', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'destination_area_id' => $secondArea->id]);
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

    public function test_legacy_courier_assignments_are_backfilled_without_overwriting_new_history(): void
    {
        $legacyRider = $this->user('courier', 'approved');
        $newerRider = $this->user('courier', 'approved');
        $assignedAt = now()->subDays(3)->startOfSecond();
        $deliveredAt = now()->subDay()->startOfSecond();
        $activeOrder = $this->order([
            'courier_id' => $legacyRider->id,
            'status' => 'OUT_FOR_DELIVERY',
            'assigned_at' => $assignedAt,
        ]);
        $completedOrder = $this->order([
            'courier_id' => $legacyRider->id,
            'status' => 'COMPLETED',
            'delivered_at' => $deliveredAt,
        ]);
        $returnedOrder = $this->order([
            'courier_id' => $legacyRider->id,
            'status' => 'RETURNED_TO_SELLER',
            'return_handed_to_seller_at' => now()->subHours(6),
        ]);
        $cancelledOrder = $this->order([
            'courier_id' => $legacyRider->id,
            'status' => 'CANCELLED',
        ]);
        $orderWithNewerHistory = $this->order([
            'courier_id' => $legacyRider->id,
            'status' => 'SORTED',
        ]);
        DB::table('delivery_assignments')->insert([
            'order_id' => $orderWithNewerHistory->id,
            'active_order_id' => null,
            'rider_id' => $newerRider->id,
            'assigned_by' => null,
            'status' => 'declined',
            'assigned_at' => now()->subHour(),
            'released_at' => now(),
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_05_060618_backfill_legacy_courier_assignment_history.php');
        $migration->up();

        $this->assertDatabaseHas('orders', [
            'id' => $activeOrder->id,
            'courier_id' => $legacyRider->id,
            'delivery_courier_id' => $legacyRider->id,
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $activeOrder->id,
            'active_order_id' => $activeOrder->id,
            'rider_id' => $legacyRider->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $completedOrder->id,
            'active_order_id' => null,
            'rider_id' => $legacyRider->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull(DB::table('delivery_assignments')->where('order_id', $completedOrder->id)->value('completed_at'));
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $returnedOrder->id,
            'active_order_id' => null,
            'rider_id' => $legacyRider->id,
            'status' => 'returned',
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $cancelledOrder->id,
            'active_order_id' => null,
            'rider_id' => $legacyRider->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $orderWithNewerHistory->id, 'delivery_courier_id' => null]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $orderWithNewerHistory->id,
            'rider_id' => $newerRider->id,
            'status' => 'declined',
        ]);

        $migration->up();

        $this->assertSame(1, DB::table('delivery_assignments')->where('order_id', $activeOrder->id)->count());
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

        $deliveryOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'COD', 'delivery_courier_id' => $rider->id]);
        $deliveryCode = $this->issueDeliveryCode($deliveryOrder);
        $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $deliveryOrder), [
            'recipient_confirmation' => 'A. Buyer',
            'delivery_code' => $deliveryCode,
            'delivery_notes' => 'Left at door',
            'cod_collected_amount' => $deliveryOrder->total_amount,
        ])->assertSessionHasErrors('proof_file');
        $this->patch(route('courier.orders.completeDelivery', $deliveryOrder), [
            'recipient_confirmation' => 'A. Buyer',
            'delivery_code' => $deliveryCode,
            'delivery_notes' => 'Left at door',
            'cod_collected_amount' => $deliveryOrder->total_amount,
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
        $returnLocation = DB::table('storage_locations')->insertGetId([
            'hub_id' => $center->id, 'code' => 'RET-TEST-01', 'label' => 'Return intake', 'type' => 'RETURNS',
            'area_id' => $order->destination_area_id, 'capacity' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAsUser($center)->post(route('logistics.scan'), [
            'reference' => 'EZP:'.$order->parcel_code,
            'location_reference' => 'EZL:RET-TEST-01',
        ])->assertRedirect();
        $this->assertDatabaseHas('parcel_placements', ['order_id' => $order->id, 'location_id' => $returnLocation, 'active_order_id' => $order->id]);
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $nextRider->id])->assertRedirect();
        $this->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->actingAsUser($center)->post(route('logistics.orders.return', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT', 'delivery_courier_id' => $nextRider->id]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $nextRider->id, 'status' => 'active', 'active_order_id' => $order->id]);
        $this->actingAsUser($nextRider)->get(route('courier.dashboard'))
            ->assertSee($order->order_number)
            ->assertSee($order->seller->street_address);
        $this->get(route('courier.tracking'))->assertSee($order->order_number);
        $this->actingAsUser($order->seller)->get(route('seller.orders.index'))
            ->assertSee('Awaiting courier handoff')
            ->assertDontSee('Confirm scanned return received');
        $returnBadge = app(RiderBadgeService::class)->ensure($nextRider);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order), [
            'rider_badge' => 'EZR:'.$returnBadge,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->actingAsUser($firstRider)->post(route('courier.orders.confirmReturnDelivery', $order))->assertForbidden();
        $this->actingAsUser($nextRider)->post(route('courier.orders.confirmReturnDelivery', $order), [
            'parcel_reference' => 'EZP:WRONG-PARCEL',
            'method' => 'handheld',
        ])->assertSessionHasErrors('parcel_reference');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT', 'return_handed_to_seller_at' => null]);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $nextRider->id,
            'station' => 'return_to_seller',
            'result' => 'rejected',
            'method' => 'handheld',
        ]);
        $this->actingAsUser($nextRider)->post(route('courier.orders.confirmReturnDelivery', $order), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'method' => 'handheld',
        ])->assertRedirect();
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'return_handed_to_seller', 'actor_id' => $nextRider->id]);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $nextRider->id,
            'station' => 'return_to_seller',
            'result' => 'accepted',
            'method' => 'handheld',
        ]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $nextRider->id, 'status' => 'returned', 'active_order_id' => null]);
        $this->post(route('courier.orders.confirmReturnDelivery', $order))->assertUnprocessable();
        $this->actingAsUser($order->seller)->get(route('seller.orders.index'))
            ->assertSee('Confirm scanned return received');
        $this->post(route('seller.orders.confirmReturn', $order), [
            'rider_badge' => 'EZR:WRONG-BADGE',
            'method' => 'handheld',
        ])->assertSessionHasErrors('rider_badge');
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $order->seller_id,
            'station' => 'seller_return_receipt',
            'result' => 'rejected',
            'method' => 'handheld',
        ]);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order), [
            'rider_badge' => 'EZR:'.$returnBadge,
            'method' => 'camera',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURNED_TO_SELLER', 'delivery_courier_id' => $nextRider->id]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'returned_to_seller']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
        $this->assertDatabaseHas('scan_events', [
            'order_id' => $order->id,
            'actor_id' => $order->seller_id,
            'station' => 'seller_return_receipt',
            'result' => 'accepted',
            'method' => 'camera',
        ]);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order), [
            'rider_badge' => 'EZR:'.$returnBadge,
        ])->assertUnprocessable();
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
            ->assertUnprocessable();
        $returnLocationId = DB::table('storage_locations')->insertGetId([
            'hub_id' => $center->id, 'code' => 'RET-RETRY-01', 'label' => 'Return intake', 'type' => 'RETURNS',
            'area_id' => $order->destination_area_id, 'capacity' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAsUser($center)->post(route('logistics.scan'), [
            'reference' => 'EZP:'.$order->parcel_code,
            'location_reference' => 'EZL:RET-RETRY-01',
        ])->assertRedirect();
        $this->assertDatabaseHas('parcel_placements', ['order_id' => $order->id, 'location_id' => $returnLocationId, 'active_order_id' => $order->id]);
        $this->post(route('logistics.storage.pick'), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'location_reference' => 'EZL:RET-RETRY-01',
        ])->assertRedirect();
        $scheduledAt = now()->addDay()->format('Y-m-d H:i:s');
        $this->post(route('logistics.orders.assignRider', $order), [
            'delivery_courier_id' => $secondRider->id,
            'scheduled_at' => $scheduledAt,
        ])->assertRedirect();
        $previousRiderNotification = $firstRider->notifications()
            ->where('type', OrderWorkflowNotification::class)
            ->get()
            ->first(fn ($notification): bool => $notification->data['event_type'] === 'delivery_assignment_replaced');
        $this->assertNotNull($previousRiderNotification);
        $this->assertSame(route('courier.history', ['search' => $order->order_number]), $previousRiderNotification->data['url']);
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
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'DELIVERY_FAILED', 'delivery_courier_id' => $secondRider->id]);
        $this->actingAsUser($center)->post(route('logistics.scan'), [
            'reference' => 'EZP:'.$order->parcel_code,
            'location_reference' => 'EZL:RET-RETRY-01',
        ])->assertRedirect();
        $this->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $secondRider->id])->assertRedirect();
        $this->post(route('logistics.orders.releaseToRider', $order))->assertRedirect();
        $this->post(route('logistics.orders.return', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURN_IN_TRANSIT', 'delivery_courier_id' => $secondRider->id]);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'rider_id' => $firstRider->id, 'attempt_no' => 1, 'outcome' => 'failed']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'rider_id' => $secondRider->id, 'attempt_no' => 2, 'outcome' => 'failed', 'scheduled_at' => $scheduledAt]);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $firstRider->id, 'status' => 'returned']);
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $secondRider->id, 'status' => 'active', 'active_order_id' => $order->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);

        $this->actingAsUser($secondRider)->post(route('courier.orders.confirmReturnDelivery', $order), [
            'parcel_reference' => 'EZP:'.$order->parcel_code,
            'method' => 'manual',
        ])->assertRedirect();
        $this->assertDatabaseHas('delivery_assignments', ['order_id' => $order->id, 'rider_id' => $secondRider->id, 'status' => 'returned', 'active_order_id' => null]);
        $returnBadge = app(RiderBadgeService::class)->ensure($secondRider);
        $this->actingAsUser($order->seller)->post(route('seller.orders.confirmReturn', $order), [
            'rider_badge' => 'EZR:'.$returnBadge,
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'RETURNED_TO_SELLER']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_logistics_workspace_pages_render_with_live_records(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $this->order(['status' => 'AT_SORTING_CENTER']);

        $this->actingAsUser($center)->get(route('logistics.dashboard'))->assertOk();
        $this->get(route('logistics.intake'))
            ->assertOk()
            ->assertSee('id="reference"', false)
            ->assertSee('autofocus', false);
        $this->get(route('logistics.pickupRequests'))->assertOk();
        $this->get(route('logistics.sorting'))->assertOk();
        $this->get(route('logistics.dispatch'))->assertOk();
        $this->get(route('logistics.tracking'))->assertOk();
        $this->get(route('logistics.riders'))->assertOk();
        $this->get(route('logistics.reports'))->assertOk();
    }

    public function test_logistics_tracking_search_treats_like_wildcards_as_literal_order_number_characters(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $literalMatch = $this->order(['status' => 'OUT_FOR_DELIVERY', 'order_number' => 'EZC-TRACK-100%_A!B']);
        $wildcardMatch = $this->order(['status' => 'OUT_FOR_DELIVERY', 'order_number' => 'EZC-TRACK-100xyAB']);

        $this->actingAsUser($center)->get(route('logistics.tracking', ['search' => '100%_A!']))
            ->assertOk()
            ->assertViewHas('orders', function ($orders) use ($literalMatch, $wildcardMatch): bool {
                $orderIds = collect($orders->items())->pluck('id')->all();

                return $orderIds === [$literalMatch->id] && ! in_array($wildcardMatch->id, $orderIds, true);
            });
    }

    public function test_logistics_tracking_date_filter_includes_the_selected_day_and_excludes_the_next_day(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $selectedDayOrder = $this->order(['status' => 'OUT_FOR_DELIVERY']);
        $nextDayOrder = $this->order(['status' => 'OUT_FOR_DELIVERY']);
        $selectedDayOrder->forceFill(['updated_at' => today()->endOfDay()])->saveQuietly();
        $nextDayOrder->forceFill(['updated_at' => today()->addDay()->startOfDay()])->saveQuietly();

        $this->actingAsUser($center)->get(route('logistics.tracking', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertViewHas('orders', function ($orders) use ($selectedDayOrder, $nextDayOrder): bool {
                $orderIds = collect($orders->items())->pluck('id')->all();

                return in_array($selectedDayOrder->id, $orderIds, true) && ! in_array($nextDayOrder->id, $orderIds, true);
            });
    }

    public function test_logistics_dashboard_caches_counters_briefly_and_refreshes_them(): void
    {
        $center = $this->user('sorting_center', 'approved');
        Cache::forget('logistics.dashboard.stats.'.$center->id.'.'.today()->toDateString());

        $this->actingAsUser($center)->get(route('logistics.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['inbound'] === 0);
        $this->order(['status' => 'PICKED_UP']);

        $this->get(route('logistics.dashboard'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['inbound'] === 0);

        Cache::forget('logistics.dashboard.stats.'.$center->id.'.'.today()->toDateString());
        $this->get(route('logistics.dashboard'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['inbound'] === 1);
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
        $deliveredOrder = $this->order(['status' => 'DELIVERED', 'delivery_courier_id' => $courier->id]);
        $completedOrder = $this->order(['status' => 'COMPLETED', 'delivery_courier_id' => $courier->id]);
        $returnedOrder = $this->order(['status' => 'RETURNED_TO_SELLER', 'delivery_courier_id' => $courier->id]);
        $otherOrder = $this->order(['status' => 'ASSIGNED_TO_RIDER', 'delivery_courier_id' => $this->user('courier', 'approved')->id]);

        $this->actingAsUser($courier)->get(route('courier.dashboard'))
            ->assertOk()
            ->assertViewHas('myDeliveryAssignments', fn ($assignments): bool => collect($assignments->items())->pluck('id')->all() === [$nextAreaOrder->id, $assignedOrder->id]);
        $this->get(route('courier.tracking'))->assertOk();
        $this->get(route('courier.history'))->assertOk();
        $this->get(route('courier.earnings'))
            ->assertOk()
            ->assertSee('Earnings are hidden until the courier payment formula is defined.')
            ->assertSee('No earnings amounts are calculated or displayed.')
            ->assertDontSee('₱');
        $this->get(route('courier.orders.show', $assignedOrder))->assertOk()->assertSee($assignedOrder->order_number);
        $this->get(route('courier.orders.show', $deliveredOrder))->assertForbidden();
        $this->get(route('courier.orders.show', $completedOrder))->assertForbidden();
        $this->get(route('courier.orders.show', $returnedOrder))->assertForbidden();
        $this->get(route('courier.orders.show', $otherOrder))->assertForbidden();

        $buyer = $this->user('buyer', 'approved');
        $this->actingAsUser($buyer)->get(route('courier.earnings'))->assertForbidden();
    }

    public function test_courier_history_escapes_search_wildcards_and_validates_status_filters(): void
    {
        $courier = $this->user('courier', 'approved');
        $literalMatch = $this->order([
            'order_number' => 'EZC-100%_A',
            'status' => 'DELIVERY_FAILED',
            'delivery_courier_id' => $courier->id,
        ]);
        $wildcardMatch = $this->order([
            'order_number' => 'EZC-100XYA',
            'status' => 'DELIVERY_FAILED',
            'delivery_courier_id' => $courier->id,
        ]);

        $this->actingAsUser($courier)->get(route('courier.history', ['search' => '%_']))
            ->assertOk()
            ->assertViewHas('orders', function ($orders) use ($literalMatch, $wildcardMatch): bool {
                $orderIds = collect($orders->items())->pluck('id')->all();

                return $orderIds === [$literalMatch->id] && ! in_array($wildcardMatch->id, $orderIds, true);
            });

        $this->get(route('courier.history', ['status' => 'UNKNOWN']))->assertSessionHasErrors('status');
        $this->get(route('courier.history', ['search' => str_repeat('A', 101)]))->assertSessionHasErrors('search');
    }

    public function test_cod_delivery_requires_exact_amount_in_centavos(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'approved', ['assigned_area' => 'Majayjay']);
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'COD', 'total_amount' => 150.25, 'delivery_courier_id' => $rider->id]);
        $deliveryCode = $this->issueDeliveryCode($order);

        $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'delivery_code' => $deliveryCode,
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertSessionHasErrors('cod_collected_amount');
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'delivery_code' => $deliveryCode,
            'cod_collected_amount' => '150.26',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertSessionHasErrors('cod_collected_amount');
        $this->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'delivery_code' => $deliveryCode,
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

    public function test_delivery_code_is_private_to_buyer_refreshable_and_limited_to_five_attempts(): void
    {
        Storage::fake('private');
        $buyer = $this->user('buyer', 'approved');
        $rider = $this->user('courier', 'approved');
        $otherBuyer = $this->user('buyer', 'approved');
        $order = $this->order([
            'buyer_id' => $buyer->id,
            'status' => 'OUT_FOR_DELIVERY',
            'payment_method' => 'COD',
            'delivery_courier_id' => $rider->id,
        ]);
        $originalCode = $this->issueDeliveryCode($order);

        $this->actingAsUser($buyer)->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee($originalCode)
            ->assertSee('<svg', false);
        $this->actingAsUser($otherBuyer)->get(route('buyer.orders.show', $order))->assertForbidden();
        $this->actingAsUser($otherBuyer)->post(route('buyer.orders.delivery-code.refresh', $order))->assertForbidden();

        $this->actingAsUser($buyer)->post(route('buyer.orders.delivery-code.refresh', $order))->assertRedirect();
        $order->refresh();
        $currentCode = Crypt::decryptString($order->delivery_code_encrypted);
        $this->assertNotSame($originalCode, $currentCode);
        $this->assertSame(0, $order->delivery_code_attempts);
        $wrongCode = $currentCode === '000000' ? '111111' : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $order), [
                'recipient_confirmation' => 'Buyer',
                'delivery_code' => $wrongCode,
                'cod_collected_amount' => $order->total_amount,
                'proof_file' => UploadedFile::fake()->image('unused-proof.jpg'),
            ])->assertSessionHasErrors('delivery_code');
        }

        $this->assertSame(5, $order->fresh()->delivery_code_attempts);
        $this->actingAsUser($buyer)->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertDontSee(Crypt::decryptString($order->fresh()->delivery_code_encrypted));
    }

    public function test_delivery_proof_is_deleted_when_the_completion_transaction_rolls_back(): void
    {
        Storage::fake('private');
        $courier = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'OUT_FOR_DELIVERY',
            'payment_method' => 'COD',
            'delivery_courier_id' => $courier->id,
            'hub_released_at' => now()->subMinute(),
        ]);
        $deliveryCode = $this->issueDeliveryCode($order);
        $transitionFailure = new HttpException(422, 'Completion could not be committed.');
        $transitionService = \Mockery::mock(OrderTransitionService::class);
        $transitionService->shouldReceive('transition')->once()->andThrow($transitionFailure);
        $this->app->instance(OrderTransitionService::class, $transitionService);

        $this->actingAsUser($courier)->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Recipient Test',
            'delivery_code' => $deliveryCode,
            'cod_collected_amount' => '150.00',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertUnprocessable();

        $this->assertSame([], Storage::disk('private')->files('delivery-proofs'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'OUT_FOR_DELIVERY']);
        $this->assertDatabaseMissing('delivery_attempts', ['order_id' => $order->id, 'outcome' => 'delivered']);
    }

    public function test_non_cod_orders_remain_held_at_delivery_until_payment_verification_is_configured(): void
    {
        Storage::fake('private');
        $rider = $this->user('courier', 'approved');
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'GCash', 'delivery_courier_id' => $rider->id]);

        $this->actingAsUser($rider)->get(route('courier.orders.show', $order))
            ->assertOk()
            ->assertSee('Delivery confirmation is unavailable until payment verification is configured.')
            ->assertDontSee('Confirm delivered');

        $this->actingAsUser($rider)->patch(route('courier.orders.completeDelivery', $order), [
            'recipient_confirmation' => 'Buyer',
            'delivery_code' => '000000',
            'proof_file' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'OUT_FOR_DELIVERY',
            'cod_collected_amount' => null,
        ]);

        $this->patch(route('courier.orders.failDelivery', $order), [
            'failure_reason' => 'recipient_unavailable',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'OUT_FOR_DELIVERY']);
        $this->assertDatabaseMissing('delivery_attempts', ['order_id' => $order->id, 'outcome' => 'failed']);

        $secondOrder = $this->order(['status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'GCash', 'delivery_courier_id' => $rider->id]);
        $this->patch(route('courier.orders.completeDelivery', $secondOrder), [
            'recipient_confirmation' => 'Buyer',
            'delivery_code' => '000000',
            'cod_collected_amount' => '150.00',
            'proof_file' => UploadedFile::fake()->image('delivery-proof-2.jpg'),
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $secondOrder->id, 'status' => 'OUT_FOR_DELIVERY']);

        $sortingCenter = $this->user('sorting_center', 'approved');
        $this->actingAsUser($sortingCenter)->post(route('logistics.orders.return', $order))->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'OUT_FOR_DELIVERY']);
        $this->assertNull($order->fresh()->inventory_restored_at);
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
        $this->post(route('admin.moderation.suspend', $rejected), ['suspension_reason' => 'Policy violation'])->assertUnprocessable();
        $this->post(route('admin.moderation.suspend', $pendingSeller), ['suspension_reason' => 'Policy violation'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $rejected->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('users', ['id' => $pendingSeller->id, 'status' => 'pending']);
    }

    public function test_account_restoration_preserves_known_approval_and_requires_review_for_legacy_suspensions(): void
    {
        $admin = $this->user('admin', 'approved');
        $approvedSeller = $this->user('seller', 'approved');
        $legacySuspendedSeller = $this->user('seller', 'suspended');

        $this->actingAsUser($admin)->post(route('admin.moderation.suspend', $approvedSeller), [
            'suspension_reason' => 'Repeated policy violations.',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $approvedSeller->id,
            'status' => 'suspended',
            'suspension_previous_status' => 'approved',
        ]);

        $this->post(route('admin.moderation.reactivate', $approvedSeller))->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $approvedSeller->id,
            'status' => 'approved',
            'suspension_previous_status' => null,
        ]);

        $this->post(route('admin.moderation.reactivate', $legacySuspendedSeller))->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $legacySuspendedSeller->id, 'status' => 'suspended']);
        $this->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertSee('Prior status is unknown. Review the account before restoring it.')
            ->assertSee('Restore status after review');

        $this->post(route('admin.moderation.reactivate', $legacySuspendedSeller), [
            'restored_status' => 'pending',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $legacySuspendedSeller->id,
            'status' => 'pending',
            'suspension_previous_status' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $legacySuspendedSeller->id,
            'type' => AccountStatusNotification::class,
        ]);
        $this->assertSame('pending', $legacySuspendedSeller->notifications()->firstOrFail()->data['status']);
    }

    public function test_remembered_session_is_invalidated_after_the_account_is_suspended(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $this->post(route('login.post'), [
            'email' => $buyer->email,
            'password' => 'password',
            'remember' => true,
        ])->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($buyer);
        $this->assertNotNull($buyer->fresh()->remember_token);

        $buyer->forceFill(['status' => 'suspended'])->save();
        Auth::forgetGuards();

        $this->get(route('buyer.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account is no longer active. Please contact support.');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'status' => 'suspended', 'remember_token' => null]);
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

    public function test_login_attempt_limits_do_not_block_password_reset_or_registration(): void
    {
        Notification::fake();
        $user = $this->user('buyer', 'approved');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.post'), ['email' => $user->email, 'password' => 'bad-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account matches that email address, a password reset link has been sent.');
        $this->post(route('register.post'))->assertSessionHasErrors('first_name');
    }

    public function test_logistics_rider_review_actions_are_throttled(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'pending');
        $this->actingAsUser($center);

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->post(route('logistics.riders.approve', $rider));
        }

        $this->assertSame('approved', $rider->fresh()->status);
        $this->post(route('logistics.riders.suspend', $rider), ['reason' => 'Policy review.'])->assertRedirect();
        $this->assertSame('suspended', $rider->fresh()->status);
        $this->post(route('logistics.riders.approve', $rider))->assertTooManyRequests();
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
        $this->post(route('admin.registrations.reject', $approvedUser))->assertUnprocessable();
        $this->post(route('admin.registrations.reject', $rejectedUser))->assertRedirect();
        $this->post(route('admin.registrations.approve', $rejectedUser))->assertUnprocessable();
        $this->actingAsUser($center)->post(route('logistics.riders.approve', $approvedRider))->assertRedirect();
        $this->post(route('logistics.riders.reject', $approvedRider))->assertNotFound();
        $this->post(route('logistics.riders.reject', $rejectedRider))->assertRedirect();
        $this->post(route('logistics.riders.approve', $rejectedRider))->assertNotFound();

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

    public function test_logistics_can_reactivate_only_couriers_it_suspended(): void
    {
        Notification::fake();
        $admin = $this->user('admin', 'approved');
        $center = $this->user('sorting_center', 'approved');
        $adminSuspendedRider = $this->user('courier', 'approved');
        $logisticsSuspendedRider = $this->user('courier', 'approved');

        $this->actingAsUser($admin)->post(route('admin.moderation.suspend', $adminSuspendedRider), [
            'suspension_reason' => 'Admin review required.',
        ])->assertRedirect();
        Notification::assertSentTo($adminSuspendedRider, AccountStatusNotification::class, function (AccountStatusNotification $notification) use ($adminSuspendedRider): bool {
            return $notification->status === 'suspended'
                && str_contains($notification->toDatabase($adminSuspendedRider)['message'], 'Admin review required.');
        });
        $this->actingAsUser($center)->post(route('logistics.riders.reactivate', $adminSuspendedRider))->assertNotFound();
        $this->get(route('logistics.riders'))->assertOk()->assertSee('Admin review required');
        $this->assertDatabaseHas('users', [
            'id' => $adminSuspendedRider->id,
            'status' => 'suspended',
            'suspension_source' => 'admin',
        ]);

        $this->post(route('logistics.riders.suspend', $logisticsSuspendedRider), ['reason' => 'Credential review.'])->assertRedirect();
        Notification::assertSentTo($logisticsSuspendedRider, AccountStatusNotification::class, fn (AccountStatusNotification $notification): bool => $notification->status === 'suspended');
        $this->assertDatabaseHas('users', [
            'id' => $logisticsSuspendedRider->id,
            'status' => 'suspended',
            'suspension_source' => 'logistics',
            'suspension_previous_status' => 'approved',
        ]);
        $this->post(route('logistics.riders.reactivate', $logisticsSuspendedRider))->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $logisticsSuspendedRider->id,
            'status' => 'approved',
            'suspension_source' => null,
            'suspension_previous_status' => null,
        ]);
    }

    public function test_suspending_a_rider_opens_visible_exception_cases_for_active_parcels(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'OUT_FOR_DELIVERY',
            'delivery_courier_id' => $rider->id,
            'sorting_center_id' => $center->id,
        ]);

        $this->actingAsUser($center)->post(route('logistics.riders.suspend', $rider), [
            'reason' => 'Delivery credential needs review.',
        ])->assertRedirect();

        $this->assertDatabaseHas('logistics_exceptions', [
            'order_id' => $order->id,
            'previous_rider_id' => $rider->id,
            'opened_by' => $center->id,
            'status' => 'OPEN',
        ]);
        $this->get(route('logistics.dashboard'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Delivery credential needs review.')
            ->assertSee('Scan recovered parcel');

        DB::table('storage_locations')->insert([
            'hub_id' => $center->id,
            'code' => 'EXC-SUSP-01',
            'label' => 'Suspended rider quarantine',
            'type' => 'EXCEPTION',
            'area_id' => null,
            'capacity' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->post(route('logistics.scan'), [
            'reference' => 'EZP:'.$order->parcel_code,
            'location_reference' => 'EZL:EXC-SUSP-01',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'SORTED', 'delivery_courier_id' => null]);
        $this->assertDatabaseHas('logistics_exceptions', ['order_id' => $order->id, 'status' => 'RESOLVED']);
        $this->assertDatabaseHas('scan_events', ['order_id' => $order->id, 'station' => 'rider_exception_intake', 'result' => 'accepted']);
    }

    public function test_suspended_rider_accepted_pickup_can_be_reassigned_with_exception_history(): void
    {
        $center = $this->user('sorting_center', 'approved');
        $formerRider = $this->user('courier', 'approved');
        $replacementRider = $this->user('courier', 'approved');
        $order = $this->order([
            'status' => 'READY_FOR_PICKUP',
            'pickup_requested_at' => now(),
            'pickup_claimed_at' => now(),
            'pickup_courier_id' => $formerRider->id,
        ]);

        $this->actingAsUser($center)->post(route('logistics.riders.suspend', $formerRider), [
            'reason' => 'Rider became unavailable after accepting pickup.',
        ])->assertRedirect();
        $this->post(route('logistics.orders.assignPickup', $order), ['pickup_courier_id' => $replacementRider->id])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'pickup_courier_id' => $replacementRider->id]);
        $this->assertDatabaseHas('logistics_exceptions', [
            'order_id' => $order->id,
            'previous_rider_id' => $formerRider->id,
            'new_rider_id' => $replacementRider->id,
            'status' => 'RESOLVED',
        ]);
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

    public function test_seller_can_cancel_before_handover_with_a_reason_and_notify_the_buyer(): void
    {
        Notification::fake();
        $order = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_requested_at' => now()]);
        $product = $this->addOrderInventoryItem($order, 2);

        $this->actingAsUser($order->seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee(route('seller.orders.cancel', $order));
        $this->post(route('seller.orders.cancel', $order), ['reason' => 'The reserved item failed final inspection.'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseHas('parcel_tracking_events', [
            'order_id' => $order->id,
            'event_type' => 'seller_cancelled',
            'notes' => 'Seller cancelled the order: The reserved item failed final inspection.',
        ]);
        Notification::assertSentTo($order->buyer, OrderStatusNotification::class, fn (OrderStatusNotification $notification): bool => $notification->status === 'CANCELLED');
    }

    public function test_admin_can_cancel_pre_shipment_order_and_cannot_cancel_after_physical_handover(): void
    {
        $admin = $this->user('admin', 'approved');
        $order = $this->order(['status' => 'CONFIRMED']);
        $product = $this->addOrderInventoryItem($order, 1);

        $this->actingAsUser($admin)->post(route('admin.orders.cancel', $order), ['reason' => 'Admin intervention: duplicate order.'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'admin_cancelled']);

        $handedOrder = $this->order([
            'status' => 'READY_FOR_PICKUP',
            'seller_handover_at' => now(),
        ]);
        $this->post(route('admin.orders.cancel', $handedOrder), ['reason' => 'Attempted post-handover cancellation.'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $handedOrder->id, 'status' => 'READY_FOR_PICKUP']);
    }

    public function test_seller_confirmation_and_preparation_are_separate_locked_transitions(): void
    {
        $order = $this->order();
        $product = $this->addOrderInventoryItem($order, 2);
        $seller = $order->seller;

        $this->actingAsUser($seller)->get(route('seller.orders.index'))->assertOk();
        $this->get(route('seller.orders.show', $order))->assertOk();
        $this->actingAsUser($seller)->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CONFIRMED']);
        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'PREPARING'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PREPARING']);
        $this->assertDatabaseHas('parcel_tracking_events', ['order_id' => $order->id, 'event_type' => 'confirmed']);
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_seller_confirmation_checks_reserved_inventory_without_decrementing_it_again(): void
    {
        $emptyOrder = $this->order();
        $this->actingAsUser($emptyOrder->seller)
            ->patch(route('seller.orders.updateStatus', $emptyOrder), ['status' => 'CONFIRMED'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $emptyOrder->id, 'status' => 'PLACED']);

        $order = $this->order();
        $product = $this->addOrderInventoryItem($order, 2);
        $this->actingAsUser($order->seller)
            ->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])
            ->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 0]);

        $archivedOrder = $this->order();
        $this->addOrderInventoryItem($archivedOrder, 1, true);
        $this->actingAsUser($archivedOrder->seller)
            ->patch(route('seller.orders.updateStatus', $archivedOrder), ['status' => 'CONFIRMED'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $archivedOrder->id, 'status' => 'PLACED']);

        $flaggedOrder = $this->order();
        $this->addOrderInventoryItem($flaggedOrder, 1, false, 'flagged');
        $this->actingAsUser($flaggedOrder->seller)
            ->patch(route('seller.orders.updateStatus', $flaggedOrder), ['status' => 'CONFIRMED'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $flaggedOrder->id, 'status' => 'PLACED']);

        $restoredOrder = $this->order();
        $restoredProduct = $this->addOrderInventoryItem($restoredOrder, 1);
        app(InventoryRestorationService::class)->restore($restoredOrder);
        $this->actingAsUser($restoredOrder->seller)
            ->patch(route('seller.orders.updateStatus', $restoredOrder), ['status' => 'CONFIRMED'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $restoredProduct->id, 'stock' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $restoredOrder->id, 'status' => 'PLACED']);
    }

    public function test_cashless_orders_remain_held_until_payment_verification_is_configured(): void
    {
        $order = $this->order(['payment_method' => 'GCash']);
        $seller = $order->seller;

        $this->actingAsUser($seller)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Fulfillment is on hold because payment verification for GCash is not configured yet.')
            ->assertDontSee('Accept order');

        $this->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PLACED']);

        $legacyLogisticsOrder = $this->order(['payment_method' => 'GCash', 'status' => 'SORTED']);
        $sortingCenter = $this->user('sorting_center', 'approved');
        $rider = $this->user('courier', 'approved');
        $this->actingAsUser($sortingCenter)
            ->post(route('logistics.orders.assignRider', $legacyLogisticsOrder), ['delivery_courier_id' => $rider->id])
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $legacyLogisticsOrder->id, 'status' => 'SORTED']);

        $legacyPickupOrder = $this->order([
            'payment_method' => 'GCash',
            'status' => 'READY_FOR_PICKUP',
            'pickup_courier_id' => $rider->id,
            'pickup_claimed_at' => now(),
            'pickup_arrived_at' => now(),
            'pickup_requested_at' => now(),
        ]);
        $legacyPickupBadge = app(RiderBadgeService::class)->ensure($rider);
        $this->actingAsUser($legacyPickupOrder->seller)
            ->post(route('seller.orders.confirmHandover', $legacyPickupOrder), [
                'rider_badge' => 'EZR:'.$legacyPickupBadge,
            ])
            ->assertUnprocessable();
        $this->actingAsUser($rider)
            ->post(route('courier.orders.confirmPickup', $legacyPickupOrder))
            ->assertUnprocessable();
        $this->assertDatabaseHas('orders', [
            'id' => $legacyPickupOrder->id,
            'status' => 'READY_FOR_PICKUP',
            'seller_handover_at' => null,
        ]);

        $this->actingAsUser($order->buyer)->get(route('buyer.orders.show', $order))
            ->assertOk()
            ->assertSee('Fulfillment is on hold because payment verification for GCash is not configured yet.');

        $this->withSession(['cart' => [[
            'product_id' => 1,
            'name' => 'Payment hold test item',
            'price' => 100,
            'quantity' => 1,
            'variation_id' => null,
        ]]])->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('value="COD"', false)
            ->assertDontSee('value="GCash"', false)
            ->assertDontSee('value="Bank Transfer"', false)
            ->assertSee('Cash on delivery is the available payment method while payment verification for other methods is being configured.');

        $product = Product::query()->create([
            'user_id' => $seller->id,
            'name' => 'Bank transfer hold test item',
            'description' => 'Checkout notification test item',
            'category' => 'Test',
            'price' => 100,
            'stock' => 2,
            'is_archived' => false,
        ]);
        $product->forceFill(['compliance_status' => 'approved'])->save();

        $this->actingAsUser($order->buyer)->withSession(['cart' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 100,
            'quantity' => 1,
            'variation_id' => null,
        ]]])->post(route('checkout.process'), [
            'recipient_name' => 'Payment Hold Buyer',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '2 Test Street',
            'payment_method' => 'Bank Transfer',
        ])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
    }

    public function test_order_notifications_are_visible_only_to_the_notifiable_account(): void
    {
        $order = $this->order();
        $this->addOrderInventoryItem($order);
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

    public function test_notification_database_record_and_email_are_discarded_when_order_transaction_rolls_back(): void
    {
        Mail::fake();
        $recipient = $this->user('buyer', 'approved');
        $sender = new TransactionAwareNotificationSender;

        try {
            DB::transaction(function () use ($recipient, $sender): void {
                $sender->send($recipient, new AccountStatusNotification('approved'));
                $this->assertDatabaseHas('notifications', [
                    'notifiable_id' => $recipient->id,
                    'type' => AccountStatusNotification::class,
                ]);
                Mail::assertNothingOutgoing();

                throw new \RuntimeException('Force the notification transaction to roll back.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Force the notification transaction to roll back.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $recipient->id,
            'type' => AccountStatusNotification::class,
        ]);
        Mail::assertNothingOutgoing();
    }

    public function test_mail_transport_failure_does_not_roll_back_order_transition(): void
    {
        config(['mail.default' => 'missing-mailer']);
        $order = $this->order();
        $this->addOrderInventoryItem($order);

        $this->actingAsUser($order->seller)
            ->patch(route('seller.orders.updateStatus', $order), ['status' => 'CONFIRMED'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CONFIRMED']);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $order->buyer_id,
            'type' => OrderStatusNotification::class,
        ]);
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
        $this->assertDatabaseHas('orders', ['id' => $activeOrder->id, 'status' => 'DELIVERY_FAILED']);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $activeOrder->id, 'attempt_no' => 1, 'outcome' => 'failed']);
        $this->actingAsUser($rider)->get(route('courier.dashboard'))
            ->assertViewHas('stats', fn (array $stats): bool => $stats['failed_today'] === 1);
        $locationId = DB::table('storage_locations')->insertGetId([
            'hub_id' => $center->id, 'code' => 'RET-MAX-01', 'label' => 'Returns', 'type' => 'RETURNS',
            'area_id' => $activeOrder->destination_area_id, 'capacity' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAsUser($center)->post(route('logistics.scan'), [
            'reference' => 'EZP:'.$activeOrder->parcel_code,
            'location_reference' => 'EZL:RET-MAX-01',
        ])->assertRedirect();
        $this->post(route('logistics.orders.assignRider', $activeOrder), ['delivery_courier_id' => $rider->id])->assertRedirect();
        $this->post(route('logistics.orders.releaseToRider', $activeOrder))->assertRedirect();
        $this->actingAsUser($rider)->post(route('courier.orders.startDelivery', $activeOrder))->assertUnprocessable();
        $this->actingAsUser($center);
        $this->post(route('logistics.orders.return', $activeOrder))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $activeOrder->id, 'status' => 'RETURN_IN_TRANSIT']);
        $this->assertDatabaseHas('parcel_placements', ['order_id' => $activeOrder->id, 'location_id' => $locationId, 'active_order_id' => $activeOrder->id]);
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
            'payment_method' => 'COD',
            'status' => 'PLACED',
        ], $extra));
    }

    private function issueDeliveryCode(Order $order): string
    {
        $code = '246810';
        $order->forceFill([
            'delivery_code_hash' => Hash::make($code),
            'delivery_code_encrypted' => Crypt::encryptString($code),
            'delivery_code_expires_at' => now()->addHour(),
            'delivery_code_attempts' => 0,
            'delivery_code_used_at' => null,
        ])->save();

        return $code;
    }

    private function addOrderInventoryItem(
        Order $order,
        int $quantity = 1,
        bool $archived = false,
        string $complianceStatus = 'approved',
    ): Product {
        $product = Product::query()->create([
            'user_id' => $order->seller_id,
            'name' => 'Reserved order item',
            'description' => 'Inventory reserved at checkout.',
            'category' => 'Test',
            'price' => 100,
            'stock' => 0,
            'is_archived' => $archived,
        ]);
        $product->forceFill(['compliance_status' => $complianceStatus])->save();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variation_id' => null,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => $quantity,
            'item_total' => 100 * $quantity,
        ]);

        return $product;
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
