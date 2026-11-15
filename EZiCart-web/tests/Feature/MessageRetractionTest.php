<?php

namespace Tests\Feature;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageRetractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_sender_unsends_content_files_and_product_context_and_revokes_old_urls(): void
    {
        [$sender, $recipient, $conversation, $message] = $this->fixture('Original private text');
        $message->update(['product_context' => ['name' => 'Original product', 'price_label' => 'PHP 100', 'image_path' => 'enquiry.png']]);
        Storage::disk('public')->put('enquiry.png', 'image');
        $attachment = $this->attachment($message, 'secret.txt');
        $notification = $this->notification($message, $recipient);

        $this->as($sender)->delete(route('chat.destroy', $message), ['panel' => 1])
            ->assertRedirect(route('chat.show', [$conversation, 'panel' => 1]))->assertSessionHasNoErrors();
        $message->refresh();
        $this->assertNotNull($message->unsent_at);
        $this->assertNotNull($message->read_at);
        $this->assertSame('Message unsent.', $message->body);
        $this->assertNull($message->product_context);
        $this->assertNotNull($attachment->fresh()->unsent_at);
        $this->assertSame('Attachment unsent', $attachment->fresh()->original_name);
        Storage::disk('private')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('notifications', ['id' => $notification]);

        $this->as($recipient)->get(route('chat.show', $conversation))->assertOk()->assertSee('This message was unsent.')
            ->assertDontSee('Original private text')->assertDontSee('Original product')->assertDontSee('secret.txt');
        foreach ([[], ['preview' => 1], ['download' => 1]] as $query) {
            $this->get(route('chat.attachments.show', [$attachment, ...$query]))->assertGone();
        }
        $this->get(route('chat.product-image', $message))->assertGone();
    }

    public function test_an_individual_file_can_be_unsent_without_removing_the_text_or_other_files(): void
    {
        [$sender, $recipient, $conversation, $message] = $this->fixture('Keep this text');
        $removed = $this->attachment($message, 'removed.txt');
        $kept = $this->attachment($message, 'kept.txt');
        $this->as($sender)->delete(route('chat.attachments.destroy', $removed))->assertRedirect(route('chat.show', $conversation));
        $this->assertNull($message->fresh()->unsent_at);
        $this->assertSame('Keep this text', $message->fresh()->body);
        Storage::disk('private')->assertMissing($removed->path);
        Storage::disk('private')->assertExists($kept->path);
        $this->as($recipient)->get(route('chat.show', $conversation))->assertOk()->assertSee('Keep this text')->assertSee('Attachment unsent.')->assertSee('kept.txt')->assertDontSee('removed.txt');
        $this->get(route('chat.attachments.show', $removed))->assertGone();
        $this->get(route('chat.attachments.show', $kept))->assertOk();
    }

    public function test_last_attachment_only_retraction_clears_unread_placeholder_and_notification(): void
    {
        [$sender, $recipient, $conversation, $message] = $this->fixture('Shared 1 attachment(s).');
        $attachment = $this->attachment($message, 'only.txt');
        $id = $this->notification($message, $recipient);
        $this->as($sender)->delete(route('chat.attachments.destroy', $attachment))->assertRedirect();
        $this->assertSame('Attachments unsent.', $message->fresh()->body);
        $this->assertNotNull($message->fresh()->read_at);
        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_recipient_and_outsider_cannot_unsend_someone_elses_message_or_file(): void
    {
        [$sender, $recipient, $conversation, $message] = $this->fixture('Keep original');
        $attachment = $this->attachment($message, 'keep.txt');
        foreach ([$recipient, $this->user('admin')] as $other) {
            $this->as($other)->delete(route('chat.destroy', $message))->assertForbidden();
            $this->delete(route('chat.attachments.destroy', $attachment))->assertForbidden();
        }
        $this->assertSame('Keep original', $message->fresh()->body);
        $this->assertNull($message->fresh()->unsent_at);
        $this->assertNull($attachment->fresh()->unsent_at);
        Storage::disk('private')->assertExists($attachment->path);
    }

    public function test_repeated_unsends_are_idempotent_and_other_conversations_are_untouched(): void
    {
        [$sender, $recipient, $conversation, $message] = $this->fixture('Remove once');
        $attachment = $this->attachment($message, 'once.txt');
        [, , , $other] = $this->fixture('Other conversation');
        $this->as($sender)->delete(route('chat.destroy', $message))->assertRedirect();
        $at = $message->fresh()->unsent_at->toIso8601String();
        $this->travel(1)->minutes();
        $this->delete(route('chat.destroy', $message))->assertRedirect();
        $this->delete(route('chat.attachments.destroy', $attachment))->assertRedirect();
        $this->assertSame($at, $message->fresh()->unsent_at->toIso8601String());
        $this->assertSame('Other conversation', $other->fresh()->body);
        $this->assertNull($other->fresh()->unsent_at);
    }

    public function test_retraction_refresh_is_private_bounded_and_does_not_return_original_content(): void
    {
        [$sender, $recipient, $conversation, $message] = $this->fixture('Never include this text in status JSON');
        $attachment = $this->attachment($message, 'hidden-name.txt');
        $this->as($sender)->delete(route('chat.destroy', $message));
        $recipient->forceFill(['last_web_activity_at' => now()->subDays(20)])->save();
        $lastActivity = $recipient->fresh()->last_web_activity_at;
        $this->as($recipient)->get(route('chat.retractions', [$conversation, 'message_ids' => [$message->id]]))
            ->assertOk()->assertJsonPath('messages.0.unsent', true)->assertJsonPath('messages.0.attachment_ids.0', $attachment->id)
            ->assertDontSee('Never include this text')->assertDontSee('hidden-name.txt')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue($lastActivity->equalTo($recipient->fresh()->last_web_activity_at));
        [, , , $foreign] = $this->fixture('Foreign');
        $this->getJson(route('chat.retractions', [$conversation, 'message_ids' => [$foreign->id]]))->assertOk()->assertExactJson(['messages' => []]);
        $this->getJson(route('chat.retractions', [$conversation, 'message_ids' => range(1, 41)]))->assertUnprocessable();
        $this->as($this->user('buyer'))->getJson(route('chat.retractions', [$conversation, 'message_ids' => [$message->id]]))->assertForbidden();
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function fixture(string $body): array
    {
        $sender = $this->user('seller');
        $recipient = $this->user('admin');
        $conversation = DirectConversation::create(['first_user_id' => $sender->id, 'second_user_id' => $recipient->id]);
        $message = DirectMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'body' => $body]);

        return [$sender, $recipient, $conversation, $message];
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'first_name' => 'Preview', 'last_name' => ucfirst($role), 'contact_no' => '09123456789']);
    }

    private function attachment(DirectMessage $message, string $name): MessageAttachment
    {
        $path = 'message-attachments/'.$message->id.'/'.$name;
        Storage::disk('private')->put($path, 'File contents');

        return $message->attachments()->create(['path' => $path, 'original_name' => $name, 'mime_type' => 'text/plain', 'size' => 13]);
    }

    private function notification(DirectMessage $message, User $recipient): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert(['id' => $id, 'type' => DirectMessageNotification::class, 'notifiable_type' => User::class, 'notifiable_id' => $recipient->id,
            'data' => json_encode(['message_id' => $message->id, 'conversation_id' => $message->conversation_id]), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }
}
