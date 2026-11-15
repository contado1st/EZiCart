@php
    $activeSection = $activeSection ?? 'profile';
    $buyer = auth()->user();
    $unreadNotificationCount = $buyer->unreadNotifications()->count();
@endphp

<aside class="buyer-account-sidebar" aria-label="My Account navigation">
    <div class="buyer-account-person">
        <x-profile-avatar :user="$buyer" class="buyer-account-avatar" aria-hidden="true" />
        <div>
            <strong>{{ $buyer->first_name }} {{ $buyer->last_name }}</strong>
            <a href="{{ route('account.profile.edit') }}#buyer-profile">Edit profile</a>
        </div>
    </div>

    <nav class="buyer-account-navigation">
        <details class="buyer-account-nav-group buyer-account-nav-dropdown" open>
            <summary class="buyer-account-nav-heading buyer-account-nav-trigger" title="Click to collapse or expand My Account">
                <span>My Account</span>
                <x-shop-icon name="chevron-down" class="workspace-dropdown-chevron" />
            </summary>
            <div class="buyer-account-nav-content">
                <a class="{{ $activeSection === 'profile' ? 'is-active' : '' }}" href="{{ route('account.profile.edit') }}" @if ($activeSection === 'profile') aria-current="page" @endif>Profile</a>
                <a href="{{ route('buyer.addresses.index') }}">Addresses</a>
                <a href="{{ route('account.profile.edit') }}#buyer-password">Change password</a>
                <a class="buyer-account-notification-link {{ $activeSection === 'notifications' ? 'is-active' : '' }}" href="{{ route('notifications.index') }}" @if ($activeSection === 'notifications') aria-current="page" @endif>
                    <span>Notifications</span>
                    @if ($unreadNotificationCount > 0)
                        <span class="buyer-account-notification-count" aria-label="{{ $unreadNotificationCount }} unread">{{ $unreadNotificationCount }}</span>
                    @endif
                </a>
            </div>
        </details>

        <details class="buyer-account-nav-group buyer-account-nav-dropdown" open>
            <summary class="buyer-account-nav-heading buyer-account-nav-trigger" title="Click to collapse or expand My EZiCart">
                <span>My EZiCart</span>
                <x-shop-icon name="chevron-down" class="workspace-dropdown-chevron" />
            </summary>
            <div class="buyer-account-nav-content">
                <a class="{{ $activeSection === 'purchases' ? 'is-active' : '' }}" href="{{ route('buyer.dashboard') }}" @if ($activeSection === 'purchases') aria-current="page" @endif>My purchases</a>
                <a href="{{ route('cart.index') }}">My cart</a>
                <a href="{{ route('buyer.wishlist.index') }}">My wishlist</a>
            </div>
        </details>
    </nav>

    <form action="{{ route('logout') }}" method="POST" class="buyer-account-sidebar-logout" data-decision="logout" data-decision-title="Sign out?" data-decision-message="You will leave your current session. You can sign in again when you are ready." data-decision-label="Sign out" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        @csrf
        <button type="submit">Log out</button>
    </form>
</aside>
