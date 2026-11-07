@props(['label', 'value', 'icon' => 'diagram', 'detail' => null, 'tone' => 'accent'])
<article class="workspace-metric workspace-metric--{{ $tone }}">
    <div class="workspace-metric-heading"><h2>{{ $label }}</h2><span class="workspace-metric-icon"><x-shop-icon :name="$icon" /></span></div>
    <strong>{{ $value }}</strong>
    @if ($detail)<p>{{ $detail }}</p>@endif
</article>
