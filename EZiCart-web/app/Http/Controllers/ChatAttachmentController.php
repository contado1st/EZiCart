<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatAttachmentController extends Controller
{
    public function show(Request $request, MessageAttachment $attachment)
    {
        abort_unless($attachment->message->conversation->hasParticipant($request->user()), 403);
        abort_unless(Storage::disk('private')->exists($attachment->path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"];
        if ($request->boolean('preview') && in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Storage::disk('private')->response($attachment->path, $attachment->original_name, $headers + ['Content-Type' => $attachment->mime_type]);
        }

        return Storage::disk('private')->download($attachment->path, $attachment->original_name, $headers);
    }
}
