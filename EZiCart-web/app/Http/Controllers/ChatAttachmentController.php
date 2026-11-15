<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use App\Services\ChatAttachmentPreviewService;
use App\Services\MessageRetractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatAttachmentController extends Controller
{
    public function show(Request $request, MessageAttachment $attachment, ChatAttachmentPreviewService $previews)
    {
        abort_unless($attachment->message->conversation->hasParticipant($request->user()), 403);
        abort_if($attachment->unsent_at || $attachment->message->unsent_at, 410, 'This attachment was unsent.');
        abort_unless(Storage::disk('private')->exists($attachment->path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"];
        if ($request->boolean('download')) {
            return Storage::disk('private')->download($attachment->path, $attachment->original_name, $headers);
        }
        if ($request->boolean('preview') && in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            return Storage::disk('private')->response($attachment->path, $attachment->original_name, $headers + ['Content-Type' => $attachment->mime_type], 'inline');
        }

        return response()->view('orders.attachment-preview', ['attachment' => $attachment, 'preview' => $previews->describe($attachment)])
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function destroy(Request $request, MessageAttachment $attachment, MessageRetractionService $retractions)
    {
        $request->validate(['panel' => ['nullable', 'boolean']]);
        $message = $retractions->attachment($attachment, $request->user());

        return redirect()->route('chat.show', [$message->conversation_id, 'panel' => $request->boolean('panel') ? 1 : null])
            ->with('success', 'Attachment unsent for everyone.');
    }
}
