@php
    $sidebarUser = auth()->user();
    $navigation = match ($sidebarUser->role) {
        'buyer' => [
            'Shopping' => [
                ['buyer.dashboard', 'diagram', 'My purchases', 'buyer.dashboard'],
                ['home', 'package1', 'Marketplace', 'home'],
                ['buyer.for-you', 'star', 'For you', 'buyer.for-you'],
                ['buyer.stock.index', 'bell', 'Stock alerts', 'buyer.stock.*'],
                ['chat.index', 'chat', 'Chat', 'chat.index'],
                ['cart.index', 'cart2', 'My cart', 'cart.*'],
                ['finance.index', 'invoice', 'Financial records', 'finance.*'],
            ],
        ],
        'seller' => [
            'Store management' => [
                ['seller.dashboard', 'diagram', 'Overview', 'seller.dashboard'],
                ['seller.orders.index', 'cart1', 'Orders', 'seller.orders.*'],
                ['seller.products.index', 'package1', 'Products & inventory', 'seller.products.*'],
                ['seller.vouchers.index', 'voucher', 'Vouchers', 'seller.vouchers.*'],
                ['seller.reports.index', 'note', 'Reports', 'seller.reports.*'],
                ['sales-reports.index', 'note', 'Sales exports', 'sales-reports.*'],
                ['chat.index', 'chat', 'Chat', 'chat.index'],
                ['finance.index', 'money', 'Financial records', 'finance.*'],
            ],
        ],
        'courier' => [
            'Communication' => [
                ['chat.index', 'chat', 'Messages', 'chat.index'],
            ],
            'Delivery operations' => [
                ['courier.dashboard', 'diagram', 'Work board', 'courier.dashboard'],
                ['courier.tracking', 'delivery1', 'Active parcels', 'courier.tracking'],
                ['courier.history', 'checklist', 'Delivery history', 'courier.history'],
                ['courier.earnings', 'money', 'Earnings', 'courier.earnings'],
                ['finance.index', 'invoice', 'Cash remittances', 'finance.*'],
            ],
        ],
        'sorting_center' => [
            'Hub operations' => [
                ['logistics.dashboard', 'diagram', 'Overview', 'logistics.dashboard'],
                ['logistics.pickupRequests', 'delivery1', 'Pickup requests', 'logistics.pickupRequests'],
                ['logistics.intake', 'package1', 'Parcel intake', 'logistics.intake'],
                ['logistics.sorting', 'checklist', 'Sorting queue', 'logistics.sorting'],
                ['logistics.dispatch', 'delivery1', 'Rider dispatch', 'logistics.dispatch'],
                ['logistics.tracking', 'location', 'Delivery tracking', 'logistics.tracking'],
            ],
            'Hub management' => [
                ['logistics.storage', 'package1', 'Storage locations', 'logistics.storage'],
                ['parcel-transfers.index', 'delivery1', 'Hub transfers', 'parcel-transfers.*'],
                ['logistics.scan-history', 'note', 'Scan history', 'logistics.scan-history'],
                ['logistics.riders', 'account', 'Riders', 'logistics.riders'],
                ['logistics.areas', 'location', 'Routing areas', 'logistics.areas*'],
                ['coverage.index', 'location', 'Barangays & vehicles', 'coverage.*'],
                ['logistics.reports', 'diagram', 'Reports', 'logistics.reports'],
            ],
        ],
        'logistics_admin' => [
            'Logistics management' => [
                ['logistics-admin.dashboard', 'diagram', 'Control tower', 'logistics-admin.dashboard'],
                ['chat.index', 'chat', 'Chat', 'chat.*'],
                ['logistics-admin.dashboard#applications', 'shield', 'Applications', ''],
                ['logistics-admin.dashboard#service-areas', 'location', 'Service areas', ''],
                ['coverage.index', 'location', 'Coverage & shipping', 'coverage.*'],
                ['activity-logs.index', 'note', 'Activity logs', 'activity-logs.*'],
                ['logistics-admin.dashboard#managed-accounts', 'account', 'Centers & riders', ''],
                ['logistics-admin.dashboard#parcels', 'package1', 'Parcel oversight', ''],
                ['parcel-transfers.index', 'delivery1', 'Hub transfers', 'parcel-transfers.*'],
                ['finance.index', 'money', 'COD & remittances', 'finance.*'],
            ],
        ],
        default => [
            'Marketplace' => [
                ['admin.dashboard', 'diagram', 'Overview', 'admin.dashboard'],
                ['chat.index', 'chat', 'Chat', 'chat.*'],
                ['admin.registrations.index', 'shield', 'User approvals', 'admin.registrations.*'],
                ['admin.compliance.products.index', 'package1', 'Product compliance', 'admin.compliance.*'],
                ['admin.disputes.index', 'warning', 'Disputes', 'admin.disputes.*'],
                ['admin.vouchers.index', 'voucher', 'Platform vouchers', 'admin.vouchers.*'],
                ['sales-reports.index', 'note', 'Sales exports', 'sales-reports.*'],
                ['activity-logs.index', 'note', 'Activity logs', 'activity-logs.*'],
                ['finance.index', 'money', 'Refunds & payouts', 'finance.*'],
            ],
            'Administration' => [
                ['admin.reports.index', 'diagram', 'Reports', 'admin.reports.*'],
                ['admin.moderation.index', 'shield', 'Account moderation', 'admin.moderation.*'],
                ['admin.announcements.index', 'megaphone', 'Announcements', 'admin.announcements.*'],
            ],
        ],
    };
    $navigation['Insights'] = [['analytics.index', 'diagram', 'Analytics', 'analytics.*']];
    $sidebarRole = match ($sidebarUser->role) {
        'admin', 'ecommerce_admin' => 'E-commerce admin',
        'logistics_admin' => 'Logistics admin',
        'sorting_center' => 'Sorting center',
        default => ucfirst($sidebarUser->role),
    };
@endphp
<aside class="workspace-sidebar" id="workspace-sidebar" aria-label="{{ $sidebarRole }} navigation">
    <div class="workspace-sidebar-heading"><span class="workspace-role-dot" aria-hidden="true"></span><span class="workspace-nav-label">{{ $sidebarRole }}</span><button type="button" class="workspace-icon-button workspace-mobile-close" data-workspace-close aria-label="Close navigation"><x-shop-icon name="close" /></button></div>
    <nav class="workspace-navigation">
        @foreach ($navigation as $group => $items)
            <div class="workspace-nav-group"><p class="workspace-nav-heading">{{ $group }}</p>
                @foreach ($items as [$destination, $icon, $label, $pattern])
                    @php
                        [$destinationRoute, $destinationAnchor] = array_pad(explode('#', $destination, 2), 2, null);
                        $isActive = $pattern !== '' && request()->routeIs($pattern);
                    @endphp
                    <a href="{{ route($destinationRoute) }}{{ $destinationAnchor ? '#'.$destinationAnchor : '' }}" @class(['workspace-nav-link', 'is-active' => $isActive]) @if ($isActive) aria-current="page" @endif title="{{ $label }}" @if($destinationRoute === 'chat.index') data-communication-label="{{ $label }}" aria-label="{{ $label }}{{ $communicationCounts['messages'] ? ', '.$communicationCounts['messages'].' unread' : '' }}" @else aria-label="{{ $label }}" @endif><x-shop-icon :name="$icon" /><span class="workspace-nav-label">{{ $label }}</span>@if($destinationRoute === 'chat.index')<x-communication-count kind="messages" :count="$communicationCounts['messages']" />@endif</a>
                @endforeach
            </div>
        @endforeach
        <div class="workspace-nav-group"><p class="workspace-nav-heading">Account</p>
            @if($sidebarUser->role === 'seller' && $sidebarUser->status === 'approved')<a class="workspace-nav-link" href="{{ route('shops.show', $sidebarUser) }}" title="View my shop"><x-shop-icon name="package1" /><span class="workspace-nav-label">View my shop</span></a>@endif
            <a href="{{ route('notifications.index') }}" @class(['workspace-nav-link', 'is-active' => request()->routeIs('notifications.*')]) title="Notifications" data-communication-label="Notifications" aria-label="Notifications{{ $communicationCounts['notifications'] ? ', '.$communicationCounts['notifications'].' unread' : '' }}"><x-shop-icon name="bell" /><span class="workspace-nav-label">Notifications</span><x-communication-count kind="notifications" :count="$communicationCounts['notifications']" /></a>
            <a href="{{ route('account.profile.edit') }}" @class(['workspace-nav-link', 'is-active' => request()->routeIs('account.profile.*')]) title="Account & profile"><x-shop-icon name="settings" /><span class="workspace-nav-label">Account & profile</span></a>
            @if ($sidebarUser->role === 'buyer')
                <a href="{{ route('buyer.addresses.index') }}" @class(['workspace-nav-link', 'is-active' => request()->routeIs('buyer.addresses.*')]) title="Addresses" @if (request()->routeIs('buyer.addresses.*')) aria-current="page" @endif><x-shop-icon name="location" /><span class="workspace-nav-label">Addresses</span></a>
                <a href="{{ route('account.profile.edit') }}#buyer-password" class="workspace-nav-link" title="Change password"><x-shop-icon name="lock" /><span class="workspace-nav-label">Change password</span></a>
            @endif
        </div>
    </nav>
    <div class="workspace-sidebar-footer">
        <a href="{{ route('account.profile.edit') }}" class="workspace-user-link"><span class="workspace-avatar"><x-profile-avatar :user="$sidebarUser" /></span><span class="workspace-nav-label"><strong>{{ $sidebarUser->first_name }} {{ $sidebarUser->last_name }}</strong><small>{{ $sidebarRole }}</small></span></a>
        <form action="{{ route('logout') }}" method="POST" data-decision="logout" data-decision-title="Sign out?" data-decision-message="You will leave your current session. You can sign in again when you are ready." data-decision-label="Sign out" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">@csrf<button type="submit" class="workspace-icon-button" title="Sign out" aria-label="Sign out"><x-shop-icon name="arrowback" /></button></form>
    </div>
</aside>
