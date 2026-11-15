<section class="buyer-shelf buyer-for-you" id="marketplace-for-you" aria-labelledby="marketplace-for-you-title">
    <header class="buyer-shelf-heading">
        <div>
            <h2 id="marketplace-for-you-title">For you</h2>
            <p class="buyer-for-you-summary">Your top 5 recommendations based on categories you browse and purchase. New shoppers see the latest available listings.</p>
        </div>
        <form method="POST" action="{{ route('buyer.for-you.reset') }}"
            data-decision="buyer.for-you.reset" data-decision-title="Clear browsing history?"
            data-decision-message="This clears the browsing history used for your recommendations."
            data-decision-label="Clear history" data-decision-tone="danger"
            onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
            @csrf
            @method('DELETE')
            <button class="buyer-for-you-reset" type="submit">Clear browsing history</button>
        </form>
    </header>
    @include('components.feature-feedback')
    @if ($products->isEmpty())
        <div class="buyer-empty-state" role="status"><p>No products are currently available.</p></div>
    @else
        <div class="storefront-product-grid">
            @foreach ($products as $product)
                <x-catalog-product-card :product="$product" />
            @endforeach
        </div>
    @endif
</section>
