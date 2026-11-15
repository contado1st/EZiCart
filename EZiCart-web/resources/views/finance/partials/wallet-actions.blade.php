@php
    $walletAdmin = $walletTransfers->isAdmin($actor);
    $walletOwner = $actor->isCommerceAdmin() ? 'Platform treasury' : ($actor->isLogisticsAdmin() ? 'Logistics administration balance' : 'Your demo wallet');
@endphp
<section class="wallet-overview" aria-label="Wallet overview">
    <div><h2>{{ $walletOwner }}</h2><p class="wallet-overview-balance">₱<span data-wallet-available>{{ number_format($available->balance_cents / 100, 2) }}</span></p><p>{{ $actor->isCommerceAdmin() ? 'Shared platform treasury; eligible for spending' : ($actor->isLogisticsAdmin() ? 'Available administration funds' : 'Your available sandbox funds') }} · PHP demo</p>
        <p>{{ $walletAdmin ? 'Administration funds are separate from user wallets. Sending debits this available balance; issuing a code creates demo credits only when redeemed.' : 'Spendable demo credits. Pending earnings, reserved vouchers and held funds are shown separately below.' }}</p>
    </div>
    <nav class="wallet-quick-actions" aria-label="Wallet actions">
        <a href="#wallet-send" data-task-open="wallet-send"><x-shop-icon name="arrowback" /><span>{{ $walletAdmin ? 'Allocate funds' : 'Send' }}</span></a>
        @if($canReceive)<a href="#wallet-receive" data-task-open="wallet-receive"><x-shop-icon name="qr-code" /><span>Receive</span></a><a href="#wallet-redeem" data-task-open="wallet-redeem"><x-shop-icon name="voucher" /><span>Redeem code</span></a>@endif
        @if($walletAdmin)<a href="#wallet-issue-code" data-task-open="wallet-issue-code"><x-shop-icon name="voucher" /><span>Issue demo code</span></a>@endif
    </nav>
</section>
@unless($demoEnabled)<p class="web-muted">Demo transfers and funding are currently disabled. Existing financial history is still available.</p>@endunless
<section id="wallet-send" class="dash-panel" data-task-panel>
    <h2>{{ $walletAdmin ? 'Allocate existing admin funds' : 'Send demo funds' }}</h2>
    @if($walletAdmin)<p>Allocations can go to approved buyer, seller, rider and hub wallets. An empty administration balance must be funded through the existing independent review process before it can send funds.</p>@endif
    <div data-wallet-task-content>
        <p>Ask the recipient for their Receive reference. Review their identity and the amount before confirming. This uses available funds only. To pay for an order, use checkout or its To Pay action.</p>
        <form class="web-form" method="POST" action="{{ route('wallet.transfer.preview') }}" data-wallet-review>
            @csrf<input type="hidden" name="request_key" value="{{ Str::uuid() }}">
            <label for="wallet-recipient">Recipient Receive reference<input id="wallet-recipient" name="recipient_reference" data-qr-input required maxlength="100" placeholder="EZW:…" autocomplete="off"></label>
            <x-qr-camera-controls />
            <label for="wallet-send-amount">Amount PHP<input id="wallet-send-amount" name="amount" type="number" min="0.01" max="1000000" step="0.01" required></label>
            <label for="wallet-send-note">Note (optional)<input id="wallet-send-note" name="note" maxlength="200"></label>
            <button type="submit" class="dash-btn-sm dash-btn-primary" @disabled(!$demoEnabled || $available->frozen)>Review recipient and amount</button>
        </form>
    </div>
</section>
@if($canReceive)
<section id="wallet-receive" class="dash-panel" data-task-panel>
    <h2>Receive demo funds</h2><p>{{ $actor->first_name }} {{ $actor->last_name }} · {{ str_replace('_', ' ', $actor->role) }}</p>
    <div class="wallet-receive-qr">{!! $receiveQr !!}</div>
    <label for="wallet-receive-reference">Your Receive reference<input id="wallet-receive-reference" value="{{ $receiveReference }}" readonly></label>
    <button type="button" class="dash-btn-sm dash-btn-outline" data-wallet-copy="wallet-receive-reference">Copy reference</button>
    <p>This reference identifies your wallet. It does not grant access, include your balance, or replace sender confirmation.</p>
</section>
<section id="wallet-redeem" class="dash-panel" data-task-panel>
    <h2>Redeem a demo funding code</h2>
    <div data-wallet-task-content>
    <p>Only the recipient named by the issuing administrator can redeem the code. A redeemed code cannot add funds again.</p>
    <form class="web-form" method="POST" action="{{ route('wallet.codes.redeem') }}" data-wallet-redeem data-decision="demo-code-redeem" data-decision-title="Redeem this demo code?" data-decision-message="The code must name your account and be unused and unexpired. Funds are posted once after server verification." data-decision-label="Redeem code" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        @csrf<label for="wallet-redeem-code">Demo funding code<input id="wallet-redeem-code" name="code" data-qr-input required maxlength="100" placeholder="EZD-…" autocomplete="off"></label>
        <x-qr-camera-controls />
        <button type="submit" class="dash-btn-sm dash-btn-primary" @disabled(!$demoEnabled || !$fundingCodesReady || $available->frozen)>Redeem code</button>
    </form>
    </div>
</section>
@endif
@if($walletAdmin)
<section id="wallet-issue-code" class="dash-panel" data-task-panel>
    <h2>Issue a recipient-bound demo code</h2>
    <div data-wallet-task-content>
    <p>This creates school-testing credits on redemption. It does not debit the admin balance. Use Allocate funds to send an existing balance instead. Commerce admins issue to buyers/sellers; logistics admins issue to riders/hubs.</p>
    <form class="web-form" method="POST" action="{{ route('wallet.codes.issue') }}" data-wallet-issue data-decision="demo-code-issue" data-decision-title="Issue this demo funding code?" data-decision-message="Check the exact recipient reference, amount and expiry. The recipient balance changes only when the code is redeemed." data-decision-label="Issue demo code" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
        @csrf<input type="hidden" name="request_key" value="{{ Str::uuid() }}">
        <label for="code-recipient">Recipient Receive reference<input id="code-recipient" name="recipient_reference" required maxlength="100" placeholder="EZW:…" autocomplete="off"></label>
        <label for="code-amount">Amount PHP<input id="code-amount" name="amount" type="number" min="0.01" max="1000000" step="0.01" required></label>
        <label for="code-expiry">Expiry<select id="code-expiry" name="expires_minutes"><option value="60">1 hour</option><option value="1440" selected>24 hours</option><option value="10080">7 days</option></select></label>
        <label for="code-reason">Issuance reason<textarea id="code-reason" name="reason" minlength="5" maxlength="500" required rows="2"></textarea></label>
        <button type="submit" class="dash-btn-sm dash-btn-primary" @disabled(!$demoEnabled || !$fundingCodesReady || $available->frozen)>Issue demo code</button>
    </form>
    </div>
</section>
@endif
