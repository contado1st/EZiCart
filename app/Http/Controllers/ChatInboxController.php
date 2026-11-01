<?php

namespace App\Http\Controllers;

use App\Services\MessagingInboxService;
use Illuminate\Http\Request;

class ChatInboxController extends Controller
{
    public function __invoke(Request $request, MessagingInboxService $inbox)
    {
        $threads = $inbox->threads($request);
        $messagePrefix = $inbox->routePrefix($request->user());

        return view('orders.inbox', compact('threads', 'messagePrefix'));
    }
}
