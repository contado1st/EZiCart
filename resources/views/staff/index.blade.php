@extends('layouts.app')
@section('content')
<main class="web-operations"><header><h1>Staff profiles</h1><p>Verified operational profiles for you and your related delivery team.</p></header>
@include('components.feature-feedback')
<section class="dash-panel"><div class="dash-table-wrapper"><table class="dash-table"><thead><tr><th>Staff member</th><th>Role and status</th><th>Service areas</th><th>Profile</th></tr></thead><tbody>
@forelse($staff as $person)<tr><td>{{ $person->first_name }} {{ $person->last_name }}</td><td>{{ str_replace('_', ' ', $person->role) }} · {{ $person->status }}</td><td>{{ $person->serviceAreas->pluck('name')->join(', ') ?: 'No area recorded' }}</td><td><a class="dash-btn-sm" href="{{ route('staff.show', $person) }}">View profile</a></td></tr>@empty<tr><td colspan="4">No related staff profiles are available.</td></tr>@endforelse
</tbody></table></div></section></main>
@endsection
