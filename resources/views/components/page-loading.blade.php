@props(['variant' => null])
@php
    $loadingProfiles = config('page-loading.profiles');
    $loadingProfileFor = static function ($name) use ($loadingProfiles) {
        foreach ($loadingProfiles as $shape => $names) {
            if ($name && \Illuminate\Support\Str::is($names, $name)) return $shape;
        }
        return 'content';
    };
    $loadingRoutes = [];
    foreach (\Illuminate\Support\Facades\Route::getRoutes() as $loadingRoute) {
        if (!in_array('GET', $loadingRoute->methods(), true)) continue;
        $shape = $loadingProfileFor($loadingRoute->getName());
        if ($shape !== 'content') $loadingRoutes[] = ['path' => request()->getBaseUrl().'/'.ltrim($loadingRoute->uri(), '/'), 'shape' => $shape];
    }
    $currentShape = $variant ?? $loadingProfileFor(request()->route()?->getName());
@endphp
<script type="application/json" data-loading-routes>@json($loadingRoutes)</script>
<div class="ezi-page-loading" data-page-loading data-loading-variant="{{ $currentShape }}" data-loading-current="{{ $currentShape }}" data-loading-scope="{{ substr(hash('sha256', session()->getId().'|'.(auth()->id() ?? 'guest')), 0, 16) }}" hidden>
    <p class="ezi-loading-status ezi-loading-sr" role="status" aria-live="polite">Loading page…</p>
    <div data-loading-stage aria-hidden="true"></div>
    @foreach([...array_keys($loadingProfiles), 'content'] as $shape)
        <template data-loading-template="{{ $shape }}"><x-loading-skeleton :variant="$shape" /></template>
    @endforeach
</div>
