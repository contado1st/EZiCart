@props(['order', 'kind', 'event'])
@if((int) $event->actor_id === auth()->id() && ($kind !== 'scan' || $event->result === 'accepted'))
<form class="web-form" method="POST" action="{{ route('parcel-evidence.store', $order) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="event_type" value="{{ $kind }}"><input type="hidden" name="event_id" value="{{ $event->id }}"><label for="photo-{{ $kind }}-{{ $event->id }}">Attach receiving / handoff photos (up to 3; 2 MB each)<input id="photo-{{ $kind }}-{{ $event->id }}" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label><button class="dash-btn-sm" type="submit">Save event photos</button></form>
@endif
