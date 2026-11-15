<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryRetryRequest;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\ParcelTransfer;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use App\Services\DeliveryCodeService;
use App\Services\DeliveryRetryService;
use App\Services\FinancialLedgerService;
use App\Services\OrderWalletService;
use App\Services\ParcelTransferService;
use App\Services\RiderBadgeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DeliveryRetryGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['financial.sandbox_enabled' => true, 'rider-work.enforce_availability' => true]);
        Storage::fake('private');
        Notification::fake();
    }

    public function test_guidance_separates_acceptance_handoff_and_start_without_exposing_buyer_code(): void
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $order->forceFill(['hub_released_at' => null])->save();
        $assignment = $order->deliveryAssignments()->firstOrFail();
        $assignment->update(['accepted_at' => null]);
        $this->signIn($rider)->get(route('courier.orders.show', $order))->assertOk()->assertSee('Accept assignment')->assertSee('Acceptance deadline')->assertSee('Attempt 1');
        $this->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->post(route('courier.orders.accept-assignment', $order))->assertSessionHasNoErrors();
        $this->get(route('courier.orders.show', $order))->assertOk()->assertSee('Assignment accepted. Wait for logistics');
        $assignment->refresh()->update(['accepted_at' => null, 'assigned_at' => now()->subHour()]);
        $this->get(route('courier.orders.show', $order))->assertOk()->assertSee('acceptance deadline expired')->assertViewHas('deliveryProgress', fn ($progress) => $progress['can_accept'] === false);
        $this->post(route('courier.orders.accept-assignment', $order))->assertUnprocessable();
    }

    public function test_cod_completion_keeps_code_proof_and_exact_cash_checks_and_duplicate_attempts_do_not_repost_cash(): void
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $code = $this->start($rider, $order);
        $this->get(route('courier.orders.show', $order))->assertOk()->assertSee('Cash on Delivery: PHP 150.00')->assertDontSee('value="'.$code.'"', false);
        $data = $this->proof($code);
        unset($data['proof_file']);
        $this->patch(route('courier.orders.completeDelivery', $order), $data)->assertSessionHasErrors('proof_file');
        $this->patch(route('courier.orders.completeDelivery', $order), [...$this->proof($code), 'delivery_code' => 'invalid-code'])->assertSessionHasErrors('delivery_code');
        $this->patch(route('courier.orders.completeDelivery', $order), [...$this->proof($code), 'cod_collected_amount' => 149])->assertSessionHasErrors('cod_collected_amount');
        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);
        $this->assertDatabaseCount('delivery_attempts', 0);
        $this->patch(route('courier.orders.completeDelivery', $order), $this->proof($code))->assertSessionHasNoErrors();
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertSame(15000, $order->finance()->firstOrFail()->collected_cents);
        $this->assertSame($rider->id, $order->finance()->firstOrFail()->collector_id);
        $this->patch(route('courier.orders.completeDelivery', $order), $this->proof($code))->assertUnprocessable();
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertCount(1, Storage::disk('private')->allFiles('delivery-proofs'));
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('logistics_allocations', 0);
    }

    public function test_verified_wallet_job_shows_zero_cash_and_still_needs_code_proof_and_no_cod_collection(): void
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $this->payWallet($order);
        $before = LedgerTransaction::count();
        $code = $this->start($rider, $order);
        $this->get(route('courier.orders.show', $order))->assertOk()->assertSee('Verified wallet payment')->assertSee('Collect PHP 0.00')
            ->assertDontSee('name="cod_collected_amount"', false)->assertSee('name="proof_file"', false)->assertSee('name="delivery_code"', false);
        $this->get(route('courier.dashboard'))->assertOk()->assertSee('Verified wallet payment')->assertDontSee('name="cod_collected_amount"', false);
        $this->patch(route('courier.orders.completeDelivery', $order), [...$this->proof($code), 'cod_collected_amount' => 9999])->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->cod_collected_amount);
        $this->assertNull($order->finance()->firstOrFail()->collector_id);
        $this->assertNull($order->finance()->firstOrFail()->collected_cents);
        $this->assertSame($before, LedgerTransaction::count());
        $this->assertDatabaseCount('logistics_allocations', 0);
    }

    public function test_frozen_wallet_payment_is_a_hold_and_cannot_start_or_be_displayed_as_verified(): void
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $this->payWallet($order);
        app(FinancialLedgerService::class)->wallet($order->buyer)->update(['frozen' => true]);
        $this->signIn($rider)->get(route('courier.orders.show', $order))->assertOk()->assertSee('Payment verification hold')->assertDontSee('Verified wallet payment');
        $this->get(route('courier.dashboard'))->assertOk()->assertSee('Payment verification hold')->assertDontSee('Verified wallet payment');
        $this->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->assertSame('ASSIGNED_TO_RIDER', $order->fresh()->status);
    }

    #[TestWith(['WALLET'])]
    #[TestWith(['GCASH'])]
    #[TestWith(['BANK_TRANSFER'])]
    public function test_payment_method_text_alone_does_not_mark_a_job_prepaid(string $method): void
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $order->forceFill(['payment_method' => $method, 'financial_version' => 1])->save();
        $this->signIn($rider)->get(route('courier.orders.show', $order))->assertOk()->assertSee('Payment verification hold')->assertDontSee('Verified wallet payment');
        $this->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->assertDatabaseCount('financial_accounts', 0);
    }

    public function test_valid_looking_payment_metadata_with_no_buyer_wallet_is_not_verified_or_repaired_by_a_read(): void
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $transaction = LedgerTransaction::create(['reference' => Str::uuid(), 'idempotency_key' => 'synthetic:legacy', 'payload_hash' => str_repeat('0', 64), 'type' => 'order_payment', 'metadata' => ['order_id' => $order->id], 'created_at' => now()]);
        $order->forceFill(['payment_method' => 'WALLET', 'financial_version' => 1, 'wallet_transaction_id' => $transaction->id, 'wallet_paid_cents' => 15000])->save();
        $this->assertFalse(app(OrderWalletService::class)->paymentReady($order));
        $this->assertDatabaseCount('financial_accounts', 0);
    }

    public function test_retry_requires_scanned_intake_and_does_not_create_an_attempt_or_credit(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $data = $this->proposal($failure);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $data)->assertSessionHasErrors('retry_request');
        $this->assertDatabaseCount('delivery_retry_requests', 0);
        $this->recover($hub, $order);
        $before = $order->fresh()->getRawOriginal();
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $data)->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $this->assertSame('PENDING', $request->status);
        $this->assertSame($hub->id, $request->hub_id);
        $this->assertSame($before, $order->fresh()->getRawOriginal());
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('logistics_allocations', 0);
        $this->assertSame(1, DB::table('administrative_audits')->where('action', 'delivery_retry_requested')->count());
        Notification::assertSentTo($hub, WebOperationsNotification::class);
        $this->get(route('courier.history', ['status' => 'SORTED']))->assertOk()->assertSee('My latest retry proposal:')->assertSee('Pending');
    }

    public function test_duplicate_proposals_are_idempotent_and_different_replays_cannot_replace_history(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $data = $this->proposal($failure);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $data)->assertSessionHasNoErrors();
        $this->post(route('courier.retry-requests.store', $order), $data)->assertSessionHasNoErrors();
        $this->post(route('courier.retry-requests.store', $order), [...$data, 'reason' => 'A different request'])->assertSessionHasErrors('retry_request');
        $this->assertDatabaseCount('delivery_retry_requests', 1);
        $this->assertSame(1, DB::table('administrative_audits')->where('action', 'delivery_retry_requested')->count());
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'delivery_retry_requested')->count());
    }

    public function test_future_time_reason_and_latest_owned_failure_are_required(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), [])->assertSessionHasErrors(['failed_attempt_id', 'proposed_for', 'reason']);
        $this->post(route('courier.retry-requests.store', $order), [...$this->proposal($failure), 'reason' => '     '])->assertSessionHasErrors('reason');
        $this->post(route('courier.retry-requests.store', $order), [...$this->proposal($failure), 'proposed_for' => now()->subHour()->toDateTimeString()])->assertSessionHasErrors('proposed_for');
        $this->post(route('courier.retry-requests.store', $order), [...$this->proposal($failure), 'failed_attempt_id' => $failure->id + 999])->assertSessionHasErrors('retry_request');
        $this->assertDatabaseCount('delivery_retry_requests', 0);
    }

    public function test_other_riders_and_roles_cannot_propose_or_review_another_riders_work(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        Auth::logout();
        $this->get(route('courier.history'))->assertRedirectToRoute('login');
        $this->signIn($this->user('buyer'))->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertForbidden();
        $this->signIn($this->user('courier'))->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertNotFound();
        $this->assertDatabaseCount('delivery_retry_requests', 0);
    }

    public function test_ambiguous_missing_or_removed_hub_custody_does_not_allow_a_proposal(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        // A status/timestamp alone is not proof of scanned physical recovery.
        $order->forceFill(['status' => 'SORTED', 'delivery_courier_id' => null, 'delivery_recovered_at' => now()])->save();
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasErrors('retry_request');
        $this->assertDatabaseCount('delivery_retry_requests', 0);
    }

    public function test_hub_schedules_through_dispatch_and_rider_must_wait_for_retry_time(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $schedule = now()->addHour()->toDateTimeString();
        $this->signIn($hub)->get(route('logistics.dispatch'))->assertOk()->assertSee('Rider retry proposals')->assertViewHas('retryRequests', fn ($rows) => $rows->total() === 1);
        $payload = ['delivery_courier_id' => $rider->id, 'retry_request_id' => $request->id, 'scheduled_at' => $schedule];
        $this->post(route('logistics.orders.assignRider', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame('SCHEDULED', $request->fresh()->status);
        $this->assertSame($hub->id, $request->fresh()->reviewed_by);
        $this->assertSame('ASSIGNED_TO_RIDER', $order->fresh()->status);
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'attempt_no' => 2, 'outcome' => 'scheduled']);
        $this->assertSame($rider->id, json_decode(DB::table('administrative_audits')->where('action', 'delivery_retry_scheduled')->value('new_values'), true)['assigned_rider_id']);
        $this->post(route('logistics.orders.assignRider', $order), $payload)->assertNotFound();
        $this->assertSame(1, DeliveryAttempt::where('order_id', $order->id)->where('outcome', 'scheduled')->count());
        $this->post(route('logistics.orders.releaseToRider', $order), ['rider_badge' => 'EZR:'.app(RiderBadgeService::class)->ensure($rider), 'parcel_reference' => 'EZP:'.$order->parcel_code])->assertSessionHasNoErrors();
        $this->signIn($rider)->get(route('courier.orders.show', $order))->assertOk()->assertSee('Attempt 2')->assertSee('Wait for the scheduled retry time');
        $this->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->post(route('courier.orders.startDelivery', $order))->assertSessionHasNoErrors();
        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);
        $code = app(DeliveryCodeService::class)->reveal($order->fresh());
        $this->patch(route('courier.orders.completeDelivery', $order), $this->proof($code))->assertSessionHasNoErrors();
        $this->assertSame(2, $order->deliveryAttempts()->count());
        $this->assertDatabaseHas('delivery_attempts', ['order_id' => $order->id, 'attempt_no' => 2, 'outcome' => 'delivered']);
        $this->assertDatabaseCount('logistics_allocations', 0);
    }

    #[TestWith(['unavailable'])]
    #[TestWith(['capacity'])]
    public function test_review_cannot_bypass_new_work_or_weight_checks_and_failure_rolls_back_the_decision(string $block): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $rider->forceFill($block === 'unavailable' ? ['work_availability' => 'unavailable'] : ['vehicle_capacity_grams' => 500])->save();
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id, 'retry_request_id' => $request->id, 'scheduled_at' => now()->addHour()->toDateTimeString()])
            ->assertSessionHasErrors($block === 'unavailable' ? 'rider_availability' : 'vehicle_capacity_kg');
        $this->assertSame('PENDING', $request->fresh()->status);
        $this->assertSame('SORTED', $order->fresh()->status);
        $this->assertSame(0, DeliveryAttempt::where('outcome', 'scheduled')->count());
        $this->assertSame(0, DB::table('administrative_audits')->where('action', 'delivery_retry_scheduled')->count());
    }

    public function test_rejection_is_reasoned_idempotent_and_scoped_to_the_current_owning_hub(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $other = $this->user('sorting_center');
        $this->signIn($other)->get(route('logistics.dispatch'))->assertOk()->assertViewHas('retryRequests', fn ($rows) => $rows->total() === 0);
        $this->post(route('logistics.retry-requests.reject', $request), ['reason' => 'Foreign hub review'])->assertNotFound();
        $this->signIn($rider)->post(route('logistics.retry-requests.reject', $request), ['reason' => 'Self approval attempt'])->assertForbidden();
        $this->signIn($hub)->post(route('logistics.retry-requests.reject', $request), ['reason' => '     '])->assertSessionHasErrors('reason');
        $this->post(route('logistics.retry-requests.reject', $request), ['reason' => 'Recipient requested return'])->assertSessionHasNoErrors();
        $this->post(route('logistics.retry-requests.reject', $request), ['reason' => 'Recipient requested return'])->assertSessionHasNoErrors();
        $this->assertSame('REJECTED', $request->fresh()->status);
        $this->assertSame('SORTED', $order->fresh()->status);
        $this->assertSame(1, DB::table('administrative_audits')->where('action', 'delivery_retry_rejected')->count());
        $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Recipient requested return')->assertSee('Rejected');
    }

    public function test_independent_dispatch_supersedes_a_proposal_without_duplicate_pending_work(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertSessionHasNoErrors();
        $this->assertSame('SUPERSEDED', DeliveryRetryRequest::firstOrFail()->status);
        $this->get(route('logistics.dispatch'))->assertOk()->assertViewHas('retryRequests', fn ($rows) => $rows->total() === 0);
    }

    public function test_review_rechecks_current_custody_instead_of_trusting_the_old_request(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        DB::table('parcel_placements')->where('order_id', $order->id)->update(['active_order_id' => null, 'removed_at' => now()]);
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id, 'retry_request_id' => $request->id, 'scheduled_at' => now()->addHour()->toDateTimeString()])->assertSessionHasErrors('retry_request_id');
        $this->assertSame('PENDING', $request->fresh()->status);
        $this->assertSame('SORTED', $order->fresh()->status);
    }

    public function test_attempt_limit_creates_attention_not_false_return_status_and_blocks_more_proposals(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew(3);
        $this->signIn($hub)->get(route('logistics.dispatch'))->assertOk()->assertSee('Return required')->assertSee('recipient unavailable')
            ->assertViewHas('returnRequired', fn ($rows) => $rows->total() === 1);
        $this->assertSame('DELIVERY_FAILED', $order->fresh()->status);
        $this->assertNull($order->fresh()->return_handed_to_seller_at);
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasErrors('retry_request');
        $this->assertDatabaseCount('delivery_retry_requests', 0);
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id])->assertSessionHasNoErrors();
        $this->post(route('logistics.orders.releaseToRider', $order), ['rider_badge' => 'EZR:'.app(RiderBadgeService::class)->ensure($rider), 'parcel_reference' => 'EZP:'.$order->parcel_code])->assertSessionHasNoErrors();
        $this->signIn($rider)->post(route('courier.orders.startDelivery', $order))->assertUnprocessable();
        $this->signIn($hub)->post(route('logistics.orders.return', $order))->assertSessionHasNoErrors();
        $this->assertSame('RETURN_IN_TRANSIT', $order->fresh()->status);
        $this->get(route('logistics.dispatch'))->assertOk()->assertViewHas('returnRequired', fn ($rows) => $rows->total() === 0);
    }

    public function test_return_required_queue_uses_configured_limit_and_own_hub_scope(): void
    {
        config(['logistics.maximum_delivery_attempts' => 2]);
        [$hub, $area, $rider, $order] = $this->failedCrew(2);
        [$other, $otherArea, $otherRider, $private] = $this->failedCrew(3);
        $this->signIn($hub)->get(route('logistics.dispatch'))->assertOk()->assertViewHas('returnRequired', fn ($rows) => $rows->total() === 1)
            ->assertDontSee($private->order_number);
    }

    public function test_retry_contexts_are_batched_and_history_does_not_offer_unrecovered_retries(): void
    {
        [$hub, $area, $rider, $order] = $this->failedCrew();
        $this->signIn($rider)->get(route('courier.history'))->assertOk()->assertSee('Wait for logistics to scan the parcel back')->assertDontSee('Propose retry to logistics');
        foreach (range(1, 19) as $number) {
            $copy = $order->replicate(['order_number', 'parcel_code']);
            $copy->forceFill(['order_number' => 'BATCH-'.Str::random(10)])->save();
            DeliveryAttempt::create(['order_id' => $copy->id, 'rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'failed', 'reason' => 'recipient_unavailable', 'attempted_at' => now()->subMinute()]);
        }
        $orders = Order::where('delivery_courier_id', $rider->id)->get();
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $contexts = app(DeliveryRetryService::class)->contexts($orders, $rider);
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertCount(20, $contexts);
        $this->assertLessThanOrEqual(7, $queries);
    }

    public function test_retry_history_is_immutable_and_the_database_rejects_a_duplicate_failed_attempt(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        try {
            $request->update(['reason' => 'Rewritten history']);
            $this->fail('Origins must be immutable.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        $this->expectException(QueryException::class);
        DeliveryRetryRequest::create($request->fresh()->only(['order_id', 'failed_attempt_id', 'requested_by', 'hub_id', 'proposed_for', 'reason']));
    }

    public function test_a_different_hubs_request_id_cannot_be_used_on_an_own_hub_order(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        [$other, $otherArea, $otherRider, $otherOrder, $otherFailure] = $this->failedCrew();
        $this->recover($other, $otherOrder);
        $this->signIn($otherRider)->post(route('courier.retry-requests.store', $otherOrder), $this->proposal($otherFailure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id, 'retry_request_id' => $request->id, 'scheduled_at' => now()->addHour()->toDateTimeString()])->assertNotFound();
        $this->assertSame('PENDING', $request->fresh()->status);
        $this->assertSame('SORTED', $order->fresh()->status);
    }

    public function test_active_hub_transfer_prevents_local_retry_approval(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $destination = $this->user('sorting_center');
        $area->update(['sorting_center_id' => $destination->id]);
        app(ParcelTransferService::class)->plan($order->fresh(), $this->user('logistics_admin'), $destination, 'Synthetic rerouting review');
        $context = app(DeliveryRetryService::class)->contexts(new Collection([$order->fresh()]), $rider, includeRecorded: false)[$order->id];
        $this->assertFalse($context['can_request']);
        $this->assertStringContainsString('active hub transfer', $context['reason']);
        $this->signIn($hub)->post(route('logistics.orders.assignRider', $order), ['delivery_courier_id' => $rider->id, 'retry_request_id' => $request->id, 'scheduled_at' => now()->addHour()->toDateTimeString()])->assertUnprocessable();
        $this->assertSame('PENDING', $request->fresh()->status);
        $this->assertSame(1, ParcelTransfer::where('order_id', $order->id)->count());
    }

    public function test_an_old_scan_cannot_be_reused_as_proof_of_recovery_from_a_later_failure(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        DB::table('scan_events')->where('order_id', $order->id)->where('station', 'failed_return_intake')->update(['created_at' => now()->subDay()]);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasErrors('retry_request');
        $this->assertDatabaseCount('delivery_retry_requests', 0);
    }

    public function test_pending_proposal_table_has_its_own_twenty_five_row_pagination(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        foreach (range(1, 25) as $number) {
            $copy = $order->fresh()->replicate(['order_number', 'parcel_code']);
            $copy->forceFill(['order_number' => 'PAGINATED-'.Str::random(10)])->save();
            $attempt = DeliveryAttempt::create(['order_id' => $copy->id, 'rider_id' => $rider->id, 'attempt_no' => 1, 'outcome' => 'failed', 'reason' => 'recipient_unavailable', 'attempted_at' => now()->subMinute()]);
            DeliveryRetryRequest::create(['order_id' => $copy->id, 'failed_attempt_id' => $attempt->id, 'requested_by' => $rider->id, 'hub_id' => $hub->id, 'proposed_for' => now()->addDay(), 'reason' => 'Synthetic pagination fixture']);
        }
        $first = $this->signIn($hub)->get(route('logistics.dispatch'))->assertOk();
        $this->assertSame(26, $first->viewData('retryRequests')->total());
        $this->assertCount(25, $first->viewData('retryRequests')->items());
        $second = $this->get(route('logistics.dispatch', ['retry_requests_page' => 2]))->assertOk();
        $this->assertCount(1, $second->viewData('retryRequests')->items());
    }

    public function test_physical_hub_transfer_release_supersedes_pending_local_retry_with_an_audit(): void
    {
        [$hub, $area, $rider, $order, $failure] = $this->failedCrew();
        $this->recover($hub, $order);
        $this->signIn($rider)->post(route('courier.retry-requests.store', $order), $this->proposal($failure))->assertSessionHasNoErrors();
        $request = DeliveryRetryRequest::firstOrFail();
        $destination = $this->user('sorting_center');
        $area->update(['sorting_center_id' => $destination->id]);
        $transfer = app(ParcelTransferService::class)->plan($order->fresh(), $this->user('logistics_admin'), $destination, 'Synthetic transfer release');
        $location = DB::table('parcel_placements')->join('storage_locations', 'storage_locations.id', '=', 'parcel_placements.location_id')->where('parcel_placements.order_id', $order->id)->value('storage_locations.code');
        $this->signIn($hub)->post(route('logistics.storage.pick'), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'location_reference' => 'EZL:'.$location, 'method' => 'manual'])->assertSessionHasNoErrors();
        $carrier = $this->user('courier', ['hub_transfer_only' => true, 'logistics_owner_id' => $hub->id]);
        $this->post(route('parcel-transfers.release', $transfer), ['parcel_reference' => 'EZP:'.$order->parcel_code, 'transport_reference' => 'QA-MANIFEST', 'carrier_rider_id' => $carrier->id])->assertSessionHasNoErrors();
        $this->assertSame('IN_TRANSIT', $transfer->fresh()->status);
        $this->assertSame('SUPERSEDED', $request->fresh()->status);
        $this->assertSame($transfer->id, json_decode(DB::table('administrative_audits')->where('action', 'delivery_retry_superseded')->value('new_values'), true)['transfer_id']);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role, array $data = []): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'first_name' => 'Retryqa', 'last_name' => 'Synthetic', 'role' => $role, 'status' => 'approved',
            'contact_no' => '09123456789', 'work_availability' => 'available', 'vehicle_capacity_grams' => 100000, 'vehicle_capacity_verified_at' => now(), 'license_expires_at' => today()->addYear(), ...$data]);
    }

    private function crew(): array
    {
        $hub = $this->user('sorting_center');
        $area = Area::create(['name' => 'Retry test area', 'code' => 'RT-'.Str::random(8), 'sorting_center_id' => $hub->id, 'is_active' => true]);
        $rider = $this->user('courier', ['logistics_owner_id' => $hub->id]);
        $rider->serviceAreas()->attach($area->id, ['is_active' => true, 'is_primary' => true]);
        $order = Order::forceCreate(['order_number' => 'RETRY-'.Str::random(10), 'buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'recipient_name' => 'Synthetic Buyer', 'recipient_contact' => '09123456789',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic fixture', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD',
            'status' => 'ASSIGNED_TO_RIDER', 'sorting_center_id' => $hub->id, 'destination_area_id' => $area->id, 'delivery_courier_id' => $rider->id, 'assigned_at' => now(), 'hub_released_at' => now(), 'parcel_weight_grams' => 1000]);
        DeliveryAssignment::create(['order_id' => $order->id, 'active_order_id' => $order->id, 'rider_id' => $rider->id, 'assigned_by' => $hub->id, 'status' => 'active', 'assigned_at' => now(), 'accepted_at' => now()]);

        return [$hub, $area, $rider, $order];
    }

    private function failedCrew(int $count = 1): array
    {
        [$hub, $area, $rider, $order] = $this->crew();
        $order->forceFill(['status' => 'DELIVERY_FAILED', 'failed_at' => now()->subMinute(), 'delivery_failure_reason' => 'recipient_unavailable'])->save();
        foreach (range(1, $count) as $number) {
            $failure = DeliveryAttempt::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'attempt_no' => $number, 'outcome' => 'failed', 'reason' => 'recipient_unavailable', 'notes' => 'Synthetic failure '.$number, 'attempted_at' => now()->subMinutes($count - $number + 1)]);
        }

        return [$hub, $area, $rider, $order, $failure];
    }

    private function recover(User $hub, Order $order): void
    {
        $code = 'RETURN-'.Str::random(6);
        DB::table('storage_locations')->insert(['hub_id' => $hub->id, 'code' => $code, 'label' => 'Synthetic returns location', 'type' => 'RETURNS', 'area_id' => $order->destination_area_id, 'capacity' => 50, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->signIn($hub)->post(route('logistics.scan'), ['reference' => 'EZP:'.$order->parcel_code, 'location_reference' => 'EZL:'.$code, 'method' => 'manual'])->assertSessionHasNoErrors();
        $this->assertSame('SORTED', $order->fresh()->status);
    }

    private function proposal(DeliveryAttempt $failure): array
    {
        return ['failed_attempt_id' => $failure->id, 'proposed_for' => now()->addDay()->toDateTimeString(), 'reason' => 'Recipient available tomorrow'];
    }

    private function start(User $rider, Order $order): string
    {
        $this->signIn($rider)->post(route('courier.orders.startDelivery', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertSessionHasNoErrors();

        return app(DeliveryCodeService::class)->reveal($order->fresh());
    }

    private function proof(string $code): array
    {
        return ['recipient_confirmation' => 'Synthetic recipient', 'delivery_code' => $code, 'cod_collected_amount' => 150, 'proof_file' => UploadedFile::fake()->image('proof.jpg')];
    }

    private function payWallet(Order $order): void
    {
        $order->refresh();
        $order->forceFill(['payment_method' => 'WALLET', 'financial_version' => 1])->save();
        $ledger = app(FinancialLedgerService::class);
        $buyer = $order->buyer;
        $ledger->transfer('synthetic:fund:'.$order->id, 'synthetic_funding', $ledger->account('synthetic:cash', 'cash_control', null, 'available', true), $ledger->wallet($buyer), 15000, null);
        app(OrderWalletService::class)->pay($order, $buyer);
    }
}
