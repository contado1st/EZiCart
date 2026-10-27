<?php

namespace App\Http\Controllers;

use App\Services\CommunicationUnreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationCountController extends Controller
{
    public function __invoke(Request $request, CommunicationUnreadService $unread): JsonResponse
    {
        return response()->json($unread->counts($request->user()))->header('Cache-Control', 'private, no-store');
    }
}
