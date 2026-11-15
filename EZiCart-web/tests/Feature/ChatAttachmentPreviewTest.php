<?php

namespace Tests\Feature;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ChatAttachmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('private');
    }

    public function test_images_and_pdfs_open_a_preview_page_and_download_only_when_requested(): void
    {
        $upload = UploadedFile::fake()->image('photo.png');
        foreach ([['photo.png', 'image/png', file_get_contents($upload->getRealPath())], ['quote.pdf', 'application/pdf', '%PDF-1.4 test document']] as [$name, $mime, $contents]) {
            [$sender, $recipient, $attachment] = $this->attachment($name, $mime, $contents);
            $this->as($recipient)->get(route('chat.attachments.show', [$attachment, 'panel' => 1]))->assertOk()->assertSee('File preview')->assertSee('Download file')->assertSee($name)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8');
            $this->get(route('chat.attachments.show', [$attachment, 'preview' => 1]))->assertOk()->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get(route('chat.attachments.show', [$attachment, 'download' => 1]))->assertDownload($name);
        }
    }

    public function test_text_preview_escapes_markup_and_keeps_the_original_download(): void
    {
        [$sender, $recipient, $attachment] = $this->attachment('note.txt', 'text/plain', '<script>unsafe-preview()</script>');
        $this->as($sender)->get(route('chat.attachments.show', $attachment))->assertOk()
            ->assertSee('&lt;script&gt;unsafe-preview()&lt;/script&gt;', false)->assertDontSee('<script>unsafe-preview()', false);
        $this->get(route('chat.attachments.show', [$attachment, 'download' => 1]))->assertDownload('note.txt');
    }

    public function test_word_preview_extracts_local_text_without_rendering_document_markup(): void
    {
        $archive = $this->archive(['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Parcel instructions &lt;script&gt;word-alert&lt;/script&gt;</w:t></w:r></w:p></w:body></w:document>']);
        [$sender, $recipient, $attachment] = $this->attachment('instructions.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $archive);
        $this->as($recipient)->get(route('chat.attachments.show', $attachment))->assertOk()->assertSee('Parcel instructions')
            ->assertSee('&lt;script&gt;word-alert&lt;/script&gt;', false)->assertDontSee('<script>word-alert', false)->assertSee('Text preview of this Word document');
    }

    public function test_spreadsheet_preview_shows_saved_values_and_cell_references_without_executing_formulas(): void
    {
        $archive = $this->archive([
            'xl/sharedStrings.xml' => '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Parcel &lt;img src=x&gt;</t></si></sst>',
            'xl/worksheets/sheet1.xml' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1"><f>HYPERLINK("https://untrusted.example")</f><v>17</v></c></row></sheetData></worksheet>',
        ]);
        [$sender, $recipient, $attachment] = $this->attachment('parcels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $archive);
        $this->as($sender)->get(route('chat.attachments.show', $attachment))->assertOk()->assertSee('Parcel &lt;img src=x&gt;', false)
            ->assertSee('A1')->assertSee('B1')->assertSee('17')->assertDontSee('untrusted.example')->assertSee('Formulas are not executed');
    }

    public function test_unsafe_oversized_or_legacy_office_documents_do_not_trigger_a_download(): void
    {
        $files = [
            ['external.docx', $this->archive(['word/document.xml' => '<!DOCTYPE x [<!ENTITY leak SYSTEM "file:///private-secret">]><x>&leak;</x>'])],
            ['oversized.docx', $this->archive(['word/document.xml' => str_repeat('x', 2097153)])],
            ['corrupt.xlsx', 'Not a ZIP archive'],
            ['legacy.doc', 'Legacy binary document'],
            ['legacy.xls', 'Legacy binary workbook'],
        ];
        foreach ($files as [$name, $bytes]) {
            [$sender, $recipient, $attachment] = $this->attachment($name, 'application/octet-stream', $bytes);
            $this->as($sender)->get(route('chat.attachments.show', $attachment))->assertOk()->assertSee('cannot be previewed here')->assertSee('Download file')
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertDontSee('file:///private-secret');
        }
    }

    public function test_long_text_is_bounded_and_labelled_as_a_limited_preview(): void
    {
        [$sender, $recipient, $attachment] = $this->attachment('long.txt', 'text/plain', str_repeat('x', 100010).'hidden-tail');
        $this->as($recipient)->get(route('chat.attachments.show', $attachment))->assertOk()->assertSee('limited preview')->assertDontSee('hidden-tail');
    }

    public function test_all_attachment_representations_require_conversation_membership(): void
    {
        [$sender, $recipient, $attachment] = $this->attachment('private.txt', 'text/plain', 'Private bytes');
        $other = $this->user('buyer');
        foreach ([[], ['preview' => 1], ['download' => 1]] as $query) {
            $this->as($other)->get(route('chat.attachments.show', [$attachment, ...$query]))->assertForbidden();
        }
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function attachment(string $name, string $mime, string $contents): array
    {
        $sender = $this->user('seller');
        $recipient = $this->user('admin');
        $conversation = DirectConversation::create(['first_user_id' => $sender->id, 'second_user_id' => $recipient->id]);
        $message = DirectMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'body' => 'Attached document']);
        $path = 'message-attachments/'.$message->id.'/'.$name;
        Storage::disk('private')->put($path, $contents);
        $attachment = $message->attachments()->create(['path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'size' => strlen($contents)]);

        return [$sender, $recipient, $attachment];
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => fake()->unique()->safeEmail(), 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'first_name' => 'Attachment', 'last_name' => 'Preview', 'contact_no' => '09123456789']);
    }

    private function archive(array $entries): string
    {
        $file = tempnam(sys_get_temp_dir(), 'chat-preview-');
        try {
            $zip = new ZipArchive;
            $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($entries as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return file_get_contents($file);
        } finally {
            unlink($file);
        }
    }
}
