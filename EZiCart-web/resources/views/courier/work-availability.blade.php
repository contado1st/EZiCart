@extends('layouts.courier')
@section('workspace')
<header class="ops-heading"><div><h1>My work availability</h1><p>Choose whether you can take new local jobs or dedicated hub transfers.</p></div></header>
<x-rider-work-availability :rider="$rider" expanded />
@endsection
