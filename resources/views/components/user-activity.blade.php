@props(['user'])
@php($recent = $user->last_web_activity_at && $user->last_web_activity_at->gte(now()->subMinutes(config('web-commerce.online_minutes'))))
<div class="web-person-status"><span class="web-status-dot {{ $recent ? 'is-online' : '' }}" aria-hidden="true"></span><span>{{ $recent ? 'Active recently' : ($user->last_web_activity_at ? 'Last active '.$user->last_web_activity_at->diffForHumans() : 'Activity not recorded yet') }}</span></div>
@if($user->role === 'seller')
    @if($user->effectiveShopAvailability() === 'open')<p class="web-person-status">Shop open @if($user->shop_availability !== 'open' && $user->shop_unavailable_from?->isFuture()) · Scheduled pause begins {{ $user->shop_unavailable_from->format('d M Y H:i') }} @endif</p>@endif
    @if($user->effectiveShopAvailability() !== 'open')<p class="web-availability-note">{{ match($user->effectiveShopAvailability()) { 'vacation' => 'Seller on vacation.', 'closed' => 'Shop temporarily closed.', default => 'Seller temporarily unavailable.' } }} New orders are paused.@if($user->shop_returns_at) Expected to return {{ $user->shop_returns_at->format('d M Y H:i') }}. @endif</p>@endif
    @if($user->last_web_activity_at && $user->last_web_activity_at->lte(now()->subDays(config('web-commerce.inactive_warning_days'))))<p class="web-availability-note">This seller has been inactive for {{ (int) $user->last_web_activity_at->diffInDays(now()) }} days. Consider contacting the shop before purchasing.</p>@endif
@endif
