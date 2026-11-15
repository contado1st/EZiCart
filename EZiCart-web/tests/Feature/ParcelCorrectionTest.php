<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;
use App\Models\ParcelCorrection;
use App\Models\ParcelTrackingEvent;
use App\Models\User;
use App\Notifications\WebOperationsNotification;
use App\Services\DeliveryCodeService;
use App\Services\FinancialLedgerService;
use App\Services\OrderWalletService;
use App\Services\ParcelCorrectionService;
use App\Services\RiderBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ParcelCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $hub;

    private User $otherHub;

    private User $admin;

    private User $buyer;

    private User $seller;

    private User $rider;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('private');
        $this->hub = $this->user('sorting_center');
        $this->otherHub = $this->user('sorting_center');
        $this->admin = $this->user('logistics_admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $this->rider = $this->user('courier');
        $this->area = Area::create(['name' => 'Correction hub', 'code' => 'correction-hub', 'sorting_center_id' => $this->hub->id, 'is_active' => true]);
        AreaMunicipality::create(['area_id' => $this->area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
    }

    public function test_request_records_private_evidence_snapshot_and_audit_without_changing_parcel_or_money(): void
    {
        $order = $this->order();
        $state = $order->getRawOriginal();
        $this->signIn($this->hub)->get(route('logistics.tracking'))->assertOk()->assertSee(route('logistics-corrections.create', $order), false);
        $this->get(route('logistics-corrections.create', $order))->assertOk()->assertSee('Supporting evidence');
        $correction = $this->requestReview($order);
        $this->assertSame($state, $order->fresh()->getRawOriginal());
        $this->assertEquals($order->id, $correction->open_order_id);
        $this->assertSame('AT_SORTING_CENTER', $correction->snapshot_before['status']);
        $this->assertDatabaseCount('parcel_tracking_events', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseHas('administrative_audits', ['entity_type' => ParcelCorrection::class, 'entity_id' => $correction->id, 'actor_id' => $this->hub->id, 'action' => 'parcel_correction_requested']);
        Storage::disk('private')->assertExists($correction->evidence_path);
        Notification::assertSentTo($this->admin, WebOperationsNotification::class);
        $response = $this->get(route('logistics-corrections.show', $correction))->assertOk()->assertDontSee($correction->evidence_path)->assertDontSee('name="target_status"', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('logistics-corrections.evidence', $correction))->assertDownload();
        $this->assertArrayNotHasKey('evidence_path', $correction->toArray());
    }

    #[TestWith(['missing_file'])]
    #[TestWith(['wrong_type'])]
    #[TestWith(['wrong_extension'])]
    #[TestWith(['too_large'])]
    #[TestWith(['blank_reason'])]
    #[TestWith(['target_status'])]
    public function test_invalid_requests_have_no_persistent_case_or_file(string $case): void
    {
        $order = $this->order();
        $data = $this->requestData();
        if ($case === 'missing_file') {
            unset($data['evidence']);
        }
        if ($case === 'wrong_type') {
            $data['evidence'] = UploadedFile::fake()->create('code.php', 10, 'text/x-php');
        }
        if ($case === 'wrong_extension') {
            $data['evidence'] = UploadedFile::fake()->create('photo.php', 10, 'image/jpeg');
        }
        if ($case === 'too_large') {
            $data['evidence'] = UploadedFile::fake()->create('proof.pdf', 5121, 'application/pdf');
        }
        if ($case === 'blank_reason') {
            $data['reason'] = '      ';
        }
        if ($case === 'target_status') {
            $data['target_status'] = 'DELIVERED';
        }
        $this->signIn($this->hub)->postJson(route('logistics-corrections.store', $order), $data)->assertUnprocessable();
        $this->assertDatabaseCount('parcel_corrections', 0);
        $this->assertCount(0, Storage::disk('private')->allFiles('parcel-corrections'));
        $this->assertSame('AT_SORTING_CENTER', $order->fresh()->status);
    }

    public function test_duplicate_open_request_is_rejected_and_its_unused_upload_is_cleaned(): void
    {
        $order = $this->order();
        $this->requestReview($order);
        $this->postJson(route('logistics-corrections.store', $order), $this->requestData())->assertUnprocessable()->assertJsonValidationErrors('correction');
        $this->assertDatabaseCount('parcel_corrections', 1);
        $this->assertCount(1, Storage::disk('private')->allFiles('parcel-corrections'));
        $this->assertDatabaseCount('administrative_audits', 1);
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    #[TestWith(['courier'])]
    #[TestWith(['ecommerce_admin'])]
    public function test_non_logistics_accounts_cannot_read_case_or_evidence_or_request_review(string $role): void
    {
        $order = $this->order();
        $case = $this->requestReview($order);
        $actor = $this->user($role);
        $this->signIn($actor)->get(route('logistics-corrections.index'))->assertForbidden();
        $this->get(route('logistics-corrections.show', $case))->assertForbidden();
        $this->get(route('logistics-corrections.evidence', $case))->assertForbidden();
        $this->post(route('logistics-corrections.store', $order), $this->requestData())->assertForbidden();
        $this->assertDatabaseCount('parcel_corrections', 1);
    }

    public function test_other_hub_cannot_request_or_view_the_case_and_transfer_does_not_transfer_review_ownership(): void
    {
        $order = $this->order();
        $case = $this->requestReview($order);
        $this->signIn($this->otherHub)->get(route('logistics-corrections.create', $order))->assertNotFound();
        $this->post(route('logistics-corrections.store', $order), $this->requestData())->assertNotFound();
        $this->get(route('logistics-corrections.show', $case))->assertNotFound();
        $this->get(route('logistics-corrections.evidence', $case))->assertNotFound();
        $order->forceFill(['sorting_center_id' => $this->otherHub->id])->save();
        $this->get(route('logistics-corrections.show', $case))->assertNotFound();
        $this->signIn($this->hub)->get(route('logistics-corrections.show', $case))->assertOk();
    }

    public function test_actual_sorting_can_be_linked_to_an_independent_reasoned_resolution_and_replay_is_safe(): void
    {
        $this->freezeTime();
        $order = $this->order();
        $case = $this->requestReview($order);
        $this->post(route('logistics.orders.sort', $order))->assertRedirect();
        $event = $order->trackingEvents()->latest('id')->firstOrFail();
        $state = $order->fresh()->getRawOriginal();
        $data = ['outcome' => 'RESOLVED', 'reason' => 'Verified guarded sorting action and evidence.', 'tracking_event_id' => $event->id];
        $this->signIn($this->admin)->post(route('logistics-corrections.review', $case), $data)->assertRedirect();
        $this->assertSame('RESOLVED', $case->fresh()->status);
        $this->assertNull($case->fresh()->open_order_id);
        $this->assertSame('AT_SORTING_CENTER', $case->fresh()->snapshot_before['status']);
        $this->assertSame('SORTED', $case->fresh()->snapshot_after['status']);
        $this->assertSame($state, $order->fresh()->getRawOriginal());
        $before = DB::table('administrative_audits')->count();
        $this->post(route('logistics-corrections.review', $case), $data)->assertRedirect();
        $this->assertSame($before, DB::table('administrative_audits')->count());
        $this->postJson(route('logistics-corrections.review', $case), [...$data, 'reason' => 'Different decision cannot replace history.'])->assertConflict();
        Notification::assertSentTo($this->hub, WebOperationsNotification::class);
        $this->get(route('logistics-corrections.show', $case))->assertOk()->assertSee('At request')->assertSee('At decision')->assertSee('Verified guarded sorting action')->assertDontSee('Resolve using recorded workflow');
        $this->get(route('activity-logs.index', ['entity_type' => ParcelCorrection::class, 'record' => $case->id]))->assertOk()->assertSee('Parcel Correction Resolved')->assertSee('AT_SORTING_CENTER')->assertSee('SORTED');
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('parcel_tracking_events', 1);
    }

    #[TestWith(['unchanged'])]
    #[TestWith(['foreign_event'])]
    #[TestWith(['old_event'])]
    #[TestWith(['stale_stage'])]
    #[TestWith(['deleted_evidence'])]
    #[TestWith(['changed_evidence'])]
    public function test_unverifiable_resolution_leaves_case_open(string $scenario): void
    {
        $order = $this->order();
        $old = ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $this->hub->id, 'event_type' => 'at_hub', 'status' => $order->status]);
        $case = $this->requestReview($order);
        if ($scenario === 'unchanged') {
            DB::table('parcel_corrections')->where('id', $case->id)->update(['snapshot_before' => json_encode(array_reverse($case->snapshot_before, true))]);
            $event = ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $this->hub->id, 'event_type' => 'note_only', 'status' => $order->status]);
        } else {
            $this->post(route('logistics.orders.sort', $order))->assertRedirect();
            $event = $order->trackingEvents()->latest('id')->firstOrFail();
        }
        if ($scenario === 'old_event') {
            $event = $old;
        }
        if ($scenario === 'foreign_event') {
            $event = ParcelTrackingEvent::create(['order_id' => $this->order()->id, 'actor_id' => $this->hub->id, 'event_type' => 'sorted', 'status' => 'SORTED']);
        }
        if ($scenario === 'stale_stage') {
            $order->forceFill(['status' => 'OUT_FOR_DELIVERY'])->save();
        }
        if ($scenario === 'deleted_evidence') {
            Storage::disk('private')->delete($case->evidence_path);
        }
        if ($scenario === 'changed_evidence') {
            Storage::disk('private')->put($case->evidence_path, 'Tampered bytes');
        }
        $before = DB::table('administrative_audits')->count();
        $this->signIn($this->admin)->postJson(route('logistics-corrections.review', $case), ['outcome' => 'RESOLVED', 'reason' => 'Insufficient evidence must block closure.', 'tracking_event_id' => $event->id])->assertUnprocessable();
        $this->assertSame('OPEN', $case->fresh()->status);
        $this->assertSame($before, DB::table('administrative_audits')->count());
    }

    public function test_rejection_records_a_decision_without_transition_and_allows_a_later_new_request(): void
    {
        $order = $this->order();
        $case = $this->requestReview($order);
        $this->signIn($this->admin)->post(route('logistics-corrections.review', $case), ['outcome' => 'REJECTED', 'reason' => 'Existing scan is correct; no correction required.'])->assertRedirect();
        $this->assertSame('REJECTED', $case->fresh()->status);
        $this->assertSame('AT_SORTING_CENTER', $order->fresh()->status);
        $this->assertDatabaseCount('parcel_tracking_events', 0);
        $next = $this->requestReview($order);
        $this->assertNotSame($case->id, $next->id);
        $this->assertDatabaseCount('parcel_corrections', 2);
        $this->assertNull($case->fresh()->open_order_id);
    }

    public function test_unauthorized_self_and_stale_account_decisions_are_denied(): void
    {
        $order = $this->order();
        $case = $this->requestReview($order);
        $data = ['outcome' => 'REJECTED', 'reason' => 'Unauthorized decision rejected.'];
        $this->postJson(route('logistics-corrections.review', $case), $data)->assertForbidden();
        $this->hub->forceFill(['role' => 'logistics_admin'])->save();
        $this->signIn($this->hub)->postJson(route('logistics-corrections.review', $case), $data)->assertForbidden();
        User::whereKey($this->admin->id)->update(['status' => 'suspended']);
        try {
            app(ParcelCorrectionService::class)->review($case, $this->admin, 'REJECTED', 'Stale actor must not decide.', null);
            $this->fail('Stale approved actor accepted.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('OPEN', $case->fresh()->status);
    }

    public function test_request_and_decision_history_are_immutable_and_user_text_is_escaped(): void
    {
        $order = $this->order();
        $case = $this->requestReview($order, '<script>alert("request")</script>');
        try {
            $case->update(['reason' => 'Rewrite original request']);
            $this->fail('Origin edited.');
        } catch (\LogicException) {
            $this->assertSame('<script>alert("request")</script>', $case->fresh()->reason);
        }
        $this->signIn($this->admin)->post(route('logistics-corrections.review', $case), ['outcome' => 'REJECTED', 'reason' => '<script>alert("decision")</script>'])->assertRedirect();
        $response = $this->get(route('logistics-corrections.show', $case))->assertOk();
        $response->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false)->assertDontSee('Edit request')->assertDontSee('Delete request');
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $case->fresh()->update(['decision_reason' => 'Changed']) : $case->fresh()->delete();
                $this->fail('Decision history changed.');
            } catch (\LogicException) {
                $this->assertSame('REJECTED', $case->fresh()->status);
            }
        }
    }

    public function test_delivered_label_without_proof_and_cash_cannot_be_endorsed_as_resolved(): void
    {
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $this->rider->id]);
        $case = $this->requestReview($order);
        $this->signIn($this->admin)->postJson(route('logistics-corrections.review', $case), ['outcome' => 'RESOLVED', 'reason' => 'Target status is forbidden.', 'target_status' => 'DELIVERED', 'tracking_event_id' => 1])->assertUnprocessable()->assertJsonValidationErrors('target_status');
        // Adversarial historical label: no public review action can create this state.
        $order->forceFill(['status' => 'DELIVERED', 'delivered_at' => now()])->save();
        $event = ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $this->rider->id, 'event_type' => 'delivered', 'status' => 'DELIVERED']);
        $this->postJson(route('logistics-corrections.review', $case), ['outcome' => 'RESOLVED', 'reason' => 'No proof or collection exists.', 'tracking_event_id' => $event->id])->assertUnprocessable();
        $this->assertSame('OPEN', $case->fresh()->status);
        $this->assertDatabaseCount('order_finances', 0);
    }

    public function test_real_cod_delivery_remains_a_separate_guarded_action_and_review_cannot_repost_cash(): void
    {
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $this->rider->id]);
        $code = app(DeliveryCodeService::class)->issue();
        $plain = $code['code'];
        unset($code['code']);
        $order->forceFill($code)->save();
        $case = $this->requestReview($order);
        $this->signIn($this->rider)->patch(route('courier.orders.completeDelivery', $order), ['delivery_code' => $plain, 'recipient_confirmation' => 'Test receiver', 'cod_collected_amount' => 150, 'proof_file' => UploadedFile::fake()->image('delivered.jpg')])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $finance = $order->finance()->firstOrFail()->getRawOriginal();
        $event = $order->trackingEvents()->latest('id')->firstOrFail();
        $this->signIn($this->admin)->post(route('logistics-corrections.review', $case), ['outcome' => 'RESOLVED', 'reason' => 'Verified actual buyer-code delivery and collection.', 'tracking_event_id' => $event->id])->assertRedirect();
        $this->assertSame('RESOLVED', $case->fresh()->status);
        $this->assertSame($finance, $order->finance()->firstOrFail()->getRawOriginal());
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_index_is_scoped_paginated_and_read_only_with_retained_history_filters(): void
    {
        $order = $this->order();
        for ($i = 0; $i < 26; $i++) {
            $case = $this->requestReview($order);
            $this->signIn($this->admin)->post(route('logistics-corrections.review', $case), ['outcome' => 'REJECTED', 'reason' => 'Retained synthetic historical review.'])->assertRedirect();
        }
        $foreignOrder = $this->order(['sorting_center_id' => $this->otherHub->id, 'order_number' => 'FOREIGN-PRIVATE']);
        Storage::disk('private')->put('foreign.jpg', 'Synthetic evidence');
        app(ParcelCorrectionService::class)->request($foreignOrder, $this->otherHub, 'Foreign hub private review.', 'foreign.jpg');
        $before = DB::table('administrative_audits')->count();
        $response = $this->signIn($this->hub)->get(route('logistics-corrections.index', ['status' => 'all']))->assertOk()->assertDontSee('FOREIGN-PRIVATE');
        $this->assertSame(26, $response->viewData('corrections')->total());
        $this->assertCount(25, $response->viewData('corrections')->items());
        $response = $this->get(route('logistics-corrections.index', ['status' => 'REJECTED', 'page' => 2]))->assertOk();
        $this->assertCount(1, $response->viewData('corrections')->items());
        $this->assertSame($before, DB::table('administrative_audits')->count());
        $this->assertSame(0, $this->get(route('logistics-corrections.index', ['status' => 'all', 'search' => '%']))->viewData('corrections')->total());
    }

    #[TestWith(['wallet_verified'])]
    #[TestWith(['wallet_frozen'])]
    #[TestWith(['cash_mismatch'])]
    #[TestWith(['missing_delivery_file'])]
    public function test_delivery_resolution_verifies_recorded_payment_and_proof_without_posting_money(string $scenario): void
    {
        $order = $this->order(['status' => 'OUT_FOR_DELIVERY', 'delivery_courier_id' => $this->rider->id]);
        if (str_starts_with($scenario, 'wallet_')) {
            config(['financial.sandbox_enabled' => true]);
            $order->forceFill(['payment_method' => 'WALLET', 'financial_version' => 1])->save();
            $ledger = app(FinancialLedgerService::class);
            $ledger->transfer('synthetic:correction-fund', 'synthetic_funding', $ledger->account('synthetic:cash', 'cash_control', null, 'available', true), $ledger->wallet($this->buyer), 15000, null);
            app(OrderWalletService::class)->pay($order, $this->buyer);
        }
        $code = app(DeliveryCodeService::class)->issue();
        $plain = $code['code'];
        unset($code['code']);
        $order->forceFill($code)->save();
        $case = $this->requestReview($order);
        $this->signIn($this->rider)->patch(route('courier.orders.completeDelivery', $order), ['delivery_code' => $plain, 'recipient_confirmation' => 'Test receiver', 'cod_collected_amount' => 150, 'proof_file' => UploadedFile::fake()->image('delivered.jpg')])->assertSessionHasNoErrors()->assertRedirect();
        $event = $order->trackingEvents()->latest('id')->firstOrFail();
        if ($scenario === 'wallet_frozen') {
            app(FinancialLedgerService::class)->wallet($this->buyer)->update(['frozen' => true]);
        }
        if ($scenario === 'cash_mismatch') {
            $order->finance()->update(['collected_cents' => 14999]);
        }
        if ($scenario === 'missing_delivery_file') {
            Storage::disk('private')->delete($order->deliveryAttempts()->firstOrFail()->proof_path);
        }
        $before = DB::table('ledger_transactions')->count();
        $response = $this->signIn($this->admin)->postJson(route('logistics-corrections.review', $case), ['outcome' => 'RESOLVED', 'reason' => 'Review actual delivery and payment evidence.', 'tracking_event_id' => $event->id]);
        if ($scenario === 'wallet_verified') {
            $response->assertRedirect();
            $this->assertSame('RESOLVED', $case->fresh()->status);
            $this->assertNull($order->fresh()->cod_collected_amount);
        } else {
            $response->assertUnprocessable();
            $this->assertSame('OPEN', $case->fresh()->status);
        }
        $this->assertSame($before, DB::table('ledger_transactions')->count());
        $this->assertSame('DELIVERED', $order->fresh()->status);
    }

    public function test_missing_historical_return_receipt_cannot_be_endorsed_but_real_scanned_receipt_can(): void
    {
        $order = $this->order(['status' => 'RETURN_IN_TRANSIT', 'delivery_courier_id' => $this->rider->id]);
        $case = $this->requestReview($order);
        $order->forceFill(['status' => 'RETURNED_TO_SELLER'])->save();
        $event = ParcelTrackingEvent::create(['order_id' => $order->id, 'actor_id' => $this->seller->id, 'event_type' => 'returned_to_seller', 'status' => 'RETURNED_TO_SELLER']);
        $data = ['outcome' => 'RESOLVED', 'reason' => 'Review recorded physical return receipt.', 'tracking_event_id' => $event->id];
        $this->signIn($this->admin)->postJson(route('logistics-corrections.review', $case), $data)->assertUnprocessable();
        $order->forceFill(['status' => 'RETURN_IN_TRANSIT'])->save();
        $this->signIn($this->rider)->post(route('courier.orders.confirmReturnDelivery', $order), ['parcel_reference' => 'EZP:'.$order->parcel_code])->assertRedirect();
        $badge = app(RiderBadgeService::class)->ensure($this->rider);
        $this->signIn($this->seller)->post(route('seller.orders.confirmReturn', $order), ['rider_badge' => 'EZR:'.$badge])->assertRedirect();
        $data['tracking_event_id'] = $order->trackingEvents()->latest('id')->value('id');
        $this->signIn($this->admin)->post(route('logistics-corrections.review', $case), $data)->assertRedirect();
        $this->assertSame('RESOLVED', $case->fresh()->status);
        $this->assertNull($order->fresh()->inventory_restored_at);
        $this->assertDatabaseCount('refund_executions', 0);
    }

    public function test_rejection_cannot_attach_an_event_and_service_rechecks_current_requesting_hub(): void
    {
        $order = $this->order();
        $case = $this->requestReview($order);
        $this->signIn($this->admin)->postJson(route('logistics-corrections.review', $case), ['outcome' => 'REJECTED', 'reason' => 'Cannot attach a resolution event to rejection.', 'tracking_event_id' => 1])->assertUnprocessable();
        User::whereKey($this->hub->id)->update(['status' => 'suspended']);
        Storage::disk('private')->put('another.jpg', 'Test evidence');
        try {
            app(ParcelCorrectionService::class)->request($this->order(), $this->hub, 'Stale approved hub must be blocked.', 'another.jpg');
            $this->fail('Suspended hub created a request.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('parcel_corrections', 1);
        $this->assertSame('OPEN', $case->fresh()->status);
    }

    public function test_guests_and_unsupported_methods_cannot_change_reviews(): void
    {
        $order = $this->order();
        $case = $this->requestReview($order);
        $this->signIn($this->hub)->patch(route('logistics-corrections.show', $case), ['reason' => 'Cannot edit via another verb'])->assertMethodNotAllowed();
        Auth::logout();
        $this->get(route('logistics-corrections.show', $case))->assertRedirect();
        $this->postJson(route('logistics-corrections.store', $order), $this->requestData())->assertUnauthorized();
        $this->assertSame('OPEN', $case->fresh()->status);
    }

    private function requestReview(Order $order, string $reason = 'Inspect scan and assignment discrepancy.'): ParcelCorrection
    {
        $this->signIn($this->hub)->post(route('logistics-corrections.store', $order), [...$this->requestData(), 'reason' => $reason])->assertRedirect();

        return ParcelCorrection::where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    private function requestData(): array
    {
        return ['reason' => 'Inspect scan and assignment discrepancy.', 'evidence' => UploadedFile::fake()->image('support.jpg')];
    }

    private function order(array $extra = []): Order
    {
        return Order::forceCreate($extra + ['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'sorting_center_id' => $this->hub->id, 'destination_area_id' => $this->area->id,
            'order_number' => 'COR-'.Str::random(10), 'status' => 'AT_SORTING_CENTER', 'received_at' => now(), 'recipient_name' => 'Test receiver', 'recipient_contact' => '09123456789',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Synthetic fixture', 'subtotal' => 100, 'shipping_fee' => 50, 'commission_fee' => 10, 'total_amount' => 150, 'payment_method' => 'COD'])->refresh();
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Correction', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
}
