@extends('surveyor_portal.layouts.portal')

@section('title', 'Dashboard — KNSSGOF Portal')
@section('heading', 'Dashboard')

@section('main')
    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="ks-card p-4"><div class="ks-help">Waiting for the SG</div><div class="text-2xl font-bold">{{ number_format($stats['submitted']) }}</div></div>
        <div class="ks-card p-4"><div class="ks-help">Ready to Accept</div><div class="text-2xl font-bold">{{ number_format($stats['ready']) }}</div></div>
        <div class="ks-card p-4"><div class="ks-help">Accepted</div><div class="text-2xl font-bold">{{ number_format($stats['accepted']) }}</div></div>
    </div>
    <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
        <h2 class="font-bold">Latest Requests</h2>
        <a class="ks-btn ks-btn-primary" href="{{ route('surveyor-portal.requests.create') }}"><i data-lucide="plus" class="w-4 h-4"></i> Requested Beacons</a>
    </div>
    @include('surveyor_portal.requests._table', ['rows' => $latest, 'first' => 1])
@endsection
