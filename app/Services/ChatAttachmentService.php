<?php

namespace App\Services;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ChatAttachmentService
{
    public static function maxKilobytes(): int
    {
        $phpLimit = trim((string) ini_get('upload_max_filesize'));
        $multiplier = match (strtolower(substr($phpLimit, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1
        };
        $bytes = (int) ((float) $phpLimit * $multiplier);

        return min((int) config('web-commerce.attachment_max_kb'), $bytes > 0 ? max(1, intdiv($bytes, 1024)) : PHP_INT_MAX);
    }

    public function send(DirectConversation $conversation, User $actor, ?string $body, array $files, ?Order $order = null): DirectMessage
    {
        abort_unless($conversation->hasParticipant($actor), 403);
        $stored = [];
        try {
            foreach ($files as $file) {
                $path = $file->store('message-attachments/'.$conversation->id, 'private');
                if (! is_string($path)) {
                    throw new \RuntimeException('The attachment could not be stored.');
                }
                $stored[] = ['path' => $path, 'original_name' => Str::limit(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'attachment', 200, ''), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
            }

            return DB::transaction(function () use ($conversation, $actor, $body, $order, $stored) {
                $message = app(DirectMessagingService::class)->send($conversation, $actor, filled($body) ? $body : 'Shared '.count($stored).' attachment(s).', $order);
                $message->attachments()->createMany($stored);

                return $message;
            }, 3);
        } catch (Throwable $e) {
            Storage::disk('private')->delete(array_column($stored, 'path'));
            throw $e;
        }
    }
}
