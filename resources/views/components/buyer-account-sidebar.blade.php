@php
    $activeSection = $activeSection ?? 'profile';
    $buyer = auth()->user();
    $unreadNotificationCount = $buyer->unreadNotifications()->count();
@endphp

<aside class="buyer-account-sidebar" aria-label="My Account navigation">
    <div class="buyer-account-person">
        <span class="buyer-account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($buyer->first_name, 0, 1) . mb_substr($buyer->last_name, 0, 1)) }}</span>
        <div>
            <strong>{{ $buyer->first_name }} {{ $buyer->last_name }}</strong>
            <a href="{{ route('account.profile.edit') }}#buyer-profile">Edit profile</a>
        </div>
    </div>

    <nav>
        <p class="buyer-account-nav-heading">My Account</p>
        <a class="{{ $activeSection === 'profile' ? 'is-active' : '' }}" href="{{ route('account.profile.edit') }}" @if ($activeSection === 'profile') aria-current="page" @endif>Profile</a>
        <a href="{{ route('buyer.addresses.index') }}">Addresses</a>
        <a href="{{ route('account.profile.edit') }}#buyer-password">Change password</a>
        <a class="buyer-account-notification-link {{ $activeSection === 'notifications' ? 'is-active' : '' }}" href="{{ route('notifications.index') }}" @if ($activeSection === 'notifications') aria-current="page" @endif>
            <span>Notifications</span>
            @if ($unreadNotificationCount > 0)
                <span class="buyer-account-notification-count" aria-label="{{ $unreadNotificationCount }} unread">{{ $unreadNotificationCount }}</span>
            @endif
        </a>

        <p class="buyer-account-nav-heading">My EZiCart</p>
        <a class="{{ $activeSection === 'purchases' ? 'is-active' : '' }}" href="{{ route('buyer.dashboard') }}" @if ($activeSection === 'purchases') aria-current="page" @endif>My purchases</a>
        <a href="{{ route('cart.index') }}">My cart</a>
    </nav>

    <form action="{{ route('logout') }}" method="POST" class="buyer-account-sidebar-logout" data-decision="logout" data-decision-title="Sign out?" data-decision-message="You will leave your current session. You can sign in again when you are ready." data-decision-label="Sign out" data-decision-tone="normal" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        @csrf
        <button type="submit">Log out</button>
    </form>
</aside>
