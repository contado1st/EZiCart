<?php

namespace App\Http\Controllers;

use App\Services\ZoneReadinessService;
use Illuminate\Http\Request;

class ZoneReadinessController extends Controller
{
    public function __invoke(Request $request, ZoneReadinessService $service)
    {
        $actor = $request->user();
        $areas = $service->areas($actor)->orderBy('name')->get(['id', 'name', 'code']);
        $activation = $service->activation();
        $preview = $request->boolean('preview') ? $service->preview($actor, $request->all()) : null;

        return response()->view('logistics.zone-readiness', compact('actor', 'areas', 'activation', 'preview'))->header('Cache-Control', 'private, no-store');
    }
}
