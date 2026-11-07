@props(['user'])
<span {{ $attributes->class(['profile-avatar']) }}>
    @if($user->profile_avatar_path)
        <img src="{{ route('profiles.media', [$user, 'avatar', 'v' => $user->updated_at?->timestamp]) }}" alt="" width="96" height="96" decoding="async">
    @else
        <span aria-hidden="true">{{ mb_strtoupper(mb_substr($user->profile_display_name ?: $user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</span>
    @endif
</span>
