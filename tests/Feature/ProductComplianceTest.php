<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_new_products_need_admin_review_and_flagged_products_cannot_be_bought(): void
    {
        $seller = $this->user('seller');
        $product = $this->actingAsUser($seller)->post(route('seller.products.store'), [
            'name' => 'Unreviewed tea',
            'category' => 'Groceries',
            'description' => 'A locally packed tea.',
            'price' => 80,
            'stock' => 10,
            'compliance_status' => 'approved',
        ])->assertRedirect(route('seller.products.index'));

        $product = Product::query()->where('name', 'Unreviewed tea')->firstOrFail();
        $this->assertSame('pending_review', $product->compliance_status);
        $this->assertDatabaseHas('product_compliance_events', [
            'product_id' => $product->id,
            'actor_id' => $seller->id,
            'action' => 'submitted',
            'new_status' => 'pending_review',
        ]);
        $this->get(route('home'))->assertOk()->assertDontSee('Unreviewed tea');
        $this->get(route('product.show', $product))->assertNotFound();
        $this->actingAsUser($seller)->patch(route('admin.compliance.products.review', $product), [
            'compliance_status' => 'approved',
        ])->assertForbidden();

        $buyer = $this->user('buyer');
        $this->actingAsUser($buyer)->post(route('cart.add', $product), ['quantity' => 1])->assertNotFound();

        $admin = $this->user('admin');
        $this->actingAsUser($admin)->get(route('admin.compliance.products.index'))->assertOk()->assertSee('Unreviewed tea');
        $this->patch(route('admin.compliance.products.review', $product), [
            'compliance_status' => 'flagged',
            'compliance_note' => 'The packaging claim needs documentation.',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'compliance_status' => 'flagged']);
        $this->assertDatabaseHas('product_compliance_events', ['product_id' => $product->id, 'action' => 'flagged', 'actor_id' => $admin->id]);
        $this->assertSame('flagged', $seller->notifications()->firstOrFail()->data['status']);
        $this->actingAsUser($seller)->get(route('seller.products.index'))->assertOk()->assertSee('Flagged')->assertSee('The packaging claim needs documentation.');
        $this->get(route('product.show', $product))->assertNotFound();

        $checkoutCart = [
            (string) $product->id => [
                'product_id' => $product->id,
                'variation_id' => null,
                'name' => $product->name,
                'price' => 80,
                'quantity' => 1,
                'seller_id' => $seller->id,
            ],
        ];
        $this->actingAsUser($buyer)->withSession(['cart' => $checkoutCart])->post(route('checkout.process'), [
            'recipient_name' => 'Test Buyer',
            'recipient_contact' => '09123456789',
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Buyer Street',
            'payment_method' => 'GCash',
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAsUser($admin)->patch(route('admin.compliance.products.review', $product), [
            'compliance_status' => 'approved',
            'compliance_note' => 'Category and listing details reviewed.',
        ])->assertRedirect();
        $this->get(route('product.show', $product))->assertOk()->assertSee('Unreviewed tea');
        $this->actingAsUser($buyer)->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect(route('cart.index'));
    }

    public function test_seller_changes_to_reviewed_listing_require_review_and_audit_history_is_preserved(): void
    {
        $seller = $this->user('seller');
        $admin = $this->user('admin');
        $product = Product::query()->forceCreate([
            'user_id' => $seller->id,
            'name' => 'Reviewed product',
            'description' => 'Reviewed description',
            'category' => 'Home & Living',
            'price' => 100,
            'stock' => 4,
            'compliance_status' => 'approved',
            'is_archived' => false,
        ]);

        $this->actingAsUser($admin)->patch(route('admin.compliance.products.review', $product), [
            'compliance_status' => 'approved',
            'compliance_note' => 'Initial review completed.',
        ])->assertRedirect();

        $this->actingAsUser($seller)->put(route('seller.products.update', $product), [
            'name' => 'Reviewed product',
            'description' => 'Changed product details.',
            'category' => 'Electronics',
            'price' => 100,
            'stock' => 4,
            'compliance_status' => 'approved',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'compliance_status' => 'pending_review', 'category' => 'Electronics']);
        $this->assertDatabaseHas('product_compliance_events', ['product_id' => $product->id, 'action' => 'resubmitted', 'previous_status' => 'approved']);

        $this->delete(route('seller.products.destroy', $product))->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_archived' => true]);
        $this->assertDatabaseHas('product_compliance_events', ['product_id' => $product->id, 'action' => 'resubmitted']);
    }

    private function actingAsUser(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::query()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
            'status' => 'approved',
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
        ]);
    }
}
