<section class="wallet-posted-receipt" aria-label="Posted demo transfer">
    <h2>Demo transfer posted</h2><p class="wallet-receipt-amount">PHP {{ number_format($amount_cents / 100, 2) }}</p>
    <dl class="wallet-receipt-details"><div><dt>From</dt><dd>{{ $sender_name }}</dd></div><div><dt>To</dt><dd>{{ $recipient_name }}</dd></div><div><dt>Reference</dt><dd>{{ $reference }}</dd></div><div><dt>Posted</dt><dd><time datetime="{{ $posted_at }}">{{ \Illuminate\Support\Carbon::parse($posted_at)->timezone('Asia/Manila')->format('d M Y h:i A T') }}</time></dd></div><div><dt>Operation</dt><dd>{{ $type === 'admin_wallet_allocation' ? 'Admin allocation' : 'User wallet transfer' }}</dd></div>@if($note)<div><dt>Note</dt><dd>{{ $note }}</dd></div>@endif</dl>
    <p>Posted once in the EZiCart demo ledger. This receipt does not represent a bank or external e-wallet transfer.</p>
    <a href="{{ route('wallet.transfer.receipt', $reference) }}" class="dash-btn-sm dash-btn-outline">Open receipt</a>
    <a href="{{ route('finance.wallet') }}" class="dash-btn-sm dash-btn-outline">Finance &amp; Wallet</a>
</section>
