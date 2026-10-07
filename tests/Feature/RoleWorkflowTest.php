<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;

    protected User $seller;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->buyer = $this->createUser([
            'email' => 'buyer@ezicart.com',
            'role' => 'buyer',
            'status' => 'approved',
        ]);

        $this->seller = $this->createUser([
            'email' => 'seller@ezicart.com',
            'role' => 'seller',
            'status' => 'approved',
            'business_name' => 'Tech Haven',
            'line_of_business' => 'Electronics',
        ]);

        $this->admin = $this->createUser([
            'email' => 'admin@ezicart.com',
            'role' => 'admin',
            'status' => 'approved',
        ]);
    }

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09123456789',
            'birthday' => '2000-01-01',
            'age' => 26,
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '123 Rizal St',
        ], $overrides));
    }

    public function test_buyer_cannot_access_seller_or_admin_routes(): void
    {
        $this->actingAs($this->buyer)
            ->get(route('seller.dashboard'))
            ->assertStatus(403);

        $this->actingAs($this->buyer)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);
    }

    public function test_buyer_can_manage_addresses_and_profile(): void
    {
        // 1. Visit addresses page
        $this->actingAs($this->buyer)
            ->get(route('buyer.addresses.index'))
            ->assertStatus(200);

        // 2. Add an address
        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'Maria Santos',
                'phone_number' => '09987654321',
                'province' => 'Cavite',
                'municipality' => 'Tagaytay',
                'barangay' => 'Silang Crossing',
                'street_address' => 'Blk 4 Lot 5 Tagaytay Heights',
                'is_default' => 1,
            ]);

        $response->assertRedirect(route('buyer.addresses.index'));

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->buyer->id,
            'recipient_name' => 'Maria Santos',
            'is_default' => true,
        ]);

        // 3. Update profile
        $avatar = UploadedFile::fake()->create('avatar.jpg', 50, 'image/jpeg');

        $profileResponse = $this->actingAs($this->buyer)
            ->from(route('buyer.profile'))
            ->put(route('buyer.profile.update'), [
                'first_name' => 'UpdatedBuyer',
                'last_name' => 'Name',
                'contact_no' => '09112223334',
                'sex' => 'Male',
                'birthday' => '2000-01-01',
                'profile_photo' => $avatar,
            ]);

        $profileResponse->assertRedirect(route('buyer.profile'));
        $this->buyer->refresh();
        $this->assertEquals('UpdatedBuyer', $this->buyer->first_name);
        $this->assertNotNull($this->buyer->profile_photo_path);
    }

    public function test_buyer_checkout_uses_selected_delivery_address(): void
    {
        $product = Product::create([
            'user_id' => $this->seller->id,
            'name' => 'Gaming Mouse',
            'category' => 'Electronics',
            'price' => 500.00,
            'stock' => 20,
            'status' => 'approved',
            'is_archived' => false,
        ]);

        // Put product in session cart
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => 500.00,
                'quantity' => 2,
                'image' => null,
            ],
        ];

        $address = UserAddress::create([
            'user_id' => $this->buyer->id,
            'label' => 'Home',
            'recipient_name' => 'Buyer Recipient',
            'phone_number' => '09129998877',
            'province' => 'Batangas',
            'municipality' => 'Lipa',
            'barangay' => 'Marawoy',
            'street_address' => '100 Main Road',
            'is_default' => true,
        ]);

        // Place order
        $response = $this->actingAs($this->buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.process'), [
                'recipient_name' => $address->recipient_name,
                'recipient_contact' => $address->phone_number,
                'province' => $address->province,
                'municipality' => $address->municipality,
                'barangay' => $address->barangay,
                'street_address' => $address->street_address,
                'payment_method' => 'COD',
            ]);

        $response->assertRedirect(route('buyer.dashboard'));

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'payment_method' => 'COD',
            'status' => 'PLACED',
        ]);
    }

    public function test_buyer_can_view_orders_and_confirm_receipt(): void
    {
        $order = Order::create([
            'order_number' => 'EZ-TEST-0001',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'status' => 'DELIVERED',
            'payment_status' => 'PAID',
            'payment_method' => 'COD',
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'shipping_fee' => 50.00,
            'total_amount' => 1050.00,
            'commission_fee' => 100.00,
            'net_seller_payout' => 900.00,
            'recipient_name' => 'Buyer Person',
            'recipient_contact' => '09123456789',
            'street_address' => '123 Street',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
        ]);

        $this->actingAs($this->buyer)
            ->get(route('buyer.orders.show', $order->id))
            ->assertStatus(200)
            ->assertSee('EZ-TEST-0001');

        // Confirm received
        $confirmResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.orders.confirm', $order->id));

        $confirmResponse->assertRedirect();
        $order->refresh();
        $this->assertEquals('COMPLETED', $order->status);
    }

    public function test_seller_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->seller)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);
    }

    public function test_seller_can_view_dashboard_stats_and_feedback(): void
    {
        $this->actingAs($this->seller)
            ->get(route('seller.dashboard'))
            ->assertStatus(200)
            ->assertSee('Tech Haven');

        $this->actingAs($this->seller)
            ->get(route('seller.feedback'))
            ->assertStatus(200);
    }

    public function test_seller_can_fulfill_order_milestones_and_view_waybill(): void
    {
        $order = Order::create([
            'order_number' => 'EZ-TEST-0002',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'status' => 'PLACED',
            'payment_status' => 'UNPAID',
            'payment_method' => 'COD',
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'shipping_fee' => 50.00,
            'total_amount' => 550.00,
            'commission_fee' => 50.00,
            'net_seller_payout' => 450.00,
            'recipient_name' => 'Buyer Person',
            'recipient_contact' => '09123456789',
            'street_address' => '123 Street',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
        ]);

        // Seller sets status to PREPARING
        $this->actingAs($this->seller)
            ->patch(route('seller.orders.updateStatus', $order->id), [
                'status' => 'PREPARING',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals('PREPARING', $order->status);

        // Seller sets status to READY_FOR_PICKUP
        $this->actingAs($this->seller)
            ->patch(route('seller.orders.updateStatus', $order->id), [
                'status' => 'READY_FOR_PICKUP',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals('READY_FOR_PICKUP', $order->status);

        // Seller prints waybill
        $this->actingAs($this->seller)
            ->get(route('seller.orders.waybill', $order->id))
            ->assertStatus(200);
    }

    public function test_admin_can_approve_or_reject_registrations(): void
    {
        $pendingUser = $this->createUser([
            'email' => 'new.seller@applicant.com',
            'role' => 'seller',
            'status' => 'pending',
            'business_name' => 'Pending Shop',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.registrations.index'))
            ->assertStatus(200)
            ->assertSee('Pending Shop');

        $this->actingAs($this->admin)
            ->post(route('admin.registrations.approve', $pendingUser->id))
            ->assertRedirect();

        $pendingUser->refresh();
        $this->assertEquals('approved', $pendingUser->status);
    }

    public function test_admin_can_moderate_users_suspend_and_reactivate(): void
    {
        $badUser = $this->createUser([
            'email' => 'rulebreaker@ezicart.com',
            'role' => 'seller',
            'status' => 'approved',
        ]);

        // Suspend user
        $this->actingAs($this->admin)
            ->post(route('admin.moderation.suspend', $badUser->id), [
                'suspension_reason' => 'Prohibited items detected',
            ])
            ->assertRedirect();

        $badUser->refresh();
        $this->assertEquals('suspended', $badUser->status);
        $this->assertEquals('Prohibited items detected', $badUser->suspension_reason);

        // Reactivate user
        $this->actingAs($this->admin)
            ->post(route('admin.moderation.reactivate', $badUser->id))
            ->assertRedirect();

        $badUser->refresh();
        $this->assertEquals('approved', $badUser->status);
        $this->assertNull($badUser->suspension_reason);
    }

    public function test_admin_can_view_commission_reports_and_publish_policies(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index'))
            ->assertStatus(200)
            ->assertSee('Platform Revenue (10%)');

        $this->actingAs($this->admin)
            ->get(route('admin.policies'))
            ->assertStatus(200)
            ->assertSee('Settlement Policy');

        // Publish policy announcement
        $response = $this->actingAs($this->admin)
            ->post(route('admin.policies.update'), [
                'title' => 'Updated Marketplace Terms (October 2026)',
                'content' => 'All sellers must adhere to the 10% commission and verified listing policies.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'title' => 'Updated Marketplace Terms (October 2026)',
            'target_role' => 'all',
            'is_active' => true,
        ]);
    }

    public function test_logout_redirects_to_landing_page_for_all_roles(): void
    {
        // 1. Buyer logout
        $this->actingAs($this->buyer)
            ->post(route('logout'))
            ->assertRedirect(route('landing'));

        // 2. Seller logout
        $this->actingAs($this->seller)
            ->post(route('logout'))
            ->assertRedirect(route('landing'));

        // 3. Admin logout
        $this->actingAs($this->admin)
            ->post(route('logout'))
            ->assertRedirect(route('landing'));
    }
}
