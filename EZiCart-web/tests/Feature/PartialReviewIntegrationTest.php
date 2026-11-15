<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PartialReviewIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_completed_buyer_can_publish_photos_with_masked_identity_and_all_purchased_variants(): void
    {
        Storage::fake('private');
        [$buyer, $seller, $product, $order] = $this->purchase();
        $this->actingAs($buyer)->post(route('buyer.orders.review', $order), ['product_id' => $product->id, 'rating' => 4, 'comment' => '<b>Honest feedback</b>',
            'purchase_context' => ['variants' => ['FORGED']], 'photos' => [UploadedFile::fake()->image('receipt.png'), UploadedFile::fake()->image('detail.jpg')->size(5120)]])
            ->assertRedirect()->assertSessionHas('success', 'Thank you! Your review and rating have been posted.');
        $review = Review::firstOrFail();
        $this->assertSame(['Red / Small', 'Blue / Large'], $review->purchase_context['variants']);
        $this->assertSame(1, $review->revision_version);
        $this->assertDatabaseCount('review_photos', 2);
        $this->assertDatabaseCount('review_revisions', 1);
        Storage::disk('private')->assertExists($review->photos->first()->path);
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('product.show', [$product, 'with_media' => 1]))->assertOk()->assertSee('D***r')->assertDontSee('DiscoveryPrivate')
            ->assertSee('Red / Small')->assertSee('Blue / Large')->assertSee('<b>Honest feedback</b>')->assertDontSee('<b>Honest feedback</b>', false);
        $this->get(route('reviews.photos.show', [$review, $review->photos->first()]))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertArrayNotHasKey('purchase_context', $review->toArray());
        $this->assertArrayNotHasKey('path', $review->photos->first()->toArray());
    }

    public function test_wrong_buyer_unpurchased_product_and_incomplete_order_cannot_publish(): void
    {
        Storage::fake('private');
        [$buyer, $seller, $product, $order] = $this->purchase();
        $payload = ['product_id' => $product->id, 'rating' => 5];
        $this->actingAs($this->user('buyer'))->post(route('buyer.orders.review', $order), $payload)->assertForbidden();
        $this->flushSession();
        $order->forceFill(['status' => 'DELIVERED'])->save();
        $this->actingAs($buyer)->post(route('buyer.orders.review', $order), $payload)->assertSessionHas('error', 'You can only review products from completed orders.');
        $order->forceFill(['status' => 'COMPLETED'])->save();
        $other = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Not purchased', 'category' => 'Home and Garden', 'price' => 10, 'stock' => 1]);
        $this->post(route('buyer.orders.review', $order), [...$payload, 'product_id' => $other->id])->assertSessionHas('error', 'This product is not part of this order.');
        $this->assertDatabaseCount('reviews', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    #[TestWith(['svg'])]
    #[TestWith(['disguised'])]
    #[TestWith(['oversize'])]
    #[TestWith(['dimensions'])]
    #[TestWith(['six'])]
    public function test_invalid_review_uploads_do_not_leave_records_or_files(string $case): void
    {
        Storage::fake('private');
        [$buyer, , $product, $order] = $this->purchase();
        $photos = match ($case) {
            'svg' => [UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
            'disguised' => [UploadedFile::fake()->createWithContent('image.jpg', '<script>bad</script>')],
            'oversize' => [UploadedFile::fake()->image('big.jpg')->size(5121)],
            'dimensions' => [UploadedFile::fake()->image('wide.jpg', 4097, 1)],
            default => array_map(fn ($n) => UploadedFile::fake()->image('photo'.$n.'.png'), range(1, 6)),
        };
        $this->actingAs($buyer)->post(route('buyer.orders.review', $order), ['product_id' => $product->id, 'rating' => 5, 'photos' => $photos])
            ->assertSessionHasErrors($case === 'six' ? 'photos' : 'photos.0');
        $this->assertDatabaseCount('reviews', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_edits_keep_private_history_remove_public_photos_and_reject_stale_version_with_cleanup(): void
    {
        Storage::fake('private');
        [$buyer, $seller, $product, $order] = $this->purchase();
        $this->publish($buyer, $product, $order, [UploadedFile::fake()->image('before.png')]);
        $review = Review::firstOrFail();
        $oldPhoto = $review->photos->first();
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 2, 'comment' => 'Updated review', 'remove_photos' => [$oldPhoto->id], 'photos' => [UploadedFile::fake()->image('after.jpg')]])
            ->assertRedirectToRoute('buyer.reviews.edit', $review);
        $this->assertSame(2, $review->fresh()->revision_version);
        $this->assertSame([2, 5], $review->revisions->pluck('snapshot.rating')->all());
        $this->assertSame(1, $review->fresh()->photos()->count());
        $this->get(route('buyer.reviews.edit', $review))->assertOk()->assertSee('Private revision history')->assertSee('Original feedback');
        $this->get(route('reviews.photos.show', [$review, $oldPhoto]))->assertOk();
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 1, 'photos' => [UploadedFile::fake()->image('stale.png')]])->assertSessionHasErrors('version');
        $this->assertCount(2, Storage::disk('private')->allFiles());
        $this->assertSame(2, $review->fresh()->rating);
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('reviews.photos.show', [$review, $oldPhoto]))->assertNotFound();
        $this->get(route('product.show', $product))->assertOk()->assertViewHas('averageRating', 2.0)->assertSee('Updated review')->assertDontSee('Original feedback');
        $this->actingAs($seller)->get(route('reviews.photos.show', [$review, $oldPhoto]))->assertNotFound();
    }

    public function test_other_buyers_and_sellers_cannot_edit_or_open_private_revision_history(): void
    {
        Storage::fake('private');
        [$buyer, $seller, $product, $order] = $this->purchase();
        $this->publish($buyer, $product, $order);
        $review = Review::firstOrFail();
        $this->flushSession();
        $this->actingAs($this->user('buyer'))->get(route('buyer.reviews.edit', $review))->assertNotFound();
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 1])->assertNotFound();
        $this->flushSession();
        $this->actingAs($seller)->get(route('buyer.reviews.edit', $review))->assertForbidden();
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 1])->assertForbidden();
        $this->assertSame(5, $review->fresh()->rating);
    }

    public function test_total_photo_limit_and_foreign_removal_fail_without_changing_existing_review(): void
    {
        Storage::fake('private');
        [$buyer, , $product, $order] = $this->purchase();
        $this->publish($buyer, $product, $order, array_map(fn ($n) => UploadedFile::fake()->image('saved'.$n.'.png'), range(1, 5)));
        $review = Review::firstOrFail();
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 4, 'photos' => [UploadedFile::fake()->image('sixth.png')]])->assertSessionHasErrors('photos');
        $this->assertCount(5, Storage::disk('private')->allFiles());
        $this->assertSame(1, $review->fresh()->revision_version);
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 4, 'remove_photos' => [999999]])->assertSessionHasErrors('remove_photos.0');
        $this->assertDatabaseCount('review_revisions', 1);
    }

    public function test_legacy_review_edit_records_original_state_and_purchased_options_without_recreating_history(): void
    {
        Storage::fake('private');
        [$buyer, , $product, $order] = $this->purchase();
        $review = Review::create(['order_id' => $order->id, 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'rating' => 3, 'comment' => 'Legacy review']);
        $this->actingAs($buyer)->get(route('buyer.reviews.edit', $review))->assertOk()->assertSee('Red / Small')->assertSee('Blue / Large');
        $this->patch(route('buyer.reviews.update', $review), ['version' => 1, 'rating' => 4, 'comment' => 'Legacy updated'])->assertRedirect();
        $this->assertSame(['Legacy updated', 'Legacy review'], $review->revisions->pluck('snapshot.comment')->all());
        $this->assertDatabaseCount('reviews', 1);
        $order->forceFill(['status' => 'RETURNED_TO_SELLER'])->save();
        $this->get(route('buyer.orders.show', $order))->assertOk()->assertSee(route('buyer.reviews.edit', $review), false);
        $this->get(route('buyer.reviews.edit', $review))->assertOk();
    }

    public function test_seller_filters_are_scoped_and_archived_product_reviews_remain_readable(): void
    {
        Storage::fake('private');
        [$buyer, $seller, $product, $order] = $this->purchase();
        $this->publish($buyer, $product, $order, [UploadedFile::fake()->image('feedback.png')]);
        $review = Review::firstOrFail();
        $product->forceFill(['is_archived' => true])->save();
        [$otherBuyer, , $otherProduct, $otherOrder] = $this->purchase();
        $this->flushSession();
        $this->publish($otherBuyer, $otherProduct, $otherOrder);
        $this->flushSession();
        $this->actingAs($seller)->get(route('seller.reviews.index', ['rating' => 5, 'media' => 'with', 'text' => 'with', 'from_date' => today()->toDateString(), 'to_date' => today()->toDateString()]))->assertOk()
            ->assertViewHas('reviews', fn ($reviews) => $reviews->total() === 1 && $reviews->first()->id === $review->id)->assertDontSee($buyer->first_name);
        $this->get(route('reviews.photos.show', [$review, $review->photos->first()]))->assertOk();
        $this->get(route('seller.reviews.index', ['media' => 'without']))->assertOk()->assertViewHas('reviews', fn ($reviews) => $reviews->total() === 0);
        $this->getJson(route('seller.reviews.index', ['rating' => 6]))->assertJsonValidationErrors('rating');
        $this->getJson(route('seller.reviews.index', ['from_date' => '2026-11-14', 'to_date' => '2026-01-01']))->assertJsonValidationErrors('to_date');
    }

    public function test_one_review_identity_is_preserved_on_repeat_create_and_database_duplicate(): void
    {
        Storage::fake('private');
        [$buyer, , $product, $order] = $this->purchase();
        $this->publish($buyer, $product, $order);
        $this->post(route('buyer.orders.review', $order), ['product_id' => $product->id, 'rating' => 1, 'photos' => [UploadedFile::fake()->image('duplicate.png')]])
            ->assertSessionHas('error', 'You have already reviewed this product for this order.');
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->expectException(UniqueConstraintViolationException::class);
        Review::create(['order_id' => $order->id, 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'rating' => 1]);
    }

    public function test_seller_review_pagination_shows_twenty_five_and_retains_filters(): void
    {
        [$buyer, $seller, $product, $order] = $this->purchase();
        for ($i = 0; $i < 26; $i++) {
            $copy = $order->replicate(['parcel_code']);
            $copy->order_number = 'PAGE-'.Str::random(12);
            $copy->save();
            foreach ($order->items as $item) {
                $copy->items()->create($item->only(['product_id', 'product_name', 'variation_info', 'quantity', 'unit_price', 'item_total']));
            }
            Review::create(['order_id' => $copy->id, 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'rating' => 5]);
        }
        $response = $this->actingAs($seller)->get(route('seller.reviews.index', ['rating' => 5, 'page' => 2]))->assertOk();
        $this->assertSame(26, $response->viewData('reviews')->total());
        $this->assertCount(1, $response->viewData('reviews'));
        $response->assertSee('rating=5', false);
    }

    public function test_shared_laravel_api_creates_one_review_with_history_and_rejects_silent_overwrite(): void
    {
        [$buyer, , $product, $order] = $this->purchase();
        $buyer->forceFill(['email_verified_at' => now()])->save();
        $this->withToken(ApiToken::createTokenForUser($buyer)['plainTextToken'])->postJson('/api/orders/'.$order->id.'/review', ['product_id' => $product->id, 'rating' => 4, 'comment' => 'API original'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('review.buyer_id', $buyer->id)->assertJsonMissingPath('review.purchase_context');
        $this->postJson('/api/orders/'.$order->id.'/review', ['product_id' => $product->id, 'rating' => 1])->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->assertSame(4, Review::firstOrFail()->rating);
        $this->assertDatabaseCount('review_revisions', 1);
    }

    public function test_shared_laravel_api_refuses_foreign_unfinished_and_unpurchased_review_targets(): void
    {
        [$buyer, , $product, $order] = $this->purchase();
        $other = $this->user('buyer');
        $other->forceFill(['email_verified_at' => now()])->save();
        $buyer->forceFill(['email_verified_at' => now()])->save();
        $payload = ['product_id' => $product->id, 'rating' => 5];
        $this->withToken(ApiToken::createTokenForUser($other)['plainTextToken'])->postJson('/api/orders/'.$order->id.'/review', $payload)->assertNotFound();
        $order->forceFill(['status' => 'DELIVERED'])->save();
        $this->withToken(ApiToken::createTokenForUser($buyer)['plainTextToken'])->postJson('/api/orders/'.$order->id.'/review', $payload)->assertUnprocessable();
        $order->forceFill(['status' => 'COMPLETED'])->save();
        $unpurchased = Product::forceCreate(['user_id' => $product->user_id, 'name' => 'Unpurchased API item', 'category' => 'Pet Supplies', 'price' => 10, 'stock' => 1]);
        $this->postJson('/api/orders/'.$order->id.'/review', [...$payload, 'product_id' => $unpurchased->id])->assertUnprocessable();
        $this->assertDatabaseCount('reviews', 0);
    }

    private function publish(User $buyer, Product $product, Order $order, array $photos = []): void
    {
        $this->actingAs($buyer)->post(route('buyer.orders.review', $order), ['product_id' => $product->id, 'rating' => 5, 'comment' => 'Original feedback', 'photos' => $photos])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => $role === 'buyer' ? 'DiscoveryPrivate' : 'Merchant', 'last_name' => 'Tester', 'contact_no' => '09123456789']);
    }

    private function purchase(): array
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Reviewed product', 'category' => 'Home and Garden', 'price' => 100, 'stock' => 10, 'is_archived' => false, 'compliance_status' => 'approved']);
        $order = Order::forceCreate(['order_number' => 'REVIEW-'.Str::random(10), 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'recipient_name' => 'Review Buyer', 'recipient_contact' => '09123456789',
            'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'Poblacion', 'street_address' => 'Synthetic test address', 'subtotal' => 200, 'shipping_fee' => 50, 'total_amount' => 250, 'payment_method' => 'COD', 'status' => 'COMPLETED']);
        foreach (['Red / Small', 'Blue / Large'] as $variant) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Purchased product name', 'variation_info' => $variant, 'quantity' => 1, 'unit_price' => 100, 'item_total' => 100]);
        }

        return [$buyer, $seller, $product, $order];
    }
}
