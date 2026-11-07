<dl class="profile-review-changes">
    @foreach($revision->proposed_values as $field => $value)
        <div>
            <dt>{{ \Illuminate\Support\Str::headline(str_replace('_path', '', $field)) }}</dt>
            @foreach(['before' => $revision->previous_values[$field] ?? null, 'requested' => $value] as $version => $display)
                <dd><span class="web-muted">{{ $version === 'before' ? 'Current at submission' : 'Requested' }}</span>
                    @if(in_array($field, ['profile_avatar_path', 'profile_banner_path'], true))
                        @if($display)<img class="profile-review-image" src="{{ route('account.profile-requests.media', [$revision, $field === 'profile_avatar_path' ? 'avatar' : 'banner', 'version' => $version]) }}" alt="{{ $version === 'before' ? 'Current' : 'Requested' }} {{ $field === 'profile_avatar_path' ? 'profile photo' : 'banner' }}">
                        @else<span>{{ $version === 'before' ? 'No image' : 'Remove image' }}</span>@endif
                    @else<span>{{ filled($display) ? $display : 'Not specified' }}</span>@endif
                </dd>
            @endforeach
        </div>
    @endforeach
</dl>
