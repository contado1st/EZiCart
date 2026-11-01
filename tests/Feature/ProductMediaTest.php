<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_seller_uploads_gallery_and_video_and_admin_can_review_them_before_publication(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller');
        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()->assertSee('Gallery images');
        $this->actingAs($seller)->post(route('seller.products.store'), [...$this->payload(),
            'image' => UploadedFile::fake()->image('cover.jpg'),
            'gallery_images' => [UploadedFile::fake()->image('side.png'), UploadedFile::fake()->image('detail.jpg')],
            'product_video' => UploadedFile::fake()->create('product.mp4', 100, 'video/mp4'),
            'user_id' => $this->user('seller')->id, 'compliance_status' => 'approved',
        ])->assertRedirectToRoute('seller.products.index')->assertSessionHasNoErrors();

        $product = Product::where('name', 'Media test product')->firstOrFail();
        $this->assertSame($seller->id, $product->user_id);
        $this->assertSame('pending_review', $product->compliance_status);
        $this->assertSame(['image', 'image', 'video'], $product->media->pluck('kind')->all());
        Storage::disk('public')->assertExists([$product->image_path, ...$product->media->pluck('path')->all()]);
        $this->get(route('product.show', $product))->assertNotFound();
        $this->flushSession();
        $this->actingAs($this->user('admin'))->get(route('admin.compliance.products.index'))->assertOk()
            ->assertSee('Review photos &amp; video', false)
            ->assertSee(asset('storage/'.$product->media->last()->path), false);
        $product->forceFill(['compliance_status' => 'approved'])->save();
        $this->get(route('product.show', $product))->assertOk()->assertSee('data-product-gallery', false)
            ->assertSee('video/mp4', false)->assertSee('Next product media');
    }

    public function test_gallery_only_upload_promotes_first_photo_to_cover_without_duplicate_slide(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user('seller'))->post(route('seller.products.store'), [...$this->payload(),
            'gallery_images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
        ])->assertSessionHasNoErrors();
        $product = Product::where('name', 'Media test product')->firstOrFail();
        $this->assertNotNull($product->image_path);
        $this->assertSame(1, $product->media()->count());
        $this->assertCount(2, Storage::disk('public')->allFiles('products'));
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('Product photos &amp; video', false);
    }

    public function test_replacing_video_preserves_photos_and_requires_compliance_review_again(): void
    {
        Storage::fake('public');
        $product = $this->product($this->user('seller'));
        $photo = $this->savedMedia($product, 'image', 'products/media/saved.jpg');
        $oldVideo = $this->savedMedia($product, 'video', 'products/media/old.mp4');
        $this->actingAs($product->seller)->put(route('seller.products.update', $product), [...$this->payload(),
            'product_video' => UploadedFile::fake()->create('new.webm', 100, 'video/webm'),
        ])->assertRedirectToRoute('seller.products.index')->assertSessionHasNoErrors();

        $this->assertSame('pending_review', $product->fresh()->compliance_status);
        $this->assertDatabaseHas('product_media', ['id' => $photo->id, 'path' => $photo->path]);
        $this->assertDatabaseMissing('product_media', ['id' => $oldVideo->id]);
        $this->assertSame(1, $product->media()->where('kind', 'video')->count());
        Storage::disk('public')->assertMissing($oldVideo->path);
        Storage::disk('public')->assertExists([$photo->path, $product->image_path, $product->media()->where('kind', 'video')->firstOrFail()->path]);
        $this->assertDatabaseHas('product_compliance_events', ['product_id' => $product->id, 'action' => 'resubmitted']);
        $this->assertDatabaseHas('administrative_audits', ['entity_id' => $product->id, 'action' => 'seller_catalog_updated']);
    }

    public function test_adding_a_single_gallery_photo_to_an_imageless_reviewed_product_requires_review(): void
    {
        Storage::fake('public');
        $product = $this->product($this->user('seller'));
        $product->update(['image_path' => null]);
        $this->actingAs($product->seller)->put(route('seller.products.update', $product), [...$this->payload(),
            'gallery_images' => [UploadedFile::fake()->image('new-cover.jpg')],
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($product->fresh()->image_path);
        $this->assertSame('pending_review', $product->fresh()->compliance_status);
        $this->assertSame(0, $product->media()->count());
    }

    public function test_owner_can_remove_saved_media_and_cover_and_stock_only_updates_preserve_media(): void
    {
        Storage::fake('public');
        $product = $this->product($this->user('seller'));
        $photo = $this->savedMedia($product, 'image', 'products/media/saved.jpg');
        $this->actingAs($product->seller)->put(route('seller.products.update', $product), [...$this->payload(), 'stock' => 9])->assertSessionHasNoErrors();
        $this->assertSame('approved', $product->fresh()->compliance_status);
        Storage::disk('public')->assertExists([$photo->path, $product->image_path]);
        $oldCover = $product->image_path;
        $this->put(route('seller.products.update', $product), [...$this->payload(), 'remove_media' => [$photo->id], 'remove_cover' => 1])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->image_path);
        $this->assertSame(0, $product->media()->count());
        Storage::disk('public')->assertMissing([$photo->path, $oldCover]);
    }

    public function test_gallery_capacity_failure_rolls_back_and_cleans_staged_files(): void
    {
        Storage::fake('public');
        $product = $this->product($this->user('seller'));
        for ($i = 0; $i < 8; $i++) {
            $this->savedMedia($product, 'image', 'products/media/photo-'.$i.'.jpg');
        }
        $files = Storage::disk('public')->allFiles();
        $this->actingAs($product->seller)->put(route('seller.products.update', $product), [...$this->payload(),
            'price' => 999, 'gallery_images' => [UploadedFile::fake()->image('extra.jpg')],
        ])->assertSessionHasErrors('gallery_images');
        $this->assertEquals(100, $product->fresh()->price);
        $this->assertSame('approved', $product->fresh()->compliance_status);
        $this->assertSame(8, $product->media()->count());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_upload_formats_sizes_and_foreign_media_ids_are_rejected(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller');
        $this->actingAs($seller)->post(route('seller.products.store'), [...$this->payload(),
            'gallery_images' => [UploadedFile::fake()->create('unsafe.svg', 2, 'image/svg+xml')],
            'product_video' => UploadedFile::fake()->create('fake.mp4', 10, 'application/x-php'),
        ])->assertSessionHasErrors(['gallery_images.0', 'product_video']);
        $this->post(route('seller.products.store'), [...$this->payload(),
            'gallery_images' => [UploadedFile::fake()->image('large.jpg')->size(2049)],
            'product_video' => UploadedFile::fake()->create('large.mp4', 20481, 'video/mp4'),
        ])->assertSessionHasErrors(['gallery_images.0', 'product_video']);
        $this->assertDatabaseCount('products', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());

        $own = $this->product($seller);
        $other = $this->product($this->user('seller'));
        $foreign = $this->savedMedia($other, 'image', 'products/media/other.jpg');
        $this->put(route('seller.products.update', $own), [...$this->payload(), 'remove_media' => [$foreign->id]])->assertSessionHasErrors('remove_media.0');
        $this->put(route('seller.products.update', $other), $this->payload())->assertForbidden();
        $this->assertDatabaseHas('product_media', ['id' => $foreign->id]);
        Storage::disk('public')->assertExists($foreign->path);
    }

    public function test_hard_deletion_cleans_media_but_archiving_preserves_media_and_review_history(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller');
        $product = $this->product($seller);
        $media = $this->savedMedia($product, 'video', 'products/media/delete.mp4');
        $this->actingAs($seller)->delete(route('seller.products.destroy', $product))->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing([$media->path, $product->image_path]);

        $archived = $this->product($seller);
        $saved = $this->savedMedia($archived, 'image', 'products/media/retained.jpg');
        $archived->complianceEvents()->create(['actor_id' => $seller->id, 'action' => 'submitted', 'new_status' => 'pending_review']);
        $this->delete(route('seller.products.destroy', $archived))->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $archived->id, 'is_archived' => true]);
        Storage::disk('public')->assertExists([$saved->path, $archived->image_path]);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'password', 'first_name' => 'Media', 'last_name' => 'User', 'role' => $role, 'status' => 'approved', 'contact_no' => '09123456789']);
    }

    private function payload(): array
    {
        return ['name' => 'Media test product', 'description' => 'Product media demonstration.', 'category' => 'Fashion', 'price' => 100, 'stock' => 3];
    }

    private function product(User $seller): Product
    {
        Storage::disk('public')->put('products/cover-'.$seller->id.'.jpg', 'sample');

        return Product::forceCreate([...$this->payload(), 'user_id' => $seller->id, 'image_path' => 'products/cover-'.$seller->id.'.jpg', 'compliance_status' => 'approved', 'is_archived' => false]);
    }

    private function savedMedia(Product $product, string $kind, string $path): ProductMedia
    {
        Storage::disk('public')->put($path, 'sample');

        return $product->media()->create(['kind' => $kind, 'path' => $path, 'mime_type' => $kind === 'image' ? 'image/jpeg' : 'video/mp4', 'sort_order' => $product->media()->count() + 1]);
    }
}
