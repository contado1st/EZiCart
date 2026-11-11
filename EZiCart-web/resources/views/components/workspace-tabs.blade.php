@props(['tabs', 'label' => 'Dashboard sections', 'defaultTab' => null])

<nav class="workspace-tabs" aria-label="{{ $label }}" data-workspace-tabs data-default-tab="{{ $defaultTab }}">
    @foreach ($tabs as $tab)
        <a id="tab-{{ $tab['id'] }}" href="{{ request()->fullUrlWithQuery(['workspace_tab' => $tab['id']]) }}#{{ $tab['id'] }}" data-workspace-tab="{{ $tab['id'] }}">
            <span>{{ $tab['label'] }}</span>
            @if (isset($tab['count']))<span class="workspace-tab-count">{{ number_format($tab['count']) }}</span>@endif
        </a>
    @endforeach
</nav>
