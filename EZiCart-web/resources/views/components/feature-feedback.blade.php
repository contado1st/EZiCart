@if (session('success'))<p class="feature-panel" role="status">{{ session('success') }}</p>@endif
@if ($errors->any())<section class="feature-panel" role="alert"><h2>Check the following details</h2><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
