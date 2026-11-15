<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaMunicipality;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Services\LogisticsQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LogisticsQueueTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $hub;

    private User $otherHub;

    private User $buyer;

    private User $seller;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->hub = $this->user('sorting_center');
        $this->otherHub = $this->user('sorting_center');
        $this->buyer = $this->user('buyer');
        $this->buyer->update(['first_name' => 'Maria', 'last_name' => 'Clara']);
        $this->seller = $this->user('seller');
        $this->area = Area::create(['name' => 'Home hub', 'code' => 'home-hub', 'sorting_center_id' => $this->hub->id, 'is_active' => true]);
        AreaMunicipality::create(['area_id' => $this->area->id, 'province' => 'Laguna', 'municipality' => 'Majayjay', 'province_normalized' => 'laguna', 'municipality_normalized' => 'majayjay']);
    }

    #[TestWith(['inbound', 'PICKED_UP'])]
    #[TestWith(['at_center', 'AT_SORTING_CENTER'])]
    #[TestWith(['sorted', 'SORTED'])]
    #[TestWith(['assigned', 'ASSIGNED_TO_RIDER'])]
    #[TestWith(['out', 'OUT_FOR_DELIVERY'])]
    #[TestWith(['failed', 'DELIVERY_FAILED'])]
    #[TestWith(['returned', 'RETURNED_TO_SELLER'])]
    #[TestWith(['delivered_today', 'COMPLETED'])]
    public function test_dashboard_card_total_matches_its_own_hub_linked_queue_across_pages(string $queue, string $status): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->order(['status' => $status, 'delivered_at' => now()]);
        }
        $foreign = $this->order(['sorting_center_id' => $this->otherHub->id, 'status' => $status, 'delivered_at' => now()]);
        Cache::put('logistics.dashboard.stats.'.$this->hub->id.'.'.today()->toDateString(), ['inbound' => 999], 600);
        $dashboard = $this->signIn($this->hub)->get(route('logistics.dashboard'))->assertOk();
        $this->assertSame(26, $dashboard->viewData('stats')[$queue]);
        $dashboard->assertSee(route('logistics.tracking', ['queue' => $queue]), false)->assertDontSee($foreign->order_number);
        $tracking = $this->get(route('logistics.tracking', ['queue' => $queue]))->assertOk()->assertDontSee($foreign->order_number);
        $this->assertSame(26, $tracking->viewData('orders')->total());
        $this->assertCount(20, $tracking->viewData('orders')->items());
        $pageTwo = $this->get(route('logistics.tracking', ['queue' => $queue, 'page' => 2]))->assertOk();
        $this->assertCount(6, $pageTwo->viewData('orders')->items());
        $this->assertEmpty(array_intersect($tracking->viewData('orders')->modelKeys(), $pageTwo->viewData('orders')->modelKeys()));
    }

    public function test_sorting_immediately_updates_cards_and_matching_totals_without_cache_expiry(): void
    {
        $order = $this->order(['status' => 'AT_SORTING_CENTER', 'received_at' => now()]);
        $before = $this->signIn($this->hub)->get(route('logistics.dashboard'))->assertOk();
        $this->assertSame(1, $before->viewData('stats')['at_center']);
        $this->post(route('logistics.orders.sort', $order))->assertRedirect();
        $after = $this->get(route('logistics.dashboard'))->assertOk();
        $this->assertSame(0, $after->viewData('stats')['at_center']);
        $this->assertSame(1, $after->viewData('stats')['sorted']);
        $this->assertSame(1, $after->viewData('queues')['dispatch']->total());
        $tracking = $this->get(route('logistics.tracking', ['queue' => 'sorted']))->assertOk();
        $this->assertSame([$order->id], $tracking->viewData('orders')->modelKeys());
        Notification::assertSentTo($this->buyer, OrderStatusNotification::class);
    }

    public function test_delivered_today_has_exclusive_next_midnight_and_returns_include_received_parcels(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $today = $this->order(['status' => 'DELIVERED', 'delivered_at' => today()]);
        $completed = $this->order(['status' => 'COMPLETED', 'delivered_at' => today()->addHours(23)]);
        $this->order(['status' => 'DELIVERED', 'delivered_at' => today()->subSecond()]);
        $this->order(['status' => 'DELIVERED', 'delivered_at' => today()->addDay()]);
        $this->order(['status' => 'DELIVERED', 'delivered_at' => null]);
        $this->order(['status' => 'RETURN_IN_TRANSIT']);
        $this->order(['status' => 'RETURNED_TO_SELLER']);
        $stats = app(LogisticsQueueService::class)->counts($this->hub);
        $this->assertSame(2, $stats['delivered_today']);
        $this->assertSame(2, $stats['returned']);
        $tracking = $this->signIn($this->hub)->get(route('logistics.tracking', ['queue' => 'delivered_today']))->assertOk();
        $this->assertEqualsCanonicalizing([$today->id, $completed->id], $tracking->viewData('orders')->modelKeys());
    }

    #[TestWith(['order'])]
    #[TestWith(['id'])]
    #[TestWith(['parcel'])]
    #[TestWith(['prefixed_parcel'])]
    #[TestWith(['first_name'])]
    #[TestWith(['full_name'])]
    #[TestWith(['recipient'])]
    public function test_tracking_looks_up_the_supported_references_inside_hub_and_status_scope(string $kind): void
    {
        $order = $this->order(['order_number' => 'FIND-ME-123', 'status' => 'SORTED', 'recipient_name' => 'Snapshot receiver']);
        $foreign = $this->order(['sorting_center_id' => $this->otherHub->id, 'order_number' => 'FIND-ME-FOREIGN', 'recipient_name' => 'Snapshot receiver']);
        $wrongStatus = $this->order(['status' => 'PICKED_UP', 'order_number' => 'FIND-ME-OTHER-STAGE', 'recipient_name' => 'Snapshot receiver']);
        $search = match ($kind) {
            'order' => 'FIND-ME', 'id' => (string) $order->id, 'parcel' => strtolower($order->parcel_code), 'prefixed_parcel' => 'ezp:'.$order->parcel_code,
            'first_name' => 'Maria', 'full_name' => 'Maria Clara', 'recipient' => 'Snapshot receiver',
        };
        $response = $this->signIn($this->hub)->get(route('logistics.tracking', ['status' => 'SORTED', 'search' => $search]))->assertOk();
        $this->assertSame([$order->id], $response->viewData('orders')->modelKeys());
        $response->assertDontSee($foreign->order_number)->assertDontSee($wrongStatus->order_number);
    }

    #[TestWith(['%'])]
    #[TestWith(['_'])]
    #[TestWith(["' OR 1=1 --"])]
    #[TestWith(['999999999999999999999999999999'])]
    public function test_unknown_wildcard_and_injection_searches_cannot_expand_results(string $search): void
    {
        $this->order();
        $this->order(['sorting_center_id' => $this->otherHub->id]);
        $response = $this->signIn($this->hub)->get(route('logistics.tracking', ['search' => $search]))->assertOk();
        $this->assertSame(0, $response->viewData('orders')->total());
    }

    public function test_literal_wildcards_are_searchable_and_queue_filters_are_not_or_conditions(): void
    {
        $order = $this->order(['order_number' => 'LITERAL%_CODE', 'status' => 'SORTED']);
        $this->order(['order_number' => 'LITERAL-ANY-CODE', 'status' => 'SORTED']);
        $this->signIn($this->hub);
        $response = $this->get(route('logistics.tracking', ['queue' => 'sorted', 'search' => '%_']))->assertOk();
        $this->assertSame([$order->id], $response->viewData('orders')->modelKeys());
        $response = $this->get(route('logistics.tracking', ['queue' => 'sorted', 'status' => 'PICKED_UP']))->assertOk();
        $this->assertSame(0, $response->viewData('orders')->total());
        $this->getJson(route('logistics.tracking', ['queue' => 'unknown']))->assertUnprocessable()->assertJsonValidationErrors('queue');
    }

    public function test_completed_search_is_explicit_and_remains_subject_to_rider_area_and_date(): void
    {
        $rider = $this->user('courier');
        $order = $this->order(['status' => 'COMPLETED', 'delivery_courier_id' => $rider->id]);
        $this->signIn($this->hub);
        $this->assertSame(0, $this->get(route('logistics.tracking', ['search' => $order->order_number]))->viewData('orders')->total());
        $filters = ['status' => 'COMPLETED', 'search' => $order->order_number, 'rider' => $rider->id, 'area' => $this->area->id, 'date' => today()->toDateString()];
        $response = $this->get(route('logistics.tracking', $filters))->assertOk();
        $this->assertSame([$order->id], $response->viewData('orders')->modelKeys());
        $filters['date'] = today()->subDay()->toDateString();
        $this->assertSame(0, $this->get(route('logistics.tracking', $filters))->viewData('orders')->total());
    }

    public function test_null_hub_exceptions_use_the_same_hub_scope_as_the_orders(): void
    {
        $foreignArea = Area::create(['name' => 'Other hub', 'code' => 'other-hub', 'sorting_center_id' => $this->otherHub->id, 'is_active' => true]);
        $own = $this->order(['sorting_center_id' => null, 'status' => 'PICKED_UP']);
        $foreign = $this->order(['sorting_center_id' => null, 'destination_area_id' => $foreignArea->id, 'status' => 'PICKED_UP']);
        $rider = $this->user('courier');
        foreach ([$own, $foreign] as $order) {
            DB::table('logistics_exceptions')->insert(['order_id' => $order->id, 'previous_rider_id' => $rider->id, 'opened_by' => $this->hub->id, 'type' => 'rider_suspended', 'reason' => 'Pending physical recovery', 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        }
        $response = $this->signIn($this->hub)->get(route('logistics.dashboard'))->assertOk();
        $this->assertSame(1, $response->viewData('exceptions')->total());
        $response->assertSee($own->order_number)->assertDontSee($foreign->order_number);
    }

    public function test_numeric_id_and_prefixed_parcel_searches_are_exact_instead_of_name_or_order_fragments(): void
    {
        $order = $this->order();
        $this->order(['order_number' => 'CONTAINS-'.$order->id, 'recipient_name' => 'EZP:'.$order->parcel_code]);
        $this->signIn($this->hub);
        foreach ([(string) $order->id, 'EZP:'.$order->parcel_code] as $search) {
            $response = $this->get(route('logistics.tracking', ['search' => $search]))->assertOk();
            $this->assertSame([$order->id], $response->viewData('orders')->modelKeys());
        }
    }

    public function test_tracking_is_read_only_and_requires_the_logistics_role(): void
    {
        $order = $this->order();
        $before = DB::table('administrative_audits')->count();
        $this->get(route('logistics.tracking'))->assertRedirect();
        $this->signIn($this->buyer)->get(route('logistics.tracking'))->assertForbidden();
        $this->signIn($this->hub)->get(route('logistics.tracking', ['search' => $order->order_number]))->assertOk();
        $this->assertSame($before, DB::table('administrative_audits')->count());
        $this->assertSame('PICKED_UP', $order->fresh()->status);
    }

    private function order(array $extra = []): Order
    {
        return Order::forceCreate($extra + ['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'sorting_center_id' => $this->hub->id, 'destination_area_id' => $this->area->id, 'order_number' => 'QUEUE-'.Str::random(12), 'status' => 'PICKED_UP', 'recipient_name' => 'Recipient', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Test street', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD']);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Queue', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
}
