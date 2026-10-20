@php
    $workspaceUser = auth()->user();
    $workspaceName = match ($workspaceUser->role) {
        'buyer' => 'My purchases',
        'seller' => 'Seller workspace',
        'courier' => 'Courier workspace',
        'sorting_center' => 'Sorting center',
        'logistics_admin' => 'Logistics administration',
        default => 'Marketplace administration',
    };
    [$workspaceAction, $workspaceActionRoute] = match ($workspaceUser->role) {
        'buyer' => ['Browse marketplace', 'home'],
        'seller' => ['Add product', 'seller.products.create'],
        'courier' => ['Active parcels', 'courier.tracking'],
        'sorting_center' => ['Receive parcel', 'logistics.intake'],
        'logistics_admin' => ['Hub transfers', 'parcel-transfers.index'],
        default => ['Review applications', 'admin.registrations.index'],
    };
    $workspaceUnread = $workspaceUser->unreadNotifications()->count();
@endphp
<a class="workspace-skip" href="#workspace-content">Skip to content</a>
<header class="workspace-topbar">
    <a href="{{ $roleDashboardRoute ? route($roleDashboardRoute) : route($workspaceUser->role === 'seller' ? 'seller.dashboard' : 'buyer.dashboard') }}" class="workspace-brand" aria-label="EZiCart dashboard">
        <img src="{{ asset('images/ezicart-logo.png') }}" alt="EZiCart">
        <span>Workspace</span>
    </a>
    <div class="workspace-toolbar">
        <button class="workspace-icon-button workspace-menu-button" type="button" data-workspace-toggle aria-controls="workspace-sidebar" aria-expanded="true" aria-label="Toggle navigation">
            <x-shop-icon name="checklist" />
        </button>
        <div class="workspace-breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>{{ $workspaceName }}</strong></div>
        <div class="workspace-toolbar-actions">
            <time class="workspace-date" datetime="{{ now()->toDateString() }}">{{ now()->format('D, d M Y') }}</time>
            <a class="workspace-primary-action" href="{{ route($workspaceActionRoute) }}">{{ $workspaceAction }}<x-shop-icon name="arrowback" /></a>
            <span class="workspace-toolbar-divider" aria-hidden="true"></span>
            <a class="workspace-icon-button" href="{{ route('notifications.index') }}" aria-label="Notifications{{ $workspaceUnread ? ', '.$workspaceUnread.' unread' : '' }}" title="Notifications">
                <x-shop-icon name="bell" />
                @if ($workspaceUnread)<span class="workspace-unread" aria-hidden="true"></span>@endif
            </a>
            <a class="workspace-avatar" href="{{ route('account.profile.edit') }}" aria-label="Your profile" title="{{ $workspaceUser->first_name }} {{ $workspaceUser->last_name }}">
                {{ mb_strtoupper(mb_substr($workspaceUser->first_name, 0, 1).mb_substr($workspaceUser->last_name, 0, 1)) }}
            </a>
        </div>
    </div>
</header>
