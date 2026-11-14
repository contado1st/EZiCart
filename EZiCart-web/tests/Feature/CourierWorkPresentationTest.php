<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\Dispute;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\ShipmentFinancial;
use App\Models\ShipmentPriceAdjustment;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Services\CourierCompensationService;
use App\Services\DeliveryPricingService;
use App\Services\DeliveryRateManagementService;
use App\Services\FinancialLedgerService;
use App\Services\LogisticsSettlementService;
use App\Services\StaffProfileService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CourierWorkPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['financial.sandbox_enabled' => true]);
        Storage::fake('private');
        Notification::fake();
    }

    public function test_profile_combines_official_vehicle_and_real_private_file_state_without_exposing_paths(): void
    {
        $hub = $this->user('sorting_center', ['business_name' => 'Synthetic assigned hub']);
        $rider = $this->user('courier', ['first_name' => 'Officialrider', 'logistics_owner_id' => $hub->id, 'license_number' => 'A01-01-123456',
            'license_path' => 'private-rider/license.pdf', 'or_cr_path' => 'private-rider/missing.pdf', 'plate_number' => 'ABC 123']);
        Storage::disk('private')->put($rider->license_path, '%PDF-1.4 synthetic licence');
        $area = Area::create(['name' => 'Synthetic coverage', 'code' => 'PROFILE-QA', 'sorting_center_id' => $hub->id, 'is_active' => true]);
        $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);

        $response = $this->signIn($rider)->get(route('courier.profile', ['user_id' => $hub->id]))->assertOk();
        $response->assertSee('Officialrider')->assertSee('ABC 123')->assertSee('Synthetic assigned hub')->assertSee('Synthetic coverage')
            ->assertSee('100.000 kg')->assertSee('File available')->assertSee('Recorded file is unavailable')->assertSee('Not submitted')
            ->assertDontSee('private-rider/')->assertSee(route('account.documents.show', ['type' => 'license', 'preview' => 1]), false);
        $this->get(route('account.documents.show', 'license'))->assertDownload('license.pdf');
        $this->get(route('account.documents.show', 'vehicle'))->assertNotFound();
        $this->get(route('logistics.riders.documents.show', ['user' => $rider->id, 'type' => 'license']))->assertForbidden();
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_pending_profile_is_not_displayed_as_official_and_approved_vehicle_change_requires_new_capacity_verification(): void
    {
        $rider = $this->user('courier', ['first_name' => 'Officialrider', 'plate_number' => 'OLD 123']);
        $revision = app(StaffProfileService::class)->submit($rider, $rider, ['first_name' => 'Proposedrider', 'plate_number' => 'NEW 456']);
        $this->signIn($rider)->get(route('courier.profile'))->assertOk()->assertSee('awaiting logistics admin review')->assertSee('Officialrider')
            ->assertSee('OLD 123')->assertDontSee('Proposedrider')->assertDontSee('NEW 456');
        app(StaffProfileService::class)->review($this->user('logistics_admin'), $revision, true, 'Synthetic verification');

        $this->signIn($rider->fresh())->get(route('courier.profile'))->assertOk()->assertSee('Proposedrider')->assertSee('NEW 456')->assertSee('Not verified')
            ->assertDontSee('awaiting logistics admin review');
        $this->assertNull($rider->fresh()->vehicle_capacity_verified_at);
        $this->assertSame('available', $rider->fresh()->work_availability);
    }

    public function test_missing_license_expiry_and_expired_license_are_not_presented_as_verified(): void
    {
        $rider = $this->user('courier', ['license_expires_at' => null]);
        $this->signIn($rider)->get(route('courier.profile'))->assertOk()->assertSee('Not recorded — ask logistics to review');
        $rider->forceFill(['license_expires_at' => today()->subDay()])->save();
        $this->get(route('courier.profile'))->assertOk()->assertSee('Expired');
    }

    public function test_profile_is_role_guarded_and_document_route_does_not_accept_another_user_query(): void
    {
        $this->get(route('courier.profile'))->assertRedirectToRoute('login');
        $this->signIn($this->user('buyer'))->get(route('courier.profile'))->assertForbidden();
        $other = $this->user('courier', ['license_path' => 'other/licence.pdf']);
        Storage::disk('private')->put($other->license_path, '%PDF-1.4 other licence');
        $rider = $this->user('courier');
        $this->signIn($rider)->get(route('account.documents.show', ['type' => 'license', 'user_id' => $other->id]))->assertNotFound();
        $this->get(route('courier.profile', ['user_id' => $other->id]))->assertOk()->assertViewHas('courier', fn ($user) => $user->id === $rider->id);
    }

    public function test_history_includes_own_transfer_legs_once_per_order_and_excludes_other_riders(): void
    {
        $rider = $this->user('courier', ['hub_transfer_only' => true]);
        $order = $this->order(['status' => 'COMPLETED', 'delivery_notes' => 'Other rider private recipient note']);
        $first = $this->transfer($order, $rider);
        $second = $this->transfer($order, $rider);
        $this->transfer($order, $this->user('courier'));
        $this->schedule($order, ['linehaul' => 1000]);
        foreach (range(1, 2) as $attempt) {
            DeliveryAssignment::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'assigned_by' => $order->sorting_center_id, 'status' => 'reassigned', 'assigned_at' => now()]);
        }
        $private = $this->order(['order_number' => 'OTHER-PRIVATE-ORDER', 'pickup_courier_id' => $this->user('courier')->id]);

        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertDontSee($private->order_number)->assertDontSee('Other rider private recipient note');
        $this->assertSame(1, $response->viewData('orders')->total());
        $rows = $response->viewData('workSummaries')[$order->id]['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame(['transfer-'.$first->id, 'transfer-'.$second->id], array_column($rows, 'leg'));
        $this->assertSame(['Pending settlement', 'Pending settlement'], array_column($rows, 'status'));
        $this->assertSame([1000, 1000], array_column($rows, 'amount_cents'));
        $this->assertSame(0, $response->viewData('pageSettledCents'));
        $response->assertSee(route('courier.history'), false)->assertSee('Work history');
    }

    public function test_verified_allocations_reconcile_per_order_and_multiple_legs_without_double_counting(): void
    {
        $rider = $this->user('courier');
        $order = $this->settledOrder($rider);

        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk();
        $this->assertSame(1, $response->viewData('orders')->total());
        $summary = $response->viewData('workSummaries')[$order->id];
        $this->assertCount(4, $summary['rows']);
        $this->assertSame(['Settled'], array_values(array_unique(array_column($summary['rows'], 'status'))));
        $this->assertSame(7000, $summary['settled_cents']);
        $this->assertSame(7000, $response->viewData('pageSettledCents'));
        $this->get(route('courier.earnings'))->assertOk()->assertViewHas('available', 7000)->assertSee('Net settled PHP')->assertSee('Settled');
    }

    public function test_reversed_credit_is_retained_as_history_and_excluded_from_net_settled_totals(): void
    {
        $rider = $this->user('courier');
        $order = $this->settledOrder($rider);
        $transaction = LedgerTransaction::findOrFail(DB::table('logistics_allocations')->where('order_id', $order->id)->where('component', 'pickup')->value('transaction_id'));
        app(FinancialLedgerService::class)->reverse($transaction, $this->user('logistics_admin'), 'Synthetic posting reversal');

        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Reversed');
        $this->assertSame(5000, $response->viewData('pageSettledCents'));
        $this->assertCount(4, $response->viewData('workSummaries')[$order->id]['rows']);
        $this->get(route('courier.earnings'))->assertOk()->assertViewHas('available', 5000)->assertSee('Reversed');
    }

    public function test_history_retains_a_credited_job_even_if_current_rider_foreign_keys_changed(): void
    {
        $rider = $this->user('courier');
        $order = $this->settledOrder($rider);
        // Synthetic historical discrepancy: do not claim normal operations can rewrite settled custody.
        DB::table('orders')->where('id', $order->id)->update(['pickup_courier_id' => null, 'delivery_courier_id' => null]);
        DB::table('parcel_transfers')->where('order_id', $order->id)->update(['carrier_rider_id' => null]);
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk();
        $this->assertSame(1, $response->viewData('orders')->total());
        $this->assertSame(7000, $response->viewData('pageSettledCents'));
    }

    public function test_reading_pending_work_and_earnings_does_not_create_wallets_allocations_or_tracking_events(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['status' => 'PICKED_UP', 'pickup_courier_id' => $rider->id, 'picked_up_at' => now()]);
        $this->schedule($order, ['pickup' => 2000]);
        $before = $order->fresh()->getRawOriginal();
        $this->signIn($rider)->get(route('courier.orders.show', $order))->assertOk()->assertSee('Pending settlement')->assertSee('PHP 20.00')->assertSee('Possession confirmed');
        $this->get(route('courier.history'))->assertOk()->assertViewHas('pageSettledCents', 0)->assertSee('1.000 kg');
        $this->get(route('courier.earnings'))->assertOk()->assertViewHas('available', 0);
        $this->assertSame($before, $order->fresh()->getRawOriginal());
        $this->assertDatabaseCount('financial_accounts', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('logistics_allocations', 0);
        $this->assertDatabaseCount('parcel_tracking_events', 0);
        Notification::assertNothingSent();
    }

    public function test_latest_approved_adjustment_is_used_and_a_later_proposal_does_not_replace_it(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['pickup_courier_id' => $rider->id]);
        $shipment = $this->schedule($order, ['pickup' => 2000]);
        foreach ([['APPROVED', 2500], ['PROPOSED', 9000]] as [$status, $amount]) {
            $quote = $shipment->quote_snapshot;
            $quote['components']['pickup'] = $amount;
            ShipmentPriceAdjustment::create(['order_id' => $order->id, 'proposed_by' => $this->user('logistics_admin')->id, 'approved_by' => $status === 'APPROVED' ? $this->user('logistics_admin')->id : null,
                'measurements' => [], 'revised_quote' => $quote, 'difference_cents' => $amount - 2000, 'evidence_path' => 'synthetic/private-evidence.txt', 'reason' => 'Synthetic measurement adjustment', 'status' => $status]);
        }
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk();
        $row = $response->viewData('workSummaries')[$order->id]['rows'][0];
        $this->assertSame('Estimated', $row['status']);
        $this->assertSame(2500, $row['amount_cents']);
        $this->assertSame(2000, $shipment->fresh()->quote_snapshot['components']['pickup']);
        $response->assertDontSee('synthetic/private-evidence.txt');
    }

    public function test_open_dispute_holds_a_recorded_work_estimate_without_claiming_a_credit(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['status' => 'PICKED_UP', 'pickup_courier_id' => $rider->id, 'picked_up_at' => now()]);
        $this->schedule($order, ['pickup' => 2000]);
        Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Synthetic dispute', 'description' => 'Adversarial read fixture', 'status' => 'PENDING']);
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Held');
        $this->assertSame(0, $response->viewData('pageSettledCents'));
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_legacy_or_unconfigured_jobs_do_not_invent_compensation(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['pickup_courier_id' => $rider->id]);
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Unconfigured')->assertSee('Amount unavailable');
        $this->assertNull($response->viewData('workSummaries')[$order->id]['rows'][0]['amount_cents']);
        $this->assertSame(0, $response->viewData('pageSettledCents'));
    }

    #[TestWith(['camera', true])]
    #[TestWith(['manual', false])]
    public function test_scanned_and_raw_manual_pickups_have_the_same_custody_notifications_and_pending_amount(string $method, bool $prefix): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id,
            'pickup_claimed_at' => now(), 'pickup_arrived_at' => now(), 'seller_handover_at' => now()]);
        $this->schedule($order, ['pickup' => 2000]);
        $reference = ($prefix ? 'EZP:' : '').$order->parcel_code;

        $this->signIn($rider)->post(route('courier.orders.confirmPickup', $order), ['parcel_reference' => $reference, 'method' => $method])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', fn ($value) => str_contains($value, 'does not credit wallet earnings'));
        $this->assertSame('PICKED_UP', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->picked_up_at);
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'picked_up')->count());
        $this->assertDatabaseHas('scan_events', ['order_id' => $order->id, 'result' => 'accepted', 'method' => $method]);
        Notification::assertSentTo($order->buyer, OrderStatusNotification::class);
        Notification::assertSentTo($order->seller, OrderStatusNotification::class);
        $this->get(route('courier.orders.show', $order))->assertOk()->assertSee('Pending settlement')->assertSee('PHP 20.00')->assertSee('Bring this parcel to the hub');
        $this->post(route('courier.orders.confirmPickup', $order), ['parcel_reference' => $reference, 'method' => $method])->assertUnprocessable();
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'picked_up')->count());
        $this->assertDatabaseCount('logistics_allocations', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->signIn($order->buyer)->get(route('buyer.orders.show', $order))->assertOk()->assertViewHas('order', fn ($value) => $value->status === 'PICKED_UP');
        $this->signIn($order->seller)->get(route('seller.orders.show', $order))->assertOk()->assertViewHas('order', fn ($value) => $value->status === 'PICKED_UP');
    }

    public function test_order_number_is_rejected_as_a_parcel_code_and_an_unrelated_rider_cannot_open_the_job(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now(), 'pickup_arrived_at' => now(), 'seller_handover_at' => now()]);
        $this->signIn($rider)->post(route('courier.orders.confirmPickup', $order), ['parcel_reference' => $order->order_number, 'method' => 'manual'])->assertSessionHasErrors('parcel_reference');
        $this->assertSame('READY_FOR_PICKUP', $order->fresh()->status);
        $this->assertDatabaseCount('parcel_tracking_events', 0);
        Notification::assertNothingSent();
        $this->signIn($this->user('courier'))->get(route('courier.orders.show', $order))->assertForbidden();
        $this->get(route('courier.history', ['search' => $order->order_number]))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0)->assertDontSee('PHP 20.00');
    }

    public function test_history_keeps_twenty_rows_and_stable_pagination_without_creating_financial_records(): void
    {
        $rider = $this->user('courier');
        foreach (range(1, 21) as $number) {
            $this->order(['order_number' => 'PAGE-'.$number, 'pickup_courier_id' => $rider->id]);
        }
        $response = $this->signIn($rider)->get(route('courier.history', ['search' => 'PAGE-']))->assertOk();
        $this->assertSame(21, $response->viewData('orders')->total());
        $this->assertCount(20, $response->viewData('orders')->items());
        $response->assertSee('search=PAGE-', false);
        $second = $this->get(route('courier.history', ['search' => 'PAGE-', 'page' => 2]))->assertOk();
        $this->assertCount(1, $second->viewData('orders')->items());
        $this->assertSame('PAGE-1', $second->viewData('orders')->first()->order_number);
        $this->assertDatabaseCount('financial_accounts', 0);
    }

    public function test_prefetched_other_riders_transfers_and_delivery_proofs_cannot_be_used_for_my_estimates(): void
    {
        $rider = $this->user('courier');
        $other = $this->user('courier');
        $order = $this->order(['delivery_courier_id' => $rider->id]);
        $this->transfer($order, $other);
        DeliveryAttempt::create(['order_id' => $order->id, 'rider_id' => $other->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => 'private/other-proof.jpg', 'attempted_at' => now()]);
        $this->schedule($order, ['last_mile' => 3000, 'linehaul' => 1000]);
        $order->load('transfers', 'deliveryAttempts');
        $summary = app(CourierCompensationService::class)->summaries(new Collection([$order]), $rider)[$order->id];
        $this->assertCount(1, $summary['rows']);
        $this->assertSame('Estimated', $summary['rows'][0]['status']);
        $this->assertNull($summary['rows'][0]['work_recorded_at']);
    }

    public function test_a_draft_tariff_cannot_be_presented_as_an_approved_work_estimate(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['pickup_courier_id' => $rider->id]);
        $this->schedule($order, ['pickup' => 2000], false);
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Unconfigured');
        $this->assertNull($response->viewData('workSummaries')[$order->id]['rows'][0]['amount_cents']);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    #[TestWith(['wrong_wallet'])]
    #[TestWith(['wrong_order'])]
    #[TestWith(['wrong_component'])]
    public function test_an_unverifiable_allocation_is_held_and_not_added_to_settled_total(string $mismatch): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['pickup_courier_id' => $rider->id]);
        $this->schedule($order, ['pickup' => 2000]);
        $ledger = app(FinancialLedgerService::class);
        $recipient = $mismatch === 'wrong_wallet' ? $this->user('courier') : $rider;
        $transaction = $ledger->transfer('synthetic:mismatched-allocation', 'logistics_earnings', $ledger->account('synthetic:control', 'cash_control', null, 'available', true),
            $ledger->wallet($recipient), 2000, null, ['order_id' => $mismatch === 'wrong_order' ? $this->order()->id : $order->id,
                'component' => $mismatch === 'wrong_component' ? 'last_mile' : 'pickup', 'leg' => '']);
        // Adversarial legacy fixture; no normal settlement endpoint creates this mismatch.
        DB::table('logistics_allocations')->insert(['order_id' => $order->id, 'allocation_key' => 'synthetic:allocation', 'component' => 'pickup', 'beneficiary_id' => $rider->id, 'amount_cents' => 2000, 'transaction_id' => $transaction->id, 'created_at' => now()]);

        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Held');
        $this->assertSame(0, $response->viewData('pageSettledCents'));
        $this->assertCount(1, $response->viewData('workSummaries')[$order->id]['rows']);
        $this->get(route('courier.earnings'))->assertOk()->assertViewHas('available', $mismatch === 'wrong_wallet' ? 0 : 2000)->assertSee('posting could not be verified');
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_compensation_queries_are_batched_for_twenty_orders(): void
    {
        $rider = $this->user('courier');
        foreach (range(1, 20) as $number) {
            $this->order(['pickup_courier_id' => $rider->id]);
        }
        $orders = Order::where('pickup_courier_id', $rider->id)->get();
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $summaries = app(CourierCompensationService::class)->summaries($orders, $rider);
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertCount(20, $summaries);
        $this->assertLessThanOrEqual(12, $queries, 'Work summaries must not query finance per order.');
    }

    public function test_a_recorded_zero_component_is_distinct_from_an_unconfigured_tariff(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['pickup_courier_id' => $rider->id]);
        $this->schedule($order, ['pickup' => 0, 'last_mile' => 3000]);
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('No paid component')->assertSee('PHP 0.00');
        $this->assertSame(0, $response->viewData('workSummaries')[$order->id]['rows'][0]['amount_cents']);
        $this->assertSame(0, $response->viewData('pageSettledCents'));
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_a_legacy_adjustment_without_the_original_shipment_snapshot_is_unconfigured(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['pickup_courier_id' => $rider->id, 'picked_up_at' => now()]);
        $shipment = $this->schedule($order, ['pickup' => 2000]);
        ShipmentPriceAdjustment::create(['order_id' => $order->id, 'proposed_by' => $this->user('logistics_admin')->id, 'approved_by' => $this->user('logistics_admin')->id,
            'measurements' => [], 'revised_quote' => $shipment->quote_snapshot, 'difference_cents' => 0, 'evidence_path' => 'synthetic/adjustment.txt', 'reason' => 'Synthetic legacy discrepancy', 'status' => 'APPROVED']);
        // Simulate missing historical data, bypassing the model's normal deletion prohibition.
        DB::table('shipment_financials')->where('id', $shipment->id)->delete();
        $response = $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Unconfigured');
        $this->assertNull($response->viewData('workSummaries')[$order->id]['rows'][0]['amount_cents']);
        $this->assertSame(0, $response->viewData('pageSettledCents'));
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $data = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'first_name' => 'Synthetic', 'last_name' => 'Riderqa',
            'contact_no' => '09123456789', 'role' => $role, 'status' => 'approved', 'work_availability' => 'available',
            'vehicle_type' => $role === 'courier' ? 'motorcycle' : null, 'vehicle_capacity_grams' => 100000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear(), ...$data]);
    }

    private function order(array $data = []): Order
    {
        return Order::forceCreate(['order_number' => 'WORK-'.Str::random(10), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id,
            'sorting_center_id' => $this->user('sorting_center')->id, 'recipient_name' => 'Synthetic Buyer', 'recipient_contact' => '09123456789',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic address', 'subtotal' => 100,
            'shipping_fee' => 100, 'total_amount' => 200, 'payment_method' => 'COD', 'status' => 'COMPLETED', 'parcel_weight_grams' => 1000, ...$data]);
    }

    private function schedule(Order $order, array $components, bool $approve = true): ShipmentFinancial
    {
        $rates = app(DeliveryRateManagementService::class);
        $components = array_replace(array_fill_keys(DeliveryPricingService::COMPONENTS, 0), $components);
        $rate = $rates->create($this->user('logistics_admin'), ['name' => 'Isolated work-history tariff', 'route_class' => 'same_municipality', 'sandbox' => true,
            'volumetric_divisor' => 6000, 'effective_at' => now()->subMinute(), 'rules' => ['weight_brackets' => [['max_grams' => 5000, 'components' => $components]]]]);
        if ($approve) {
            $rates->approve($rate, $this->user('logistics_admin'), 'Approve isolated test schedule');
        }

        return ShipmentFinancial::create(['order_id' => $order->id, 'rate_version_id' => $rate->id, 'quote_snapshot' => ['model' => 'approved_components', 'rate_version_id' => $rate->id, 'components' => $components], 'buyer_shipping_cents' => 10000]);
    }

    private function transfer(Order $order, User $rider): ParcelTransfer
    {
        return ParcelTransfer::create(['order_id' => $order->id, 'source_hub_id' => $order->sorting_center_id, 'destination_hub_id' => $this->user('sorting_center')->id,
            'requested_by' => $order->sorting_center_id, 'carrier_rider_id' => $rider->id, 'status' => 'RECEIVED', 'reason' => 'Synthetic historical transfer', 'released_at' => now()->subHour(), 'received_at' => now()]);
    }

    private function settledOrder(User $rider): Order
    {
        $order = $this->order(['pickup_courier_id' => $rider->id, 'delivery_courier_id' => $rider->id, 'picked_up_at' => now()->subDays(9), 'delivered_at' => now()->subDays(8)]);
        $this->schedule($order, ['pickup' => 2000, 'last_mile' => 3000, 'linehaul' => 1000]);
        $this->transfer($order, $rider);
        $this->transfer($order, $rider);
        DeliveryAttempt::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => 'synthetic-proof/photo.jpg', 'attempted_at' => now()->subDays(8)]);
        $ledger = app(FinancialLedgerService::class);
        $ledger->transfer('synthetic:fund:'.$order->id, 'synthetic_funding', $ledger->account('synthetic:cash', 'cash_control', null, 'available', true), $ledger->escrow($order->id), 10000, null, ['order_id' => $order->id]);
        app(LogisticsSettlementService::class)->settle($order, $this->user('logistics_admin'));

        return $order;
    }
}
