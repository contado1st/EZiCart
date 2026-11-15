<?php

namespace App\Services;

use App\Models\DirectMessage;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MessageRetractionService
{
    public function message(DirectMessage $message, User $actor): DirectMessage
    {
        return DB::transaction(function () use ($message, $actor): DirectMessage {
            $locked = $this->ownedMessage($message->id, $actor);
            if ($locked->unsent_at) {
                return $locked;
            }
            foreach ($locked->attachments()->orderBy('id')->lockForUpdate()->get() as $attachment) {
                $this->retractAttachment($attachment);
            }
            $locked->update(['body' => 'Message unsent.', 'product_context' => null, 'unsent_at' => now(), 'read_at' => $locked->read_at ?? now()]);
            DB::table('notifications')->where('type', DirectMessageNotification::class)->where('data->message_id', $locked->id)->delete();

            return $locked;
        }, 3);
    }

    public function attachment(MessageAttachment $attachment, User $actor): DirectMessage
    {
        return DB::transaction(function () use ($attachment, $actor): DirectMessage {
            $message = $this->ownedMessage($attachment->direct_message_id, $actor);
            $locked = $message->attachments()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            $this->retractAttachment($locked);
            if ($message->body === 'Shared '.$message->attachments()->count().' attachment(s).'
                && ! $message->attachments()->whereNull('unsent_at')->exists()) {
                $message->update(['body' => 'Attachments unsent.', 'read_at' => $message->read_at ?? now()]);
                DB::table('notifications')->where('type', DirectMessageNotification::class)->where('data->message_id', $message->id)->delete();
            }

            return $message;
        }, 3);
    }

    private function ownedMessage(int $id, User $actor): DirectMessage
    {
        $message = DirectMessage::with('conversation')->whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($message->sender_id === $actor->id && $message->conversation->hasParticipant($actor), 403);

        return $message;
    }

    private function retractAttachment(MessageAttachment $attachment): void
    {
        if ($attachment->unsent_at) {
            return;
        }
        $path = $attachment->path;
        $id = $attachment->id;
        $attachment->update(['unsent_at' => now(), 'original_name' => 'Attachment unsent', 'mime_type' => 'application/octet-stream', 'size' => 0]);
        DB::afterCommit(function () use ($path, $id): void {
            try {
                if (! Storage::disk('private')->delete($path)) {
                    Log::warning('Retracted attachment cleanup failed.', ['attachment_id' => $id]);
                }
            } catch (Throwable $error) {
                report($error);
            }
        });
    }
}
