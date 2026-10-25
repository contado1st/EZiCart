@props(['variant' => 'dashboard'])
<div {{ $attributes->class(['ezi-skeleton'])->merge(['aria-hidden' => 'true']) }}>
    @if($variant === 'table-body')
        <div class="ezi-skeleton-table">@for($i = 0; $i < 6; $i++)<div>@for($j = 0; $j < 4; $j++)<span class="ezi-skeleton-bar"></span>@endfor</div>@endfor</div>
    @elseif($variant === 'form-block')
        <div class="ezi-skeleton-panel"><span class="ezi-skeleton-bar ezi-skeleton-title"></span>@for($i = 0; $i < 4; $i++)<div class="ezi-skeleton-field"><span class="ezi-skeleton-bar ezi-skeleton-short"></span><span class="ezi-skeleton-input"></span></div>@endfor<span class="ezi-skeleton-input ezi-skeleton-button"></span></div>
    @elseif($variant === 'product')
        <span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-product-detail"><div class="ezi-skeleton-image ezi-skeleton-gallery"></div><div><span class="ezi-skeleton-bar ezi-skeleton-short"></span><span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span><div class="ezi-skeleton-description">@for($i = 0; $i < 3; $i++)<span class="ezi-skeleton-bar"></span>@endfor</div><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input ezi-skeleton-button"></span></div></div>
    @elseif(in_array($variant, ['cart', 'checkout', 'order'], true))
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span>
        <div class="ezi-skeleton-split"><div>@if($variant === 'checkout')<x-loading-skeleton variant="form-block" />@else<x-loading-skeleton variant="table-body" />@endif</div><x-loading-skeleton variant="form-block" /></div>
    @elseif($variant === 'announcements')
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-announcements"><x-loading-skeleton variant="form-block" /><x-loading-skeleton variant="table-body" /></div>
    @elseif($variant === 'table')
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-toolbar"><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span></div><x-loading-skeleton variant="table-body" />
    @elseif($variant === 'analytics')
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-toolbar"><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span><span class="ezi-skeleton-input"></span></div><div class="ezi-skeleton-metrics">@for($i = 0; $i < 4; $i++)<div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span></div>@endfor</div><div class="ezi-skeleton-chart-grid"><x-loading-skeleton variant="charts" /><div class="ezi-skeleton-panel"><span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-ring"></div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar"></span></div></div>
    @elseif($variant === 'charts')
        <div class="ezi-skeleton-chart"><span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-plot"></div><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div>
    @elseif(in_array($variant, ['messages', 'thread', 'notifications'], true))
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-toolbar"><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar"></span></div><div @class(['ezi-skeleton-message-layout', 'ezi-skeleton-thread' => $variant === 'thread', 'ezi-skeleton-notifications' => $variant === 'notifications'])><x-loading-skeleton variant="message-list" /><div class="ezi-skeleton-panel">@if($variant === 'thread')@for($i = 0; $i < 5; $i++)<div @class(['ezi-skeleton-bubble', 'ezi-skeleton-bubble-reply' => $i % 2])><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div>@endfor<span class="ezi-skeleton-input"></span>@elseif($variant === 'notifications')<x-loading-skeleton variant="message-list" />@else<div class="ezi-skeleton-empty-thread"><span class="ezi-skeleton-avatar"></span><span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar"></span></div>@endif</div></div>
    @elseif($variant === 'message-list')
        <div class="ezi-skeleton-messages">@for($i = 0; $i < 5; $i++)<div class="ezi-skeleton-contact"><span class="ezi-skeleton-avatar"></span><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div></div>@endfor</div>
    @elseif(in_array($variant, ['form', 'auth', 'login'], true))
        <div @class(['ezi-skeleton-form', 'ezi-skeleton-form-wide' => $variant === 'form'])><span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-form-fields">@for($i = 0; $i < ($variant === 'login' ? 2 : 6); $i++)<div class="ezi-skeleton-field"><span class="ezi-skeleton-bar ezi-skeleton-short"></span><span class="ezi-skeleton-input"></span></div>@endfor</div><span class="ezi-skeleton-input ezi-skeleton-button"></span></div>
    @elseif(in_array($variant, ['search', 'storefront'], true))
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span>@if($variant === 'storefront')<div class="ezi-skeleton-image ezi-skeleton-hero"></div><x-loading-skeleton variant="catalog" />@else<div class="ezi-skeleton-search"><x-loading-skeleton variant="form-block" /><x-loading-skeleton variant="catalog" /></div>@endif
    @elseif($variant === 'catalog')
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span>
        <div class="ezi-skeleton-products">@for($i = 0; $i < 6; $i++)<div><div class="ezi-skeleton-image"></div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div>@endfor</div>
    @elseif(in_array($variant, ['dashboard', 'purchases'], true))
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span>
        <div class="ezi-skeleton-metrics">@for($i = 0; $i < 4; $i++)<div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-value"></span></div>@endfor</div>
        @if($variant === 'purchases')@for($i = 0; $i < 3; $i++)<div class="ezi-skeleton-purchase"><span class="ezi-skeleton-bar ezi-skeleton-short"></span><div class="ezi-skeleton-contact"><span class="ezi-skeleton-image"></span><div><span class="ezi-skeleton-bar"></span><span class="ezi-skeleton-bar ezi-skeleton-short"></span></div></div></div>@endfor @else<x-loading-skeleton variant="table-body" />@endif
    @else
        <span class="ezi-skeleton-bar ezi-skeleton-title"></span><div class="ezi-skeleton-panel">@for($i = 0; $i < 7; $i++)<span class="ezi-skeleton-bar"></span>@endfor</div>
    @endif
</div>
