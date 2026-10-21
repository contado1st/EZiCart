@props(['kind', 'count' => 0])
<span class="communication-count-badge" data-communication-badge="{{ $kind }}" aria-hidden="true" @if(!$count) hidden @endif>{{ $count > 99 ? '99+' : $count }}</span>
