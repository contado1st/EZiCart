@extends('layouts.app')
@push('styles') @include('finance.partials.wallet-assets') @endpush
@section('content')
<main class="web-operations"><section class="dash-panel">@include('finance.partials.transfer-receipt')</section></main>
@endsection
