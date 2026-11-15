@extends('layouts.app')
@push('styles') @include('finance.partials.wallet-assets') @endpush
@section('content')
<main class="web-operations"><section class="dash-panel wallet-code-receipt">
    @include('finance.partials.code-receipt')
</section></main>
@endsection
