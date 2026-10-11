<?php

namespace App\Http\Controllers;

use App\Services\PsgcAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class PsgcAddressController extends Controller
{
    public function __invoke(Request $request, PsgcAddressService $psgc): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::in(['regions', 'provinces', 'localities', 'barangays'])],
            'parent' => ['nullable', 'regex:/^(?:\d{10}|NCR)$/'],
            'parent_level' => ['nullable', Rule::in(['region', 'province', 'locality'])],
        ]);

        abort_unless($validated['level'] === 'regions' || filled($validated['parent'] ?? null), 422);
        abort_unless($validated['level'] !== 'localities' || filled($validated['parent_level'] ?? null), 422);

        try {
            return response()->json([
                'data' => $psgc->options(
                $validated['level'],
                $validated['parent'] ?? null,
                $validated['parent_level'] ?? null,
                ),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Location data is temporarily unavailable. Please try again.'], 503);
        }
    }
}
