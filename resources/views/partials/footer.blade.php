<footer class="ez-footer">
    <div class="ez-container-fluid">
        <div class="ez-footer-grid">
            <div class="ez-footer-brand">
                <a href="{{ route('home') }}" class="ez-logo">EZ<span>i</span>Cart</a>
                <p>Direct-to-consumer e-commerce infrastructure supporting local merchants, verified delivery couriers, and middle-mile logistics sorting hubs.</p>
            </div>
            <div>
                <h4 class="ez-footer-title">Marketplace</h4>
                <ul class="ez-footer-links">
                    <li><a href="#categories">All Categories</a></li>
                    <li><a href="#">Flash Sales</a></li>
                    <li><a href="#">Top Rated Items</a></li>
                </ul>
            </div>
            <div>
                <h4 class="ez-footer-title">Seller Hub</h4>
                <ul class="ez-footer-links">
                    <li><a href="{{ route('register.seller') }}">Become a Seller</a></li>
                    <li><a href="#">Merchant Dashboard</a></li>
                    <li><a href="#">Order Fulfillment Policy</a></li>
                </ul>
            </div>
            <div>
                <h4 class="ez-footer-title">Logistics & Partners</h4>
                <ul class="ez-footer-links">
                    <li><a href="{{ route('register.courier') }}">Apply as Courier Rider</a></li>
                    <li><a href="{{ route('register.sorting') }}">Sorting Hub Application</a></li>
                    <li><a href="#">Parcel Tracking System</a></li>
                </ul>
            </div>
        </div>
        <div class="ez-footer-bottom">
            <p>&copy; {{ date('Y') }} EZiCart Platform. Built for CS/IT Web Development Systems.</p>
        </div>
    </div>
</footer>