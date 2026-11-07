@extends('surveyor_portal.layouts.app')

@section('body')
@php
    $me = auth('surveyor')->user();
    $unread = \App\Models\Survey\SurveyorPortalNotification::visibleTo($me)->whereNull('read_at')->count();
@endphp
@push('styles')
    <style>
        .ks-portal-layout { display:grid; grid-template-columns:248px minmax(0,1fr); gap:24px; align-items:start; }
        .ks-portal-sidebar { position:sticky; top:88px; padding:16px; box-shadow:0 8px 24px rgba(20,23,26,.04); }
        .ks-sidebar-profile { display:flex; align-items:center; gap:11px; padding:4px 4px 16px; margin-bottom:14px; border-bottom:1px solid var(--ks-line); }
        .ks-sidebar-avatar { display:grid; place-items:center; flex:0 0 42px; width:42px; height:42px; border-radius:12px; background:#E8EDF5; color:var(--ks-blue); font-size:17px; font-weight:800; }
        .ks-sidebar-profile-name { overflow:hidden; color:var(--ks-ink); font-size:13px; font-weight:700; text-overflow:ellipsis; white-space:nowrap; }
        .ks-sidebar-profile-id { margin-top:2px; color:var(--ks-soft); font-size:11px; }
        .ks-sidebar-label { margin:0 0 8px 10px; color:#737B84; font-size:10px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
        .ks-sidebar-nav { display:flex; flex-direction:column; gap:5px; }
        .ks-sidebar-nav a { position:relative; display:flex; align-items:center; gap:11px; min-height:44px; padding:9px 11px; border-radius:9px; color:var(--ks-soft); font-size:13px; font-weight:650; text-decoration:none; transition:background .15s ease, color .15s ease; }
        .ks-sidebar-nav a svg { color:#737B84; }
        .ks-sidebar-nav a:hover { background:#F3F6FA; color:var(--ks-blue); }
        .ks-sidebar-nav a:hover svg { color:var(--ks-blue); }
        .ks-sidebar-nav a.on { background:#E8EDF5; color:var(--ks-blue); }
        .ks-sidebar-nav a.on::before { position:absolute; top:9px; bottom:9px; left:0; width:3px; border-radius:0 3px 3px 0; background:var(--ks-blue); content:""; }
        .ks-sidebar-nav a.on svg { color:var(--ks-blue); }
        .ks-sidebar-nav .ks-badge { margin-left:auto; }
        .ks-portal-content { min-width:0; }
        @media (max-width:760px) {
            .ks-portal-layout { grid-template-columns:minmax(0,1fr); gap:14px; }
            .ks-portal-sidebar { position:static; padding:12px; }
            .ks-sidebar-profile { display:none; }
            .ks-sidebar-label { margin:0 0 7px 4px; }
            .ks-sidebar-nav { flex-direction:row; flex-wrap:nowrap; gap:6px; overflow-x:auto; padding-bottom:2px; }
            .ks-sidebar-nav a { flex:0 0 auto; min-height:40px; padding:8px 10px; white-space:nowrap; }
            .ks-sidebar-nav a.on::before { top:auto; right:10px; bottom:0; left:10px; width:auto; height:3px; border-radius:3px 3px 0 0; }
        }
    </style>
@endpush
<header class="bg-white" style="border-bottom:1px solid var(--ks-line);">
    <div class="mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3" style="max-width:1120px;">
        <a href="{{ route('surveyor-portal.dashboard') }}" class="flex items-center gap-3" style="text-decoration:none;color:inherit;">
            <img src="{{ asset('assets/logo/knssgof-logo.png') }}" alt="KNSSGOF seal" width="44" height="44" style="width:44px;height:44px;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.25);">
            <span>
                <span class="block font-extrabold tracking-wide" style="color:var(--ks-blue);font-size:17px;line-height:1.1;">KNSSGOF</span>
                <span class="block ks-help" style="font-size:12px;">Surveyor General's Office Portal</span>
            </span>
        </a>
        <div class="flex items-center gap-3 text-sm">
            <div class="text-right">
                <div class="font-semibold">{{ $me->holder_name }}</div>
                <div class="ks-help">{{ $me->surveyor_id_no }}@if ($me->holder_name !== $me->name) · {{ $me->name }}@endif</div>
            </div>
            <form method="POST" action="{{ route('surveyor-portal.logout') }}">
                @csrf
                <button class="ks-btn ks-btn-light" style="padding:8px 12px;"><i data-lucide="log-out" class="w-4 h-4"></i> Sign out</button>
            </form>
        </div>
    </div>
</header>
<main class="mx-auto px-4 py-6" style="max-width:1280px;">
    <div class="ks-portal-layout">
        <aside class="ks-card ks-portal-sidebar" aria-label="Portal navigation">
            <div class="ks-sidebar-profile">
                <div class="ks-sidebar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</div>
                <div class="min-w-0">
                    <div class="ks-sidebar-profile-name">{{ $me->company_name ?: '—' }}</div>
                    <div class="ks-sidebar-profile-name">{{ $me->name }}</div>
                    <div class="ks-sidebar-profile-id">{{ $me->surveyor_id_no }}</div>
                </div>
            </div>
            <div class="ks-sidebar-label">Workspace</div>
            <nav class="ks-sidebar-nav">
                <a class="{{ request()->routeIs('surveyor-portal.dashboard') ? 'on' : '' }}" href="{{ route('surveyor-portal.dashboard') }}"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
                <a class="{{ request()->routeIs('surveyor-portal.requests.*') ? 'on' : '' }}" href="{{ route('surveyor-portal.requests.create') }}"><i data-lucide="plus-circle" class="w-4 h-4"></i> Requested Beacons</a>
                <a class="{{ request()->routeIs('surveyor-portal.notifications') ? 'on' : '' }}" href="{{ route('surveyor-portal.notifications') }}"><i data-lucide="bell" class="w-4 h-4"></i><span>Notifications</span> @if ($unread)<span class="ks-badge issued">{{ $unread }}</span>@endif</a>
            </nav>
        </aside>
        <section class="ks-portal-content">
            <h1 class="text-xl font-bold mb-4">@yield('heading')</h1>
            @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
            @if ($errors->any())
                <div class="ks-flash bad">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            @yield('main')
        </section>
    </div>
</main>
@endsection
