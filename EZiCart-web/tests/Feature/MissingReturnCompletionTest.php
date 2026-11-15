<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\ApiToken;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Notifications\WebOperationsNotification;
use App\Services\OrderCompletionEligibilityService;
use App\Services\OrderFinancialService;
use App\Services\OrderTransitionService;
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

class MissingReturnCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_new_request_has_a_72_hour_seller_stage_and_private_urgent_admin_path_is_preserved(): void
    {
        $this->travelTo(now()->startOfSecond());
        $order = $this->order();
        $case = $this->requestReturn($order);
        $this->assertSame('awaiting_seller', $case->sellerResponse->stage);
        $this->assertTrue($case->sellerResponse->response_due_at->equalTo(now()->addHours(72)));
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('Awaiting seller response', json_decode(DB::table('notifications')->first()->data, true)['title']);
        $this->as($order->seller)->get(route('seller.returns.index'))->assertSee('Respond to request');
        $this->as($order->buyer)->get(route('buyer.orders.show', $order))->assertSee('Request urgent admin assistance');

        $other = $this->order();
        $this->as($other->buyer)->post(route('buyer.orders.dispute.store', $other), [...$this->requestData(), 'review_path' => 'admin_help'])->assertSessionHasNoErrors();
        $this->assertNull($other->fresh()->dispute->sellerResponse);
        $admin = $this->user('ecommerce_admin');
        $this->as($admin)->patch(route('admin.disputes.resolve', $other->fresh()->dispute), ['status' => 'UNDER_REVIEW', 'admin_notes' => 'Urgent review remains available.'])->assertSessionHasNoErrors();
        $this->assertSame('UNDER_REVIEW', $other->fresh()->dispute->status);
    }

    #[TestWith([''])]
    #[TestWith(['Item used'])]
    #[TestWith(['xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'])]
    public function test_invalid_rejection_reasons_leave_request_pending(string $reason): void
    {
        $case = $this->requestReturn($order = $this->order());
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'reject', 'version' => 1, 'reason' => $reason])->assertSessionHasErrors('reason');
        $this->assertSame('awaiting_seller', $case->fresh()->sellerResponse->stage);
        $this->assertSame('PENDING', $case->fresh()->status);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_buyer_sees_exact_escaped_rejection_reason_and_can_escalate_once(): void
    {
        $this->travelTo(now()->startOfSecond());
        $case = $this->requestReturn($order = $this->order());
        $reason = 'Item shows signs of use and the tag is removed. <script>bad()</script>';
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'reject', 'version' => 1, 'reason' => $reason])->assertSessionHasNoErrors();
        $this->as($order->buyer)->get(route('returns.workflow', $order))->assertSee($reason)->assertDontSee('<script>bad()</script>', false)->assertSee('Escalate');
        $this->assertTrue($case->fresh()->sellerResponse->escalation_due_at->equalTo(now()->addHours(72)));
        $this->post(route('buyer.returns.escalate', $case), ['version' => 2])->assertSessionHasNoErrors();
        $this->assertSame('UNDER_REVIEW', $case->fresh()->status);
        $this->assertSame('escalated', $case->fresh()->sellerResponse->stage);
        $this->post(route('buyer.returns.escalate', $case), ['version' => 3])->assertUnprocessable();
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'approve', 'version' => 3])->assertUnprocessable();
        $this->assertDatabaseCount('refund_executions', 0);
    }

    public function test_ownership_role_and_stale_versions_cannot_change_another_users_case(): void
    {
        $case = $this->requestReturn($order = $this->order());
        $this->as($this->user('seller'))->post(route('seller.returns.respond', $case), ['decision' => 'approve', 'version' => 1])->assertNotFound();
        $this->as($this->user('buyer'))->post(route('buyer.returns.escalate', $case), ['version' => 1, 'urgent' => 1])->assertNotFound();
        $this->as($this->user('courier'))->post(route('seller.returns.respond', $case), ['decision' => 'approve', 'version' => 1])->assertForbidden();
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'approve', 'version' => 9])->assertConflict();
        $this->assertSame('awaiting_seller', $case->fresh()->sellerResponse->stage);
    }

    public function test_seller_approval_does_not_restore_stock_move_custody_or_authorize_refund(): void
    {
        Notification::fake();
        $case = $this->requestReturn($order = $this->order());
        $product = Product::forceCreate(['user_id' => $order->seller_id, 'name' => 'Retained inventory', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 4]);
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'approve', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('seller_approved', $case->fresh()->sellerResponse->stage);
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertDatabaseCount('refund_executions', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $admin = $this->user('ecommerce_admin');
        $this->as($admin)->patch(route('admin.disputes.resolve', $case), ['status' => 'REFUND_APPROVED', 'admin_notes' => 'Refund authorized; arrange physical pickup.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('refund_executions', ['order_id' => $order->id, 'status' => 'AUTHORIZED']);
        $this->assertSame('resolved', $case->fresh()->sellerResponse->stage);
        $this->assertSame('DELIVERED', $order->fresh()->status);
        Notification::assertSentTo($order->buyer, WebOperationsNotification::class);
    }

    public function test_deadline_preview_does_not_mutate_and_exact_72_hour_approval_is_once(): void
    {
        $this->travelTo(now()->startOfSecond());
        $case = $this->requestReturn($order = $this->order());
        $deadline = now()->addHours(72);
        $this->travelTo($deadline->copy()->subSecond());
        $this->artisan('returns:process-deadlines', ['--apply' => true])->assertSuccessful();
        $this->assertSame('awaiting_seller', $case->fresh()->sellerResponse->stage);
        $this->travelTo($deadline);
        $this->artisan('returns:process-deadlines')->assertSuccessful();
        $this->assertSame('awaiting_seller', $case->fresh()->sellerResponse->stage);
        $this->artisan('returns:process-deadlines', ['--apply' => true])->assertSuccessful();
        $this->artisan('returns:process-deadlines', ['--apply' => true])->assertSuccessful();
        $this->assertSame('deadline_approved', $case->fresh()->sellerResponse->stage);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('refund_executions', 0);
        $this->assertDatabaseCount('return_inspection_items', 0);
        $this->assertSame('DELIVERED', $order->fresh()->status);
    }

    public function test_late_seller_request_commits_deadline_approval_without_scheduler_or_late_rejection(): void
    {
        $this->travelTo(now()->startOfSecond());
        $case = $this->requestReturn($order = $this->order());
        $this->travel(72)->hours();
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'reject', 'version' => 1, 'reason' => 'A late rejection must not win.'])->assertSessionHasNoErrors();
        $this->assertSame('deadline_approved', $case->fresh()->sellerResponse->stage);
        $this->assertNull($case->fresh()->sellerResponse->seller_decision);
    }

    public function test_rejection_escalation_closes_at_exact_72_hours_but_not_a_second_earlier(): void
    {
        $this->travelTo(now()->startOfSecond());
        $case = $this->requestReturn($order = $this->order());
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'reject', 'version' => 1, 'reason' => 'Item shows signs of prior use.'])->assertSessionHasNoErrors();
        $this->travel(72)->hours();
        $this->as($order->buyer)->post(route('buyer.returns.escalate', $case), ['version' => 2])->assertSessionHasNoErrors();
        $this->assertSame('REJECTED', $case->fresh()->status);
        $this->assertSame('resolved', $case->fresh()->sellerResponse->stage);
        $this->assertNull($case->fresh()->sellerResponse->escalated_at);
    }

    public function test_urgent_assistance_and_admin_takeover_stop_seller_and_deadline_actions(): void
    {
        $this->travelTo(now()->startOfSecond());
        $case = $this->requestReturn($order = $this->order());
        $this->as($order->buyer)->post(route('buyer.returns.escalate', $case), ['version' => 1, 'urgent' => 1])->assertSessionHasNoErrors();
        $this->travel(73)->hours();
        $this->artisan('returns:process-deadlines', ['--apply' => true])->assertSuccessful();
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'approve', 'version' => 2])->assertUnprocessable();
        $this->assertSame('escalated', $case->fresh()->sellerResponse->stage);
        $this->assertSame('UNDER_REVIEW', $case->fresh()->status);
    }

    public function test_buyer_can_escalate_one_second_before_expiry_and_admin_preempts_a_waiting_seller(): void
    {
        $this->travelTo(now()->startOfSecond());
        $case = $this->requestReturn($order = $this->order());
        $this->as($order->seller)->post(route('seller.returns.respond', $case), ['decision' => 'reject', 'version' => 1, 'reason' => str_repeat('a', 300)])->assertSessionHasNoErrors();
        $this->travelTo(now()->addHours(72)->subSecond());
        $this->as($order->buyer)->post(route('buyer.returns.escalate', $case), ['version' => 2])->assertSessionHasNoErrors();
        $this->assertSame('UNDER_REVIEW', $case->fresh()->status);
        $waiting = $this->requestReturn($another = $this->order());
        $this->as($this->user('ecommerce_admin'))->patch(route('admin.disputes.resolve', $waiting), ['status' => 'UNDER_REVIEW', 'admin_notes' => 'Urgent intervention while seller was reviewing.'])->assertSessionHasNoErrors();
        $this->as($another->seller)->post(route('seller.returns.respond', $waiting), ['decision' => 'approve', 'version' => 1])->assertUnprocessable();
        $this->assertSame('escalated', $waiting->fresh()->sellerResponse->stage);
    }

    public function test_guest_cannot_respond_and_seller_workspace_has_25_scoped_rows_per_page(): void
    {
        $order = $this->order();
        for ($i = 0; $i < 26; $i++) {
            $parcel = $order->replicate(['parcel_code']);
            $parcel->order_number = 'PAGE-'.Str::random(12);
            $parcel->save();
            Dispute::create(['order_id' => $parcel->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Other', 'description' => 'Historical admin-led fixture '.$i, 'status' => 'PENDING']);
        }
        $other = $this->requestReturn($this->order());
        $this->as($order->seller)->get(route('seller.returns.index'))->assertViewHas('cases', fn ($cases) => $cases->count() === 25 && $cases->total() === 26)->assertDontSee($other->order->order_number);
        $this->get(route('seller.returns.index', ['page' => 2]))->assertViewHas('cases', fn ($cases) => $cases->count() === 1);
        $this->flushSession();
        Auth::forgetGuards();
        $this->post(route('seller.returns.respond', $other), ['decision' => 'approve', 'version' => 1])->assertRedirect(route('login'));
    }

    public function test_new_verified_delivery_enrolls_168_hour_window_and_legacy_records_remain_unenrolled(): void
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('private');
        Notification::fake();
        $order = $this->verifiedOrder();
        $order->forceFill(['status' => 'OUT_FOR_DELIVERY', 'delivered_at' => null, 'auto_completion_due_at' => null, 'completion_enrolled_at' => null])->save();
        app(OrderTransitionService::class)->transition($order, $order->deliveryCourier, OrderStatus::Delivered, 'delivered', null, 'Verified delivery', ['delivered_at' => now(), 'delivery_code_used_at' => now(), 'cod_collected_amount' => 150]);
        $this->assertTrue($order->fresh()->auto_completion_due_at->equalTo(now()->addHours(168)));
        $legacy = $this->order(['delivered_at' => now()->subDays(30)]);
        $this->artisan('orders:complete-delivered', ['--apply' => true])->assertSuccessful();
        $this->assertSame('DELIVERED', $legacy->fresh()->status);
        $this->assertNull($legacy->fresh()->completion_enrolled_at);
        Notification::assertSentTo($order->buyer, OrderStatusNotification::class);
    }

    public function test_completion_is_due_at_168_hours_once_with_system_actor_and_no_earnings_release(): void
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('private');
        Notification::fake();
        $order = $this->verifiedOrder();
        $due = now()->addHours(168);
        $this->travelTo($due->copy()->subSecond());
        $this->artisan('orders:complete-delivered', ['--apply' => true])->assertSuccessful();
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->travelTo($due);
        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->artisan('orders:complete-delivered', ['--apply' => true])->assertSuccessful();
        $this->artisan('orders:complete-delivered', ['--apply' => true])->assertSuccessful();
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame(1, $order->trackingEvents()->where('event_type', 'order_auto_completed')->whereNull('actor_id')->count());
        $this->assertSame(1, DB::table('administrative_audits')->where('action', 'order_auto_completed')->count());
        $this->assertDatabaseCount('seller_payouts', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(1, Notification::sent($order->buyer, OrderStatusNotification::class, fn ($notification, $channels) => in_array('database', $channels, true))->count());
        $this->assertSame(1, Notification::sent($order->seller, OrderStatusNotification::class, fn ($notification, $channels) => in_array('database', $channels, true))->count());
        $this->expectException(HttpException::class);
        app(OrderFinancialService::class)->requestPayout($order->fresh(), $order->seller);
    }

    #[TestWith(['dispute'])]
    #[TestWith(['approved_return'])]
    #[TestWith(['code'])]
    #[TestWith(['file'])]
    #[TestWith(['payment'])]
    #[TestWith(['cod'])]
    #[TestWith(['due'])]
    #[TestWith(['exception'])]
    public function test_active_returns_payment_proof_and_due_mismatch_block_system_completion(string $hold): void
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('private');
        Notification::fake();
        $order = $this->verifiedOrder();
        if (in_array($hold, ['dispute', 'approved_return'])) {
            Dispute::create(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'seller_id' => $order->seller_id, 'reason' => 'Damaged Item', 'description' => 'Return under review.', 'status' => $hold === 'dispute' ? 'PENDING' : 'REFUND_APPROVED']);
        } elseif ($hold === 'file') {
            Storage::disk('private')->delete('completion/'.$order->id.'.jpg');
        } elseif ($hold === 'exception') {
            DB::table('logistics_exceptions')->insert(['order_id' => $order->id, 'type' => 'recovery', 'reason' => 'Unresolved custody discrepancy.', 'opened_by' => $order->delivery_courier_id, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $order->forceFill(match ($hold) {
                'code' => ['delivery_code_used_at' => null],
                'payment' => ['payment_method' => 'WALLET'],
                'cod' => ['cod_collected_amount' => 149],
                'due' => ['auto_completion_due_at' => now()->addHour()],
            })->save();
        }
        $this->travel(169)->hours();
        $this->assertFalse(app(OrderTransitionService::class)->completeAutomatically($order));
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
        Notification::assertNothingSent();
    }

    public function test_legacy_enrollment_is_explicit_previewed_audited_and_does_not_itself_complete(): void
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('private');
        $order = $this->verifiedOrder();
        $order->forceFill(['completion_enrolled_at' => null, 'auto_completion_due_at' => null])->save();
        $this->artisan('orders:complete-delivered', ['--enroll-order' => $order->id, '--reason' => 'Reviewed historical proof and collection.'])->assertSuccessful();
        $this->assertNull($order->fresh()->completion_enrolled_at);
        $this->artisan('orders:complete-delivered', ['--apply' => true, '--enroll-order' => $order->id, '--reason' => 'short'])->assertExitCode(2);
        $this->artisan('orders:complete-delivered', ['--apply' => true, '--enroll-order' => $order->id, '--reason' => 'Reviewed historical proof and collection.'])->assertSuccessful();
        $this->assertNotNull($order->fresh()->completion_enrolled_at);
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertSame(1, DB::table('administrative_audits')->where('action', 'completion_legacy_opt_in')->count());
    }

    public function test_buyer_completion_uses_the_same_return_hold_and_preserves_buyer_only_authority(): void
    {
        Storage::fake('private');
        $order = $this->verifiedOrder();
        $this->requestReturn($order);
        $this->as($order->buyer)->get(route('buyer.orders.show', $order))->assertSee('Receipt confirmation is paused')->assertDontSee('>Confirm receipt</button>', false);
        $this->as($order->buyer)->post(route('buyer.orders.confirm', $order))->assertUnprocessable();
        $this->as($order->seller)->post(route('buyer.orders.confirm', $order))->assertForbidden();
        $this->assertSame('DELIVERED', $order->fresh()->status);
    }

    public function test_buyer_can_still_confirm_a_verified_delivery_early_without_waiting_for_system_completion(): void
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('private');
        Notification::fake();
        $order = $this->verifiedOrder();
        $this->as($order->buyer)->get(route('buyer.orders.show', $order))->assertSee('>Confirm receipt</button>', false);
        $this->post(route('buyer.orders.confirm', $order))->assertSessionHasNoErrors();
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertNull($order->fresh()->auto_completed_at);
        Notification::assertSentTo($order->buyer, OrderStatusNotification::class);
    }

    public function test_additive_migration_can_reverse_and_reapply_without_enrolling_historical_orders(): void
    {
        $order = $this->order();
        $migration = require database_path('migrations/2026_11_15_000001_add_seller_return_responses_and_completion_eligibility.php');
        $migration->down();
        $migration->up();
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertNull($order->fresh()->auto_completion_due_at);
        $this->assertDatabaseCount('seller_return_responses', 0);
    }

    public function test_api_dispute_upload_is_private_blocks_completion_and_remains_admin_led(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $order = $this->verifiedOrder();
        $token = ApiToken::createTokenForUser($order->buyer)['plainTextToken'];
        $this->withToken($token)->post('/api/orders/'.$order->id.'/dispute', [...$this->requestData(), 'evidence_file' => UploadedFile::fake()->image('claim.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $case = $order->fresh()->dispute;
        Storage::disk('private')->assertExists($case->evidence_path);
        Storage::disk('public')->assertMissing($case->evidence_path);
        $this->assertNull($case->sellerResponse);
        $this->postJson('/api/orders/'.$order->id.'/dispute', $this->requestData())->assertUnprocessable();
        $this->assertDatabaseCount('disputes', 1);
        $this->assertSame('An active return, dispute or refund prevents completion.', app(OrderCompletionEligibilityService::class)->holdReason($order));
        $this->as($order->seller)->get(route('returns.request-evidence', $case))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->as($order->buyer)->get(route('returns.request-evidence', $case))->assertOk();
        $this->as($this->user('seller'))->get(route('returns.request-evidence', $case))->assertNotFound();
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.invalid', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Return', 'last_name' => 'Tester', 'contact_no' => '09123456789']);
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function order(array $data = []): Order
    {
        return Order::forceCreate(['buyer_id' => $this->user('buyer')->id, 'seller_id' => $this->user('seller')->id, 'delivery_courier_id' => $this->user('courier')->id, 'order_number' => 'RET-'.Str::random(12), 'recipient_name' => 'Synthetic Buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Isolated fixture', 'status' => 'DELIVERED', 'delivered_at' => now()->startOfSecond(), 'payment_method' => 'COD', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'commission_fee' => 10, ...$data]);
    }

    private function verifiedOrder(): Order
    {
        $order = $this->order(['delivery_code_used_at' => now()->startOfSecond(), 'cod_collected_amount' => 150, 'completion_enrolled_at' => now()->startOfSecond(), 'auto_completion_due_at' => now()->startOfSecond()->addHours(168)]);
        $path = 'completion/'.$order->id.'.jpg';
        Storage::disk('private')->put($path, 'Synthetic proof bytes for evidence-presence checks.');
        $order->deliveryAttempts()->create(['rider_id' => $order->delivery_courier_id, 'attempt_no' => 1, 'outcome' => 'delivered', 'proof_path' => $path, 'attempted_at' => $order->delivered_at]);
        $finance = app(OrderFinancialService::class)->initialize($order);
        $finance->forceFill(['collected_cents' => 15000, 'collected_at' => $order->delivered_at, 'remittance_status' => 'COLLECTED'])->save();

        return $order;
    }

    private function requestData(): array
    {
        return ['reason' => 'Damaged Item', 'description' => 'The received item was damaged; please review this return request.'];
    }

    private function requestReturn(Order $order): Dispute
    {
        $this->as($order->buyer)->post(route('buyer.orders.dispute.store', $order), $this->requestData())->assertSessionHasNoErrors();

        return $order->fresh()->dispute;
    }
}
