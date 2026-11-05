@props(['values', 'label' => 'Order status', 'description' => null])
@php
    $distributionTotal = array_sum($values);
    $distributionOffset = 0;
@endphp
<section class="workspace-distribution" aria-label="{{ $label }}">
    <h2>{{ $label }}</h2>
    @if ($description)<p class="workspace-panel-description">{{ $description }}</p>@endif
    <div class="workspace-distribution-body">
        <div class="workspace-donut">
            <svg viewBox="0 0 160 160" aria-hidden="true" focusable="false">
                <circle class="workspace-donut-track" cx="80" cy="80" r="60" />
                @foreach ($values as $statusLabel => $statusCount)
                    @php $distributionPercent = $distributionTotal > 0 ? $statusCount / $distributionTotal * 100 : 0; @endphp
                    <circle class="workspace-chart-color-{{ $loop->index % 6 }}" cx="80" cy="80" r="60" pathLength="100" stroke-dasharray="{{ $distributionPercent }} {{ 100 - $distributionPercent }}" stroke-dashoffset="{{ -$distributionOffset }}" />
                    @php $distributionOffset += $distributionPercent; @endphp
                @endforeach
            </svg>
            <div><strong>{{ $distributionTotal }}</strong><span>orders</span></div>
        </div>
        <dl class="workspace-chart-legend">
            @forelse ($values as $statusLabel => $statusCount)
                <div><dt><span class="workspace-chart-dot workspace-chart-color-{{ $loop->index % 6 }}" aria-hidden="true"></span>{{ ucwords(strtolower(str_replace('_', ' ', $statusLabel))) }}</dt><dd>{{ $statusCount }}</dd></div>
            @empty
                <div><dt>No orders to display yet.</dt><dd>0</dd></div>
            @endforelse
        </dl>
    </div>
</section>
