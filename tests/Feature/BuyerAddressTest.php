<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BuyerAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    public function test_existing_profile_address_is_preserved_only_once(): void
    {
        $buyer = $this->user();
        $this->actingAs($buyer)->get(route('buyer.addresses.index'))->assertOk()->assertSee('Saved addresses');
        $this->get(route('buyer.addresses.index'))->assertOk();
        $this->assertDatabaseCount('buyer_addresses', 1);
        $this->assertDatabaseHas('buyer_addresses', ['user_id' => $buyer->id, 'street_address' => '1 Original Street', 'is_default' => true]);
    }

    public function test_buyer_can_save_edit_choose_default_and_remove_addresses(): void
    {
        $buyer = $this->user();
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $this->address())->assertRedirect(route('buyer.addresses.index'));
        $this->assertSame(2, $buyer->addresses()->count());
        $home = $buyer->addresses()->where('is_default', true)->firstOrFail();
        $work = $buyer->addresses()->where('label', 'Work')->firstOrFail();
        $this->assertFalse($work->is_default);
        $this->get(route('buyer.addresses.edit', $work))->assertOk()->assertSee('Office Recipient');
        $this->put(route('buyer.addresses.update', $work), $this->address(['label' => 'Office', 'street_address' => '30 Updated Street']))->assertRedirect();
        $this->assertSame('30 Updated Street', $work->fresh()->street_address);
        $this->assertSame('1 Original Street', $buyer->fresh()->street_address);

        $this->patch(route('buyer.addresses.default', $work))->assertRedirect();
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
        $this->assertTrue($work->fresh()->is_default);
        $this->assertSame('30 Updated Street', $buyer->fresh()->street_address);
        $this->delete(route('buyer.addresses.destroy', $work))->assertRedirect();
        $this->assertTrue($home->fresh()->is_default);
        $this->assertSame('1 Original Street', $buyer->fresh()->street_address);

        $this->delete(route('buyer.addresses.destroy', $home))->assertRedirect();
        $this->get(route('buyer.addresses.index'))->assertOk()->assertSee('No saved addresses yet');
        $this->assertSame(0, $buyer->addresses()->count());
        $this->assertNull($buyer->fresh()->province);
        $this->post(route('buyer.addresses.store'), $this->address())->assertRedirect();
        $this->assertTrue($buyer->addresses()->sole()->is_default);
    }

    public function test_profile_address_editor_keeps_the_default_saved_location_in_sync(): void
    {
        $buyer = $this->user();
        $this->actingAs($buyer)->get(route('account.profile.edit'))->assertOk()->assertSee('Manage saved addresses');
        $this->patch(route('account.profile.update'), [
            'first_name' => 'Test', 'last_name' => 'Buyer', 'contact_no' => '09123456789',
            ...$this->address(['street_address' => '45 New Profile Street']),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('45 New Profile Street', $buyer->addresses()->where('is_default', true)->sole()->street_address);
    }

    public function test_editing_the_default_or_selecting_it_again_preserves_exactly_one_default(): void
    {
        $buyer = $this->user();
        $this->actingAs($buyer)->get(route('buyer.addresses.index'))->assertOk();
        $home = $buyer->addresses()->sole();
        $this->put(route('buyer.addresses.update', $home), $this->address())->assertRedirect();
        $this->assertTrue($home->fresh()->is_default);
        $this->assertSame('20 Work Street', $buyer->fresh()->street_address);
        $this->patch(route('buyer.addresses.default', $home))->assertRedirect();
        $this->patch(route('buyer.addresses.default', $home))->assertRedirect();
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());

        $this->post(route('buyer.addresses.store'), $this->address(['label' => 'Second address', 'is_default' => true]))->assertRedirect();
        $this->assertFalse($home->fresh()->is_default);
        $this->assertSame('Second address', $buyer->addresses()->where('is_default', true)->sole()->label);
    }

    public function test_another_buyers_address_cannot_be_read_edited_removed_or_made_default(): void
    {
        $owner = $this->user();
        $address = $owner->addresses()->create($this->storedAddress());
        $buyer = $this->user();
        $this->actingAs($buyer)->get(route('buyer.addresses.edit', $address))->assertNotFound();
        $this->put(route('buyer.addresses.update', $address), $this->address())->assertNotFound();
        $this->delete(route('buyer.addresses.destroy', $address))->assertNotFound();
        $this->patch(route('buyer.addresses.default', $address))->assertNotFound();
        $this->assertSame('20 Work Street', $address->fresh()->street_address);
    }

    public function test_new_addresses_require_complete_location_and_valid_mobile_number(): void
    {
        $this->actingAs($this->user())->post(route('buyer.addresses.store'), [])->assertSessionHasErrors(['label', 'recipient_name', 'recipient_contact', 'address_mode']);
        $this->post(route('buyer.addresses.store'), $this->address(['recipient_contact' => '1234', 'manual_municipality' => '']))->assertSessionHasErrors(['recipient_contact', 'manual_municipality']);
        $this->assertDatabaseCount('buyer_addresses', 0);
    }

    #[TestWith(['seller'])]
    #[TestWith(['courier'])]
    #[TestWith(['sorting_center'])]
    #[TestWith(['admin'])]
    #[TestWith(['ecommerce_admin'])]
    #[TestWith(['logistics_admin'])]
    public function test_address_book_is_buyer_only(string $role): void
    {
        $this->actingAs($this->user($role))->get(route('buyer.addresses.index'))->assertForbidden();
        $this->post(route('buyer.addresses.store'), $this->address())->assertForbidden();
    }

    public function test_guests_must_sign_in_to_manage_addresses(): void
    {
        $this->get(route('buyer.addresses.index'))->assertRedirect(route('login'));
        $this->post(route('buyer.addresses.store'), $this->address())->assertRedirect(route('login'));
    }

    public function test_checkout_uses_selected_address_and_orders_keep_the_original_snapshot(): void
    {
        $buyer = $this->user();
        $address = $buyer->addresses()->create($this->storedAddress());
        $cart = $this->cart();
        $this->actingAs($buyer)->withSession(['cart' => $cart])->get(route('checkout.index'))->assertOk()->assertViewHas('selectedAddress', fn ($selected) => $selected->id === $address->id);
        $this->get(route('checkout.index', ['address_id' => 'new']))->assertOk()->assertViewHas('selectedAddress', null);
        $this->get(route('checkout.index', ['address_id' => $address->id]))->assertOk()->assertSee('Work');
        $payload = [
            'saved_address_id' => $address->id, 'checkout_token' => session('checkout_token'),
            'quote_hash' => session('checkout_quote_hash'), 'payment_method' => 'COD',
            'recipient_name' => 'Forged Recipient', 'street_address' => 'Forged Street',
        ];
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'));
        $order = Order::sole();
        $this->assertSame('Office Recipient', $order->recipient_name);
        $this->assertSame('20 Work Street', $order->street_address);
        $this->put(route('buyer.addresses.update', $address), $this->address(['street_address' => '99 Changed Street']))->assertRedirect();
        $this->delete(route('buyer.addresses.destroy', $address))->assertRedirect();
        $this->post(route('checkout.process'), $payload)->assertRedirect(route('buyer.dashboard'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('20 Work Street', $order->fresh()->street_address);
    }

    public function test_checkout_rejects_another_buyers_saved_address_without_placing_an_order(): void
    {
        $address = $this->user()->addresses()->create($this->storedAddress());
        $buyer = $this->user();
        $this->actingAs($buyer)->withSession(['cart' => $this->cart()])->get(route('checkout.index'))->assertOk();
        $this->get(route('checkout.index', ['address_id' => $address->id]))->assertNotFound();
        $this->post(route('checkout.process'), [
            'saved_address_id' => $address->id, 'checkout_token' => session('checkout_token'),
            'quote_hash' => session('checkout_quote_hash'), 'payment_method' => 'COD',
        ])->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, Product::sole()->stock);
    }

    private function user(string $role = 'buyer'): User
    {
        return User::forceCreate([
            'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => $role,
            'status' => 'approved', 'first_name' => 'Test', 'last_name' => 'Buyer',
            'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay',
            'barangay' => 'Poblacion', 'street_address' => '1 Original Street',
        ]);
    }

    private function address(array $overrides = []): array
    {
        return array_replace([
            'label' => 'Work', 'recipient_name' => 'Office Recipient', 'recipient_contact' => '+639123456789',
            'address_mode' => 'manual', 'manual_region' => 'CALABARZON', 'manual_province' => 'Laguna',
            'manual_municipality' => 'Majayjay', 'manual_barangay' => 'Poblacion',
            'house_number' => '20', 'street_address' => '20 Work Street', 'postal_code' => '4005',
        ], $overrides);
    }

    private function storedAddress(): array
    {
        return [
            ...$this->address(), 'region' => 'CALABARZON', 'province' => 'Laguna',
            'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'is_default' => true,
        ];
    }

    private function cart(): array
    {
        $product = Product::forceCreate(['user_id' => $this->user('seller')->id, 'name' => 'Address test product', 'category' => 'Test', 'price' => 100, 'stock' => 5, 'is_archived' => false, 'compliance_status' => 'approved']);

        return [['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null, 'price' => 100, 'name' => $product->name]];
    }
}
