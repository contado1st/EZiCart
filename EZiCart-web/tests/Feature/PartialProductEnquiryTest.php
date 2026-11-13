<?php

namespace Tests\Feature;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Services\ProductMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartialProductEnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_opening_product_chat_sends_nothing_then_submission_shares_server_owned_product_text_and_image(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        Notification::fake();
        [$buyer, $seller, $product] = $this->fixture();
        $this->actingAs($buyer)->get(route('product.show', $product))->assertOk()->assertSee('name="product_id" value="'.$product->id.'"', false);
        $this->post(route('chat.start', $seller), ['product_id' => $product->id])->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $url = route('chat.show', [$conversation, 'product_id' => $product->id]);
        $this->get($url)->assertOk()->assertSee('Enquiry about Original product');
        $this->get($url)->assertOk();
        $this->assertDatabaseCount('direct_messages', 0);
        Notification::assertNothingSent();
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'Does this fit?', 'product_context' => ['name' => 'FORGED', 'image_path' => '.env'],
            'attachments' => [UploadedFile::fake()->image('question.jpg')]])->assertRedirectToRoute('chat.show', $conversation)->assertSessionHasNoErrors();
        $message = DirectMessage::firstOrFail();
        $this->assertSame('Original product', $message->product_context['name']);
        $this->assertSame('₱100.00', $message->product_context['price_label']);
        $this->assertSame($product->image_path, $message->product_context['image_path']);
        $this->assertSame($seller->id, $message->recipient_id);
        $this->assertArrayNotHasKey('product_context', $message->toArray());
        $this->assertDatabaseCount('message_attachments', 1);
        Notification::assertSentTo($seller, DirectMessageNotification::class);
        $this->flushSession();
        $this->actingAs($seller)->get(route('chat.show', $conversation))->assertOk()->assertSeeInOrder(['Product enquiry: Original product', 'Does this fit?', 'question.jpg']);
        $this->get(route('chat.product-image', $message))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_other_shop_and_hidden_product_contexts_are_rejected_and_staged_attachments_are_cleaned(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        Notification::fake();
        [$buyer, $seller, $product] = $this->fixture();
        $otherSeller = $this->user('seller');
        $this->actingAs($buyer)->post(route('chat.start', $otherSeller), ['product_id' => $product->id])->assertNotFound();
        $this->assertDatabaseCount('direct_conversations', 0);
        $this->post(route('chat.start', $otherSeller))->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'Invalid shop enquiry', 'attachments' => [UploadedFile::fake()->image('cleanup.png')]])->assertNotFound();
        $this->assertDatabaseCount('direct_messages', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $product->forceFill(['is_seller_active' => false])->save();
        $this->post(route('chat.start', $seller), ['product_id' => $product->id])->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_plain_replies_remain_plain_and_reopening_never_reposts_a_product_enquiry(): void
    {
        Storage::fake('public');
        Notification::fake();
        [$buyer, $seller, $product] = $this->fixture();
        $this->actingAs($buyer)->post(route('chat.start', $seller), ['product_id' => $product->id])->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'First product question'])->assertRedirect();
        $this->post(route('chat.start', $seller), ['product_id' => $product->id])->assertRedirect();
        $this->assertDatabaseCount('direct_messages', 1);
        $this->flushSession();
        $this->actingAs($seller)->post(route('chat.store', $conversation), ['body' => 'Yes, it is available.'])->assertRedirect();
        $this->assertNull(DirectMessage::latest('id')->firstOrFail()->product_context);
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'Forged buyer enquiry'])->assertForbidden();
        $this->assertDatabaseCount('direct_messages', 2);
    }

    public function test_historical_card_and_image_survive_listing_changes_and_media_cleanup_but_stay_conversation_scoped(): void
    {
        Storage::fake('public');
        Notification::fake();
        [$buyer, $seller, $product] = $this->fixture();
        $oldPath = $product->image_path;
        $this->actingAs($buyer)->post(route('chat.start', $seller))->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'Historical product question'])->assertRedirect();
        $message = DirectMessage::firstOrFail();
        $product->forceFill(['name' => 'Changed private name', 'price' => 999, 'image_path' => null, 'compliance_status' => 'pending_review'])->save();
        app(ProductMediaService::class)->deleteFiles([$oldPath]);
        Storage::disk('public')->assertExists($oldPath);
        $this->get(route('chat.show', $conversation))->assertOk()->assertSee('Original product')->assertDontSee('Changed private name');
        $this->get(route('chat.product-image', $message))->assertOk();
        $this->flushSession();
        $this->actingAs($this->user('buyer'))->get(route('chat.product-image', $message))->assertForbidden();
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'Not a participant'])->assertForbidden();
    }

    public function test_product_context_alone_does_not_satisfy_a_message_and_guests_cannot_read_its_image(): void
    {
        Storage::fake('public');
        Notification::fake();
        [$buyer, $seller, $product] = $this->fixture();
        $this->actingAs($buyer)->post(route('chat.start', $seller))->assertRedirect();
        $conversation = DirectConversation::firstOrFail();
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('direct_messages', 0);
        $this->post(route('chat.store', $conversation), ['product_id' => $product->id, 'body' => 'Explicit question'])->assertRedirect();
        $message = DirectMessage::firstOrFail();
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('chat.product-image', $message))->assertRedirectToRoute('login');
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Enquiry', 'last_name' => 'Tester', 'contact_no' => '09123456789']);
    }

    private function fixture(): array
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $path = UploadedFile::fake()->image('product.png')->store('products', 'public');
        $product = Product::forceCreate(['user_id' => $seller->id, 'name' => 'Original product', 'category' => 'Pet Supplies', 'price' => 100, 'stock' => 10,
            'image_path' => $path, 'is_archived' => false, 'compliance_status' => 'approved']);

        return [$buyer, $seller, $product];
    }
}
