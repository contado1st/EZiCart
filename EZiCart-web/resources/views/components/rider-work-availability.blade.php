@props(['rider', 'expanded' => false])
<details class="ops-panel" aria-label="Work availability" @if($expanded) open @endif>
    <summary>New work: <strong>{{ match($rider->work_availability) { 'available' => 'Available', 'unavailable' => 'Unavailable', default => 'Not chosen' } }}</strong>{{ config('rider-work.enforce_availability') ? '' : ' · Preparation mode' }}</summary>
    <h2>Availability for new work</h2>
    <p><strong>{{ match($rider->work_availability) { 'available' => 'Available', 'unavailable' => 'Unavailable', default => 'Not chosen' } }}</strong>@if($rider->work_availability_changed_at) · Updated {{ $rider->work_availability_changed_at->format('M d, Y h:i A') }}@endif</p>
    <p>Account approval, licence, verified vehicle and cargo limits are separate. This choice does not mean you are online, change official profile details or give up parcels already accepted.</p>
    @if(config('rider-work.enforce_availability'))<p>New-work rules are active. You must choose Available to receive new assignments. Choosing unavailable returns safely unaccepted offers to logistics; continue accepted work and follow all handover scans.</p>
    @else<p role="status">Preparation mode: choices are saved, but new-work enforcement has not started. Existing assignment rules continue until logistics announces activation.</p>@endif
    <form class="ops-form" method="POST" action="{{ route('courier.work-availability.update') }}" data-decision="rider_work_availability" data-decision-title="Change work availability?" data-decision-message="Check the selected choice. Accepted parcels remain your responsibility. Riders must choose Available to receive new offers." data-decision-label="Save availability">
        @csrf @method('PATCH')<input type="hidden" name="version" value="{{ $rider->work_availability_version ?? 0 }}">
        <label>New work<select name="state" required><option value="" @selected(!in_array($rider->work_availability, ['available','unavailable'], true))>Choose availability</option><option value="available" @selected($rider->work_availability === 'available')>Available for new work</option><option value="unavailable" @selected($rider->work_availability === 'unavailable')>Unavailable for new work</option></select></label>
        <button class="ops-btn ops-btn--primary" type="submit">Save availability</button>
    </form>
</details>
