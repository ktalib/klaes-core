@extends('surveyor_portal.layouts.portal')

@section('title', 'My Requests — MLPP-SG Portal')
@section('heading', 'My Requests')

@section('main')
    @include('surveyor_portal.requests._table', ['rows' => $requests, 'first' => $requests->firstItem() ?? 1])
    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
