<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\ApiToken;
use App\Models\DispatchCycle;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\ParcelTransfer;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use App\Services\InventoryRestorationService;
use App\Services\OrderFinancialService;
use App\Services\OrderProgressService;
use App\Services\OrderTransitionService;
use App\Services\ParcelIncidentService;
use App\Services\ParcelLoadService;
use App\Services\ParcelTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class MissingParcelIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_lost_report_preserves_custody_load_and_money_and_is_visible_to_affected_parties_once(): void
    {
        Notification::fake();
        [$hub, $rider, $order] = $this->crew();
        $data = $this->data($order);
        $incident = app(ParcelIncidentService::class)->report($order, $hub, $data, null);
        $again = app(ParcelIncidentService::class)->report($order, $hub, $data, null);
        $this->assertSame($incident->id, $again->id);
        $this->assertSame('PICKED_UP', $order->fresh()->status);
        $this->assertSame('LOST', $order->fresh()->operational_status);
        $this->assertSame('LOST', app(OrderProgressService::class)->describe($order->fresh())['status']);
        $this->assertSame(1000, app(ParcelLoadService::class)->summary($rider)['grams']);
        $this->assertDatabaseCount('parcel_incidents', 1);
        $this->assertDatabaseCount('refund_executions', 0);
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->as($order->buyer)->get(route('parcel-incidents.show', $incident))->assertSee('Fulfillment on hold')->assertSee('No automatic stock or money change');
        $this->as($order->seller)->get(route('parcel-incidents.index'))->assertSee($order->order_number);
        $token = ApiToken::createTokenForUser($order->buyer)['plainTextToken'];
        $this->withToken($token)->getJson('/api/orders/'.$order->id)->assertOk()->assertJsonPath('data.status', 'PICKED_UP')->assertJsonPath('data.incident_status', 'LOST')->assertJsonPath('data.fulfillment_on_hold', true);
        Notification::assertSentTo($order->buyer, WebOperationsNotification::class);
        Notification::assertSentTo($order->seller, WebOperationsNotification::class);
    }

    public function test_damage_requires_a_valid_private_photo_and_reason_is_escaped_and_scoped(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        [$hub, $rider, $order] = $this->crew();
        $data = $this->data($order, 'DAMAGED');
        $this->as($hub)->post(route('parcel-incidents.store'), $data)->assertSessionHasErrors('evidence');
        $this->post(route('parcel-incidents.store'), [...$data, 'evidence' => UploadedFile::fake()->create('pretend.jpg', 10, 'application/pdf')])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('parcel_incidents', 0);
        $reason = 'Parcel packaging is damaged. <script>bad()</script>';
        $this->post(route('parcel-incidents.store'), [...$data, 'reason' => $reason, 'evidence' => UploadedFile::fake()->image('damage.jpg')])->assertSessionHasNoErrors();
        $incident = ParcelIncident::sole();
        Storage::disk('private')->assertExists($incident->evidence_path);
        Storage::disk('public')->assertMissing($incident->evidence_path);
        $this->as($order->buyer)->get(route('parcel-incidents.show', $incident))->assertSee($reason)->assertDontSee('<script>bad()</script>', false);
        $this->get(route('parcel-incidents.evidence', [$incident, 'report']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->as($this->user('seller'))->get(route('parcel-incidents.show', $incident))->assertNotFound();
        $this->get(route('parcel-incidents.evidence', [$incident, 'report']))->assertNotFound();
        $this->assertArrayNotHasKey('evidence_path', $incident->toArray());
        $this->assertArrayNotHasKey('custody_snapshot', $incident->toArray());
    }

    #[TestWith(['transition', 'PICKED_UP'])]
    #[TestWith(['stock', 'RETURNED_TO_SELLER'])]
    #[TestWith(['payout', 'COMPLETED'])]
    #[TestWith(['transfer', 'SORTED'])]
    public function test_incident_hold_blocks_existing_movement_stock_and_earnings_services(string $operation, string $status): void
    {
        [$hub, $rider, $order] = $this->crew($status);
        $incident = app(ParcelIncidentService::class)->report($order, $hub, $this->data($order), null);
        try {
            match ($operation) {
                'transition' => app(OrderTransitionService::class)->transition($order, $hub, OrderStatus::AtSortingCenter, 'must_not_move'),
                'stock' => app(InventoryRestorationService::class)->restore($order),
                'payout' => app(OrderFinancialService::class)->requestPayout($order, $order->seller),
                'transfer' => app(ParcelTransferService::class)->plan($order, $this->user('logistics_admin'), $this->user('sorting_center'), 'Transfer must stay blocked by the incident.'),
            };
            $this->fail('Incident hold was bypassed.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(422, $error->getStatusCode());
            $this->assertStringContainsString('incident hold', $error->getMessage());
        }
        $this->assertSame($status, $order->fresh()->status);
        $this->assertNull($order->fresh()->inventory_restored_at);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('seller_payouts', 0);
    }

    public function test_incident_on_a_hub_manifest_blocks_receipt_and_keeps_driver_load_and_manifest(): void
    {
        [$hub, $rider, $order] = $this->crew('SORTED');
        $destination = $this->user('sorting_center');
        $rider->forceFill(['hub_transfer_only' => true])->save();
        $transfer = ParcelTransfer::forceCreate(['order_id' => $order->id, 'active_order_id' => $order->id, 'source_hub_id' => $hub->id, 'destination_hub_id' => $destination->id, 'carrier_rider_id' => $rider->id, 'requested_by' => $hub->id, 'status' => 'IN_TRANSIT', 'reason' => 'Synthetic released manifest', 'released_by' => $hub->id, 'released_at' => now()]);
        app(ParcelIncidentService::class)->report($order, $hub, $this->data($order), null);
        try {
            app(OrderTransitionService::class)->receiveHubTransfer($transfer, $destination, 'EZP:'.$order->parcel_code);
            $this->fail('Incident transfer was received.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(422, $error->getStatusCode());
        }
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertSame($order->id, $transfer->fresh()->active_order_id);
        $this->assertSame(1000, app(ParcelLoadService::class)->summary($rider)['grams']);
    }

    public function test_provisional_offers_are_withdrawn_but_physical_load_is_not_freed(): void
    {
        [$hub, $rider, $order] = $this->crew();
        $cycle = DispatchCycle::create(['order_id' => $order->id, 'active_order_id' => $order->id, 'hub_id' => $hub->id, 'operation_key' => (string) Str::uuid(), 'kind' => 'pickup', 'status' => 'OFFERING']);
        $offer = DispatchOffer::create(['dispatch_cycle_id' => $cycle->id, 'active_cycle_id' => $cycle->id, 'order_id' => $order->id, 'rider_id' => $rider->id, 'status' => 'PENDING', 'weight_grams' => 1000, 'distance_meters' => 1, 'expires_at' => now()->addMinute()]);
        app(ParcelIncidentService::class)->report($order, $hub, $this->data($order), null);
        $this->assertSame('WITHDRAWN', $offer->fresh()->status);
        $this->assertSame('CANCELLED', $cycle->fresh()->status);
        $this->assertSame(1000, app(ParcelLoadService::class)->summary($rider)['grams']);
        $this->assertSame($rider->id, $order->fresh()->pickup_courier_id);
    }

    public function test_uncollected_cod_claim_assessment_does_not_invent_a_refund_or_clear_custody_hold(): void
    {
        [$hub, $rider, $order] = $this->crew();
        $incident = app(ParcelIncidentService::class)->report($order, $hub, $this->data($order), null);
        $admin = $this->user('ecommerce_admin');
        $this->as($admin)->post(route('parcel-incidents.review', $incident), ['version' => 1, 'reason' => 'No collected COD exists; investigate parcel recovery and responsibility.'])->assertSessionHasNoErrors();
        $this->assertSame('NO_COLLECTED_COD', $incident->fresh()->claim_status);
        $this->assertSame('OPEN', $incident->fresh()->status);
        $this->assertSame($order->id, $incident->fresh()->open_order_id);
        $this->post(route('parcel-incidents.review', $incident), ['version' => 1, 'reason' => 'A stale screen must not overwrite the recorded assessment.'])->assertConflict();
        $this->assertDatabaseCount('refund_executions', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_collected_cod_is_reviewed_as_existing_money_without_a_second_collection_or_payment(): void
    {
        Storage::fake('private');
        [$hub, $rider, $order] = $this->crew('DELIVERED');
        $order->forceFill(['delivery_code_used_at' => now(), 'delivered_at' => now(), 'cod_collected_amount' => 150])->save();
        Storage::disk('private')->put('delivery/synthetic.jpg', 'Synthetic proof for existing collection.');
        $order->deliveryAttempts()->create(['rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => 'delivery/synthetic.jpg', 'attempted_at' => now()]);
        app(OrderFinancialService::class)->recordCollection($order, $rider);
        $incident = app(ParcelIncidentService::class)->report($order, $hub, $this->data($order), null);
        $this->assertSame('COLLECTED_COD_REVIEW', app(ParcelIncidentService::class)->assessment($incident));
        $this->as($order->buyer)->post(route('buyer.orders.confirm', $order))->assertUnprocessable();
        $this->assertFalse(app(OrderTransitionService::class)->completeAutomatically($order));
        $this->assertSame(15000, $order->finance()->sole()->collected_cents);
        $this->assertDatabaseCount('refund_executions', 0);
    }

    public function test_independent_label_and_photo_reconciliation_clears_only_the_hold_and_tamper_is_detected(): void
    {
        Storage::fake('private');
        [$hub, $rider, $order] = $this->crew();
        $incident = app(ParcelIncidentService::class)->report($order, $hub, $this->data($order), null);
        $admin = $this->user('logistics_admin');
        $payload = ['version' => 1, 'parcel_reference' => 'EZP:'.$order->parcel_code, 'reason' => 'Physical parcel recovered with its recorded custodian; label checked.'];
        $this->as($admin)->post(route('parcel-incidents.clear', $incident), $payload)->assertSessionHasErrors('evidence');
        $this->post(route('parcel-incidents.clear', $incident), [...$payload, 'parcel_reference' => 'EZP:wrong', 'evidence' => UploadedFile::fake()->image('proof.jpg')])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('private')->allFiles('parcel-incidents/reconciliation'));
        $this->post(route('parcel-incidents.clear', $incident), [...$payload, 'evidence' => UploadedFile::fake()->image('proof.jpg')])->assertSessionHasNoErrors();
        $this->assertSame('RESOLVED', $incident->fresh()->status);
        $this->assertNull($order->fresh()->activeIncident);
        $this->assertSame('PICKED_UP', $order->fresh()->status);
        $this->assertSame(1000, app(ParcelLoadService::class)->summary($rider)['grams']);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->get(route('parcel-incidents.evidence', [$incident, 'resolution']))->assertOk();
        Storage::disk('private')->put($incident->fresh()->resolution_evidence_path, 'tampered');
        $this->get(route('parcel-incidents.evidence', [$incident, 'resolution']))->assertConflict();
    }

    public function test_migration_can_reverse_and_reapply_without_changing_existing_physical_statuses(): void
    {
        [$hub, $rider, $order] = $this->crew();
        $migration = require database_path('migrations/2026_11_15_014300_create_dispatch_offers_and_parcel_incidents.php');
        $migration->down();
        $migration->up();
        $this->assertSame('PICKED_UP', $order->fresh()->status);
        $this->assertDatabaseCount('dispatch_cycles', 0);
        $this->assertDatabaseCount('parcel_incidents', 0);
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Incident', 'last_name' => 'Fixture', 'contact_no' => '09123456789', 'email_verified_at' => now(), ...$extra]);
    }

    private function crew(string $status = 'PICKED_UP'): array
    {
        $hub = $this->user('sorting_center');
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id, 'work_availability' => 'available', 'vehicle_capacity_grams' => 10000, 'vehicle_capacity_verified_at' => now()]);
        $order = Order::forceCreate(['order_number' => 'INC-'.Str::random(12), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'pickup_courier_id' => $rider->id, 'delivery_courier_id' => $status === 'DELIVERED' ? $rider->id : null, 'sorting_center_id' => $hub->id, 'recipient_name' => 'Synthetic recipient', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Fixture', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'commission_fee' => 10, 'status' => $status, 'payment_method' => 'COD', 'parcel_weight_grams' => 1000, 'picked_up_at' => now()]);

        return [$hub, $rider, $order];
    }

    private function data(Order $order, string $kind = 'LOST'): array
    {
        return ['order_id' => $order->id, 'kind' => $kind, 'reason' => 'Synthetic parcel incident requires custody and claim review.', 'operation_key' => (string) Str::uuid()];
    }
}
