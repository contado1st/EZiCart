<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Services\RiderWorkAvailabilityService;
use Illuminate\Http\Request;

class WorkAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        return response()->view('courier.work-availability', ['rider' => $request->user()])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, RiderWorkAvailabilityService $availability)
    {
        $data = $request->validate(['state' => ['required', 'in:available,unavailable'], 'version' => ['required', 'integer', 'min:0']]);
        $released = $availability->choose($request->user(), $data['state'], (int) $data['version']);

        return back()->with('success', 'Work availability saved. '.($availability->enforced()
            ? array_sum($released).' unaccepted offer(s) returned to logistics. Accepted work remains assigned.'
            : 'Choices are being collected; availability enforcement has not started. Existing assignment rules remain active.'));
    }
}
