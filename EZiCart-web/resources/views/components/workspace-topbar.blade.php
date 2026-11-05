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
    $workspaceUnread = $communicationCounts['notifications'];
@endphp
<a class="workspace-skip" href="#workspace-content">Skip to content</a>
<header class="workspace-topbar">
    <div class="workspace-brand">
        <a href="{{ $roleDashboardRoute ? route($roleDashboardRoute) : route($workspaceUser->role === 'seller' ? 'seller.dashboard' : 'buyer.dashboard') }}" aria-label="EZiCart dashboard">
            <img src="{{ asset('images/ezicart-logo.png') }}" alt="EZiCart">
        </a>
        <button class="workspace-icon-button workspace-menu-button" type="button" data-workspace-toggle aria-controls="workspace-sidebar" aria-expanded="true" aria-label="Toggle sidebar" title="Toggle sidebar">
            <svg class="workspace-hamburger" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>
    <div class="workspace-toolbar">
        <div class="workspace-breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>{{ $workspaceName }}</strong></div>
        <div class="workspace-toolbar-actions">
            <time class="workspace-date" datetime="{{ now()->toDateString() }}">{{ now()->format('D, d M Y') }}</time>
            <a class="workspace-primary-action" href="{{ route($workspaceActionRoute) }}">{{ $workspaceAction }}<x-shop-icon name="arrowback" /></a>
            <span class="workspace-toolbar-divider" aria-hidden="true"></span>
            <a class="workspace-icon-button" href="{{ route('chat.index') }}" data-message-drawer-toggle data-communication-label="Messages" aria-label="Messages{{ $communicationCounts['messages'] ? ', '.$communicationCounts['messages'].' unread' : '' }}" title="Messages"><x-shop-icon name="chat" /><x-communication-count kind="messages" :count="$communicationCounts['messages']" /></a>
            <a class="workspace-icon-button" href="{{ route('notifications.index') }}" data-communication-label="Notifications" aria-label="Notifications{{ $workspaceUnread ? ', '.$workspaceUnread.' unread' : '' }}" title="Notifications">
                <x-shop-icon name="bell" />
                <x-communication-count kind="notifications" :count="$workspaceUnread" />
            </a>
            <a class="workspace-avatar" href="{{ route('account.profile.edit') }}" aria-label="Your profile" title="{{ $workspaceUser->first_name }} {{ $workspaceUser->last_name }}">
                <x-profile-avatar :user="$workspaceUser" />
            </a>
        </div>
    </div>
</header>
