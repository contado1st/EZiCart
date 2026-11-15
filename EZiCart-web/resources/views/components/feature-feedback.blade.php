@if (session('success'))
    <div class="feature-panel feature-feedback feature-feedback--success" role="status">
        <x-shop-icon name="checklist" />
        <p>{{ session('success') }}</p>
    </div>
@endif
@if ($errors->any())
    <section class="feature-panel feature-feedback feature-feedback--error" role="alert">
        <h2>Check the following details</h2>
        <p>Review the details below before trying again.</p>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </section>
@endif
