@extends('layouts.app')
@push('styles') @include('finance.partials.wallet-assets') @endpush
@section('content')
<main class="web-operations"><section class="dash-panel">@include('finance.partials.transfer-confirmation')<a class="dash-btn-sm dash-btn-outline" href="{{ route('finance.wallet') }}">Change recipient or amount</a></section></main>
@endsection
