<section id="wallet-codes" class="dash-panel">
    <h2>Demo funding code receipts</h2><p>Issuance does not change a balance. Redeemed receipts show the corresponding ledger reference. Only the issuer and named recipient can open each receipt.</p>
    @if(!$fundingCodesReady)<p role="status">Funding codes are awaiting the database update.</p>
    @else
    <div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Receipt</th><th>Recipient / issuer</th><th>Demo PHP</th><th>State</th><th>Expiry</th></tr></thead><tbody>
        @forelse($fundingCodes as $code)<tr><td><a href="{{ route('wallet.codes.receipt', $code->reference) }}">{{ $code->reference }}</a></td><td>{{ $code->recipient->first_name }} {{ $code->recipient->last_name }}<br>Issued by {{ $code->issuer->first_name }} {{ $code->issuer->last_name }}</td><td>{{ number_format($code->amount_cents / 100, 2) }}</td><td>{{ $code->state() }}</td><td>{{ $code->expires_at->timezone('Asia/Manila')->format('d M Y h:i A T') }}</td></tr>@empty<tr><td colspan="5">No demo funding codes on this page.</td></tr>@endforelse
    </tbody></table></div>{{ $fundingCodes->links() }}
    @endif
</section>
