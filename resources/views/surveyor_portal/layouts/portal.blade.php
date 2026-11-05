@extends('surveyor_portal.layouts.app')

@section('body')
@php
    $me = auth('surveyor')->user();
    $unread = \App\Models\Survey\SurveyorPortalNotification::where('surveyor_portal_account_id', $me->id)->whereNull('read_at')->count();
    $on = fn ($r) => request()->routeIs($r) ? 'on' : '';
@endphp
<header class="bg-white" style="border-bottom:1px solid var(--ks-line);">
    <div class="mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3" style="max-width:1120px;">
        <a href="{{ route('surveyor-portal.dashboard') }}"><img src="{{ asset('assets/logo/kssgof-wordmark.png') }}" alt="KSSGOF" style="height:44px;width:auto;"></a>
        <div class="flex items-center gap-3 text-sm">
            <div class="text-right">
                <div class="font-semibold">{{ $me->name }}</div>
                <div class="ks-help">{{ $me->surveyor_id_no }}@if ($me->company_name) · {{ $me->company_name }}@endif</div>
            </div>
            <form method="POST" action="{{ route('surveyor-portal.logout') }}">
                @csrf
                <button class="ks-btn ks-btn-light" style="padding:8px 12px;"><i data-lucide="log-out" class="w-4 h-4"></i> Sign out</button>
            </form>
        </div>
    </div>
    <nav class="ks-nav mx-auto px-4 pb-2 flex flex-wrap gap-1" style="max-width:1120px;">
        <a class="{{ $on('surveyor-portal.dashboard') }}" href="{{ route('surveyor-portal.dashboard') }}"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
        <a class="{{ $on('surveyor-portal.requests.create') }}" href="{{ route('surveyor-portal.requests.create') }}"><i data-lucide="plus-circle" class="w-4 h-4"></i> Request Beacons</a>
        <a class="{{ request()->routeIs('surveyor-portal.requests.index') || request()->routeIs('surveyor-portal.requests.show') ? 'on' : '' }}" href="{{ route('surveyor-portal.requests.index') }}"><i data-lucide="list" class="w-4 h-4"></i> My Requests</a>
        <a class="{{ $on('surveyor-portal.notifications') }}" href="{{ route('surveyor-portal.notifications') }}"><i data-lucide="bell" class="w-4 h-4"></i> Notifications @if ($unread)<span class="ks-badge issued">{{ $unread }}</span>@endif</a>
    </nav>
</header>
<main class="mx-auto px-4 py-6" style="max-width:1120px;">
    <h1 class="text-xl font-bold mb-4">@yield('heading')</h1>
    @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="ks-flash bad">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif
    @yield('main')
</main>
@endsection
