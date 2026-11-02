<?php

namespace App\Http\Controllers;

use App\Services\WorkflowAttentionService;
use Illuminate\Http\Request;

class WorkflowAttentionController extends Controller
{
    public function __invoke(Request $request, WorkflowAttentionService $attention)
    {
        $data = $request->validate(['order_ids' => ['nullable', 'array', 'max:100'], 'order_ids.*' => ['integer', 'min:1']]);

        return response()->json($attention->payload($request->user(), $data['order_ids'] ?? []))->header('Cache-Control', 'private, no-store');
    }
}
