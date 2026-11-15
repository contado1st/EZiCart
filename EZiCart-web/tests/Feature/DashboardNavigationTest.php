<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
    }

    public function test_buyer_status_groups_filter_the_full_history_before_pagination(): void
    {
        $first = null;
        for ($i = 0; $i < 26; $i++) {
            $order = $this->order(['status' => 'PLACED']);
            $first ??= $order;
        }
        $completed = $this->order(['status' => 'COMPLETED']);
        $otherBuyerOrder = $this->order(['buyer_id' => $this->user('buyer')->id]);
        $this->signIn($this->buyer)->get(route('buyer.dashboard', ['order_group' => 'to_ship']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 26 && $orders->count() === 25)
            ->assertViewHas('counts', fn ($counts) => $counts['all'] === 27 && $counts['to_ship'] === 26)
            ->assertSee('Go to page 2')->assertSee('1–25 of 26 records')
            ->assertDontSee($completed->order_number)->assertDontSee($otherBuyerOrder->order_number);
        $this->get(route('buyer.dashboard', ['order_group' => 'to_ship', 'page' => 2]))->assertOk()
            ->assertSee($first->order_number)->assertSee('26–26 of 26 records')
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1);
        $this->get(route('buyer.dashboard', ['order_group' => 'completed']))->assertOk()->assertSee($completed->order_number)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
    }

    public function test_rider_queues_are_disjoint_and_have_independent_pages_and_totals(): void
    {
        $rider = $this->user('courier');
        $first = null;
        for ($i = 0; $i < 26; $i++) {
            $order = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now()]);
            $first ??= $order;
        }
        $offer = $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id]);
        $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $this->user('courier')->id]);
        $this->signIn($rider)->get(route('courier.dashboard', ['workspace_tab' => 'accepted-pickups']))->assertOk()
            ->assertViewHas('claimedPickups', fn ($queue) => $queue->total() === 26 && $queue->count() === 25)
            ->assertViewHas('availablePickups', fn ($queue) => $queue->total() === 1 && $queue->first()->id === $offer->id)
            ->assertViewHas('stats', fn ($stats) => $stats['claimed_pickups'] === 26)
            ->assertSee('data-workspace-tab="accepted-pickups"', false)->assertSee('claimed_page=2');
        $this->get(route('courier.dashboard', ['workspace_tab' => 'accepted-pickups', 'claimed_page' => 2]))->assertOk()
            ->assertViewHas('claimedPickups', fn ($queue) => $queue->count() === 1 && $queue->first()->id === $first->id)
            ->assertViewHas('availablePickups', fn ($queue) => $queue->currentPage() === 1 && $queue->total() === 1)
            ->assertViewHas('stats', fn ($stats) => $stats['claimed_pickups'] === 26);
    }

    public function test_hub_queues_show_all_matching_parcels_without_leaking_other_hubs(): void
    {
        $hub = $this->user('sorting_center');
        $other = $this->user('sorting_center');
        for ($i = 0; $i < 26; $i++) {
            $this->order(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $hub->id]);
        }
        $privateOrder = $this->order(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $other->id]);
        $this->signIn($hub)->get(route('logistics.dashboard', ['workspace_tab' => 'hub-sorting']))->assertOk()
            ->assertViewHas('queues', fn ($queues) => $queues['sorting']->total() === 26 && $queues['sorting']->count() === 25)
            ->assertDontSee($privateOrder->order_number)->assertSee('sorting_page=2');
        $this->get(route('logistics.dashboard', ['workspace_tab' => 'hub-sorting', 'sorting_page' => 2]))->assertOk()
            ->assertViewHas('queues', fn ($queues) => $queues['sorting']->count() === 1 && $queues['dispatch']->currentPage() === 1);
    }

    public function test_seller_and_marketplace_admin_overviews_have_reachable_second_pages(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->order();
            $this->user('seller', ['status' => 'pending']);
        }
        $foreignOrder = $this->order(['seller_id' => $this->user('seller')->id]);
        $this->signIn($this->seller)->get(route('seller.dashboard'))->assertOk()
            ->assertViewHas('recentOrders', fn ($orders) => $orders->total() === 26 && $orders->count() === 25)
            ->assertViewHas('orderStatuses', fn ($statuses) => $statuses['SORTED'] === 26)
            ->assertDontSee($foreignOrder->order_number)->assertSee('orders_page=2');
        $this->get(route('seller.dashboard', ['orders_page' => 2]))->assertOk()
            ->assertViewHas('recentOrders', fn ($orders) => $orders->count() === 1);
        $this->signIn($this->user('ecommerce_admin'))->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('recentOrders', fn ($orders) => $orders->total() === 27 && $orders->count() === 25)
            ->assertViewHas('pendingUsers', fn ($users) => $users->total() === 26 && $users->count() === 25)
            ->assertSee('approvals_page=2');
        $this->get(route('admin.dashboard', ['approvals_page' => 2]))->assertOk()
            ->assertViewHas('pendingUsers', fn ($users) => $users->count() === 1)
            ->assertViewHas('recentOrders', fn ($orders) => $orders->currentPage() === 1);
        $this->get(route('admin.registrations.index'))->assertOk()->assertSee('Go to page 2');
    }

    public function test_inventory_pagination_and_management_directory_keep_their_owner_scope(): void
    {
        for ($i = 0; $i < 26; $i++) {
            Product::forceCreate(['user_id' => $this->seller->id, 'name' => 'Inventory '.$i, 'category' => 'Home and Garden', 'price' => 100, 'stock' => 2, 'weight_grams' => 1000, 'compliance_status' => 'approved']);
            $this->user('courier');
        }
        $this->signIn($this->seller)->get(route('seller.products.index'))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 26 && $products->count() === 25)
            ->assertSee('Go to page 2');
        $this->signIn($this->user('logistics_admin'))->get(route('staff.index'))->assertOk()
            ->assertViewHas('staff', fn ($staff) => $staff->total() === 26 && $staff->count() === 25 && $staff->every(fn ($user) => $user->role === 'courier'))
            ->assertSee('Go to page 2');
        $this->get(route('staff.index', ['page' => 2]))->assertOk()->assertViewHas('staff', fn ($staff) => $staff->count() === 1);
        $this->get(route('logistics-admin.dashboard', ['workspace_tab' => 'managed-accounts', 'users_page' => 2]))->assertOk()
            ->assertViewHas('managedUsers', fn ($users) => $users->count() === 1)->assertSee('data-workspace-tab="managed-accounts"', false);
    }

    public function test_storage_and_area_tables_paginate_without_truncating_mapping_choices(): void
    {
        $hub = $this->user('sorting_center');
        for ($i = 0; $i < 26; $i++) {
            Area::forceCreate(['name' => 'Area '.$i, 'code' => 'AREA-'.$i, 'sorting_center_id' => $hub->id, 'is_active' => true]);
            DB::table('storage_locations')->insert(['hub_id' => $hub->id, 'code' => 'ZONE-'.$i, 'label' => 'Zone '.$i, 'type' => 'STAGING', 'capacity' => 50]);
        }
        $this->signIn($hub)->get(route('logistics.storage'))->assertOk()
            ->assertViewHas('locations', fn ($locations) => $locations->total() === 26 && $locations->count() === 25)
            ->assertSee('locations_page=2');
        $this->get(route('logistics.storage', ['locations_page' => 2]))->assertOk()
            ->assertViewHas('locations', fn ($locations) => $locations->count() === 1);
        $this->get(route('logistics.areas'))->assertOk()
            ->assertViewHas('areas', fn ($areas) => $areas->total() === 26 && $areas->count() === 25)
            ->assertViewHas('areaOptions', fn ($areas) => $areas->count() === 26)->assertSee('Go to page 2');
    }

    public function test_numbered_links_keep_filters_and_the_selected_panel(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->user('courier', ['status' => 'pending', 'first_name' => 'Matching applicant']);
        }
        $response = $this->signIn($this->user('logistics_admin'))->get(route('logistics-admin.dashboard', ['application_search' => 'Matching', 'workspace_tab' => 'applications', 'users_page' => 2]));
        $response->assertOk()->assertSee('application_search=Matching')->assertSee('workspace_tab=applications')
            ->assertSee('applications_page=2')->assertSee('users_page=2')->assertSee('Go to page 2');
        $this->get(route('logistics-admin.dashboard', ['applications_page' => 99]))->assertOk()->assertSee('No records on this page.');
    }

    public function test_empty_queue_pages_do_not_claim_that_all_parcel_work_is_finished(): void
    {
        $rider = $this->user('courier');
        $this->order(['status' => 'READY_FOR_PICKUP', 'pickup_courier_id' => $rider->id, 'pickup_claimed_at' => now()]);
        $this->signIn($rider)->get(route('courier.dashboard', ['workspace_tab' => 'accepted-pickups', 'claimed_page' => 99]))
            ->assertOk()->assertSee('Check your parcel queues')->assertSee('Parcels are available on another page.')
            ->assertDontSee('No open parcel tasks')
            ->assertViewHas('claimedPickups', fn ($queue) => $queue->total() === 1 && $queue->isEmpty());

        $hub = $this->user('sorting_center');
        $this->order(['status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $hub->id]);
        $this->signIn($hub)->get(route('logistics.dashboard', ['workspace_tab' => 'hub-sorting', 'sorting_page' => 99]))
            ->assertOk()->assertSee('No parcels on this page. This queue has 1 parcel.')
            ->assertSee('Return to first page')->assertSee('sorting_page=1', false)
            ->assertViewHas('queues', fn ($queues) => $queues['sorting']->total() === 1 && $queues['sorting']->isEmpty());
        $this->get(route('logistics.dashboard'))->assertOk()
            ->assertSee('data-label="Destination"', false)->assertSee('data-label="Current stage"', false)
            ->assertSee('data-label="Last updated"', false);
    }

    public function test_buyer_feedback_is_announced_once_with_the_correct_severity(): void
    {
        foreach ([
            ['success', 'Your order update was saved.', 'status'],
            ['error', 'The parcel was already collected.', 'alert'],
        ] as [$key, $message, $role]) {
            $response = $this->signIn($this->buyer)->withSession([$key => $message])->get(route('buyer.dashboard'));
            $response->assertOk();
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, $message), 'Feedback must not be duplicated.');
            $document = new \DOMDocument;
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//*[@role="'.$role.'"][contains(., "'.$message.'")]')->length);
        }
    }

    public function test_control_tower_empty_pages_preserve_queue_truth_and_filter_recovery(): void
    {
        $admin = $this->user('logistics_admin');
        $hub = $this->user('sorting_center');
        $this->user('courier', ['status' => 'pending']);
        $order = $this->order(['sorting_center_id' => $hub->id]);
        Area::forceCreate(['name' => 'Demo area', 'code' => 'EMPTY-PAGE', 'sorting_center_id' => $hub->id, 'is_active' => true]);
        DB::table('logistics_exceptions')->insert(['order_id' => $order->id, 'opened_by' => $admin->id, 'type' => 'DEMO_RECOVERY', 'reason' => 'Review physical custody.', 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->signIn($admin)->get(route('logistics-admin.dashboard', [
            'page' => 99, 'applications_page' => 99, 'users_page' => 99, 'areas_page' => 99, 'exceptions_page' => 99,
            'workspace_tab' => 'parcels', 'search' => 'NAV-',
        ]));
        $response->assertOk()->assertSee('No parcels on this page.')->assertSee('No applications on this page.')
            ->assertSee('No managed accounts on this page.')->assertSee('No service areas on this page.')
            ->assertSee('No recovery exceptions on this page.')->assertDontSee('No open recovery exceptions.');

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        foreach (['parcels' => 'page', 'applications' => 'applications_page', 'managed-accounts' => 'users_page', 'service-areas' => 'areas_page', 'network-exceptions' => 'exceptions_page'] as $panel => $pageName) {
            /** @var \DOMElement $link */
            $link = $xpath->query('//*[@id="'.$panel.'"]//a[normalize-space(.)="Return to first page"]')->item(0);
            $this->assertInstanceOf(\DOMElement::class, $link);
            parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame('1', $query[$pageName]);
            $this->assertSame('NAV-', $query['search']);
            $this->assertSame('parcels', $query['workspace_tab']);
        }
    }

    public function test_control_tower_disclosures_preserve_guarded_forms_and_reopen_after_validation_errors(): void
    {
        $hub = $this->user('sorting_center');
        $sorted = $this->order(['sorting_center_id' => $hub->id]);
        $this->order(['status' => 'READY_FOR_PICKUP']);
        $admin = $this->user('logistics_admin');
        $response = $this->signIn($admin)->get(route('logistics-admin.dashboard'))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//details[contains(@class,"workspace-table-disclosure")]')->length);
        $this->assertSame(0, $xpath->query('//details[contains(@class,"workspace-table-disclosure")][@open]')->length);
        /** @var \DOMElement $form */
        foreach ($xpath->query('//details[contains(@class,"workspace-table-disclosure")]/form') as $form) {
            $this->assertSame('post', strtolower($form->getAttribute('method')));
            $this->assertNotSame('', $form->getAttribute('data-decision'));
            $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
            $this->assertSame(1, $xpath->query('.//*[@name="reason"][@required][@minlength="5"][@maxlength="500"]', $form)->length);
            $this->assertSame(1, $xpath->query('.//select[@required]', $form)->length);
        }

        $this->from(route('logistics-admin.dashboard'))->post(route('logistics-admin.orders.transfer', $sorted), ['destination_hub_id' => $hub->id])
            ->assertRedirect()->assertSessionHasErrors('reason');
        // A browser carries its session ID through the redirect so JSON errors reload from storage.
        $invalid = $this->withCookie((string) config('session.cookie'), $this->app['session']->getId())
            ->get(route('logistics-admin.dashboard'))->assertOk();
        $document->loadHTML($invalid->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(2, (new \DOMXPath($document))->query('//details[contains(@class,"workspace-table-disclosure")][@open]')->length, 'Server validation errors must reopen the action forms.');
    }

    private function user(string $role, array $extra = []): User
    {
        return User::forceCreate($extra + ['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Navigation', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789']);
    }

    private function order(array $extra = []): Order
    {
        return Order::forceCreate($extra + ['order_number' => 'NAV-'.Str::upper(Str::random(10)), 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'recipient_name' => 'Demo buyer', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro', 'street_address' => 'Test street', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'SORTED', 'parcel_weight_grams' => 1000]);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }
}
