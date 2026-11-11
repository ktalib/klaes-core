@extends('surveyor_portal.layouts.portal')

@section('title', 'My Map Requests — MLPP-SG Portal')
@section('heading', 'My Map Requests')

@section('main')
    <div class="flex justify-end mb-3">
        <a class="ks-btn ks-btn-primary" href="{{ route('surveyor-portal.map-requests.create') }}"><i data-lucide="plus" class="w-4 h-4"></i> Request Map</a>
    </div>
    @include('surveyor_portal.map_requests._table', ['rows' => $requests, 'first' => $requests->firstItem() ?? 1])
    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
