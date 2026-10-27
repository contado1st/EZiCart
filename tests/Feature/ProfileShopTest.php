<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_buyer_can_customize_their_profile_and_images_are_resized_without_changing_identity(): void
    {
        Storage::fake('private');
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->patch(route('account.appearance.update'), [
            ...$this->appearance(), 'avatar' => UploadedFile::fake()->image('avatar.png', 200, 300),
            'banner' => UploadedFile::fake()->image('banner.png', 1200, 400),
            'role' => 'admin', 'status' => 'suspended', 'profile_avatar_path' => 'documents/identity.jpg',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $buyer->refresh();
        $this->assertSame('My profile', $buyer->profile_display_name);
        $this->assertSame('teal', $buyer->profile_theme);
        $this->assertSame('buyer', $buyer->role);
        $this->assertSame('approved', $buyer->status);
        Storage::disk('private')->assertExists($buyer->profile_avatar_path);
        $avatar = getimagesizefromstring(Storage::disk('private')->get($buyer->profile_avatar_path));
        $banner = getimagesizefromstring(Storage::disk('private')->get($buyer->profile_banner_path));
        $this->assertSame([400, 400], [$avatar[0], $avatar[1]]);
        $this->assertSame([1600, 500], [$banner[0], $banner[1]]);
        $this->assertSame('image/jpeg', $avatar['mime']);
        $this->get(route('account.profile.edit'))->assertOk()->assertSee('Save appearance')->assertSee('My profile');
    }

    public function test_appearance_update_is_own_account_only_and_guests_cannot_upload(): void
    {
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $this->patch(route('account.appearance.update'), $this->appearance())->assertRedirectToRoute('login');
        $this->actingAs($buyer)->patch(route('account.appearance.update'), [...$this->appearance(), 'user_id' => $other->id])->assertRedirect();
        $this->assertNull($other->fresh()->profile_display_name);
        $this->assertSame('My profile', $buyer->fresh()->profile_display_name);
    }

    public function test_private_buyer_media_and_unapproved_sellers_are_not_public(): void
    {
        Storage::fake('private');
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $path = 'profile-media/test/avatar.jpg';
        Storage::disk('private')->put($path, 'image data');
        $buyer->forceFill(['profile_avatar_path' => $path])->save();
        $seller->forceFill(['profile_avatar_path' => $path])->save();
        $this->get(route('profiles.media', [$buyer, 'avatar']))->assertNotFound();
        $this->get(route('shops.show', $buyer))->assertNotFound();
        $this->actingAs($this->user('buyer'))->get(route('profiles.media', [$buyer, 'avatar']))->assertNotFound();
        $this->actingAs($buyer)->get(route('profiles.media', [$buyer, 'avatar']))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('profiles.media', [$buyer, 'identity']))->assertNotFound();
        $this->get(route('profiles.media', [$seller, 'avatar']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $seller->forceFill(['status' => 'suspended'])->save();
        $this->get(route('profiles.media', [$seller, 'avatar']))->assertNotFound();
        $this->get(route('shops.show', $seller))->assertNotFound();
    }

    public function test_upload_validation_rejects_scripts_invalid_dimensions_oversized_files_and_unknown_colors(): void
    {
        Storage::fake('private');
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->patchJson(route('account.appearance.update'), [
            ...$this->appearance(), 'profile_theme' => 'evil-css', 'avatar' => UploadedFile::fake()->image('small.png', 20, 20),
            'banner' => UploadedFile::fake()->image('small-banner.png', 100, 100),
        ])->assertJsonValidationErrors(['profile_theme', 'avatar', 'banner']);
        $this->patchJson(route('account.appearance.update'), [...$this->appearance(), 'avatar' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertJsonValidationErrors('avatar');
        $this->patchJson(route('account.appearance.update'), [...$this->appearance(), 'avatar' => UploadedFile::fake()->image('huge.png', 100, 100)->size(3000)])->assertJsonValidationErrors('avatar');
        $this->patchJson(route('account.appearance.update'), [...$this->appearance(), 'banner' => UploadedFile::fake()->image('huge-banner.png', 1200, 400)->size(3000)])->assertJsonValidationErrors('banner');
        $this->patchJson(route('account.appearance.update'), [...$this->appearance(), 'remove_avatar' => 1, 'avatar' => UploadedFile::fake()->image('avatar.png', 100, 100)])->assertJsonValidationErrors('avatar');
        $this->assertNull($buyer->fresh()->profile_avatar_path);
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_public_shop_contains_only_its_available_products_and_escaped_branding_not_private_details(): void
    {
        $seller = $this->user('seller');
        $other = $this->user('seller');
        $seller->forceFill(['profile_bio' => '<script>alert(1)</script> Our shop story'])->save();
        $visible = $this->product($seller, 'Visible cotton tote');
        $this->product($other, 'Other seller private listing');
        $this->product($seller, 'Flagged hidden', ['compliance_status' => 'flagged']);
        $this->product($seller, 'Pending hidden', ['compliance_status' => 'pending_review']);
        $this->product($seller, 'Archived hidden', ['is_archived' => true]);
        $this->product($seller, 'Sold out hidden', ['stock' => 0]);
        $buyer = $this->user('buyer');
        $order = Order::forceCreate(['order_number' => 'PROFILE-'.Str::random(8), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Test', 'recipient_contact' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => 'Test', 'subtotal' => 100, 'total_amount' => 150, 'payment_method' => 'COD', 'status' => 'COMPLETED']);
        Review::create(['order_id' => $order->id, 'product_id' => $visible->id, 'buyer_id' => $buyer->id, 'rating' => 4]);
        $this->get(route('shops.show', $seller))->assertOk()->assertSee('Visible cotton tote')
            ->assertSee('4.0 / 5')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Other seller private listing')->assertDontSee('Flagged hidden')->assertDontSee('Pending hidden')
            ->assertDontSee('Archived hidden')->assertDontSee('Sold out hidden')->assertDontSee($seller->email)
            ->assertDontSee('PRIVATE STREET')->assertDontSee('09129999999');
        $this->get(route('product.show', $visible))->assertSee(route('shops.show', $seller));
    }

    public function test_shop_search_sorting_and_category_filters_are_scoped_and_escape_wildcards(): void
    {
        $seller = $this->user('seller');
        $cheap = $this->product($seller, '100% Cotton', ['price' => 50]);
        $dear = $this->product($seller, 'Leather bag', ['price' => 500, 'category' => 'Bags']);
        $this->get(route('shops.show', [$seller, 'q' => '%']))->assertOk()->assertViewHas('products', fn ($p) => $p->count() === 1 && $p->first()->id === $cheap->id);
        $this->get(route('shops.show', [$seller, 'sort' => 'price_desc']))->assertOk()->assertViewHas('products', fn ($p) => $p->first()->id === $dear->id);
        $this->get(route('shops.show', [$seller, 'category' => 'Bags']))->assertOk()->assertViewHas('products', fn ($p) => $p->count() === 1 && $p->first()->id === $dear->id);
        $this->getJson(route('shops.show', [$seller, 'sort' => 'unsafe']))->assertJsonValidationErrors('sort');
    }

    public function test_follow_and_unfollow_are_idempotent_buyer_only_and_appear_on_the_private_profile(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $other = $this->user('buyer');
        $this->post(route('shops.follow', $seller), ['following' => 1])->assertRedirectToRoute('login');
        $this->signIn($seller)->post(route('shops.follow', $seller), ['following' => 1])->assertForbidden();
        $this->signIn($buyer)->post(route('shops.follow', $seller), ['following' => 1])->assertRedirect()->assertSessionHas('success', 'You are now following this shop.');
        $this->post(route('shops.follow', $seller), ['following' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shop_follows', 1);
        $this->get(route('account.profile.edit'))->assertSee('Shops you follow')->assertSee($seller->business_name);
        $this->signIn($other)->post(route('shops.follow', $seller), ['following' => 0, 'buyer_id' => $buyer->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shop_follows', 1);
        $this->signIn($buyer)->post(route('shops.follow', $seller), ['following' => 0])->assertRedirect()->assertSessionHas('success', 'You have unfollowed this shop.');
        $this->assertDatabaseCount('shop_follows', 0);
    }

    public function test_owner_can_remove_uploaded_images(): void
    {
        Storage::fake('private');
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->patch(route('account.appearance.update'), [...$this->appearance(), 'avatar' => UploadedFile::fake()->image('photo.png', 100, 100)])->assertRedirect();
        $old = $buyer->fresh()->profile_avatar_path;
        $this->patch(route('account.appearance.update'), [...$this->appearance(), 'avatar' => UploadedFile::fake()->image('replacement.png', 200, 200)])->assertRedirect()->assertSessionHasNoErrors();
        $replacement = $buyer->fresh()->profile_avatar_path;
        $this->assertNotSame($old, $replacement);
        Storage::disk('private')->assertMissing($old);
        $this->patch(route('account.appearance.update'), [...$this->appearance(), 'remove_avatar' => 1])->assertRedirect();
        $this->assertNull($buyer->fresh()->profile_avatar_path);
        Storage::disk('private')->assertMissing($replacement);
    }

    public function test_followed_shops_paginate_and_are_private_to_the_current_buyer(): void
    {
        $buyer = $this->user('buyer');
        for ($index = 0; $index < 7; $index++) {
            $buyer->followedShops()->attach($this->user('seller')->id);
        }
        $this->signIn($buyer)->get(route('account.profile.edit'))->assertOk()
            ->assertViewHas('followedShops', fn ($shops) => $shops->total() === 7 && $shops->count() === 6);
        $this->get(route('account.profile.edit', ['shops_page' => 2]))->assertOk()
            ->assertViewHas('followedShops', fn ($shops) => $shops->count() === 1);
        $this->signIn($this->user('buyer'))->get(route('account.profile.edit'))->assertOk()
            ->assertViewHas('followedShops', fn ($shops) => $shops->total() === 0);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'password', 'role' => $role, 'status' => 'approved', 'first_name' => 'Profile', 'last_name' => 'User', 'business_name' => 'Shop '.Str::random(6), 'contact_no' => '09129999999', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Roque', 'street_address' => 'PRIVATE STREET']);
    }

    private function product(User $seller, string $name, array $data = []): Product
    {
        return Product::forceCreate(array_replace(['user_id' => $seller->id, 'name' => $name, 'description' => 'Sample product', 'category' => 'Fashion', 'price' => 100, 'stock' => 3, 'is_archived' => false, 'compliance_status' => 'approved'], $data));
    }

    private function appearance(): array
    {
        return ['profile_display_name' => 'My profile', 'profile_bio' => 'My introduction', 'profile_theme' => 'teal'];
    }

    private function signIn(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }
}
