@extends('surveyor_portal.layouts.app')

{{-- Sign in, register and password pages. The wordmark is the banner, the seal the logo (user, 2026-11-05). --}}
@section('body')
@include('surveyor_portal.partials.public_nav')

<div class="grid lg:grid-cols-2" style="min-height:calc(100vh - 69px);">
    <aside class="hidden lg:flex ks-guest-banner flex-col items-center justify-center px-10 py-12">
        <img src="{{ asset('assets/logo/kssgof-wordmark.png') }}" alt="KSSGOF, Kano State Surveyor General's Office Portal" width="900" height="300"
             class="relative" style="width:100%;max-width:520px;height:auto;mix-blend-mode:multiply;">
        <ul class="relative ks-help" style="margin-top:22px;font-size:15px;line-height:1.5;">
            <li class="flex items-center gap-3 py-1.5"><i data-lucide="user-plus" class="w-5 h-5" style="color:var(--ks-blue);"></i> Register once, approved by the SG's office</li>
            <li class="flex items-center gap-3 py-1.5"><i data-lucide="map-pin" class="w-5 h-5" style="color:var(--ks-green);"></i> Request public or private beacon numbers</li>
            <li class="flex items-center gap-3 py-1.5"><i data-lucide="printer" class="w-5 h-5" style="color:#9A7414;"></i> Accept them and print your issue slip</li>
        </ul>
    </aside>

    <div class="flex items-center justify-center px-4 py-10" style="background:var(--ks-page);">
        <div class="w-full" style="max-width:@yield('width', '440px');">
            <img src="{{ asset('assets/logo/kssgof-wordmark.png') }}" alt="KSSGOF" width="900" height="300" class="mx-auto mb-5 lg:hidden" style="width:100%;max-width:280px;height:auto;">
            @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
            <div class="ks-card p-6" style="margin-top:34px;border-color:#C9CED4;box-shadow:0 10px 30px -18px rgba(11,58,126,.35);">
                <img src="{{ asset('assets/logo/kssgof-logo.png') }}" alt="" width="64" height="64" class="mx-auto" style="width:64px;height:64px;border-radius:50%;margin-top:-50px;margin-bottom:10px;box-shadow:0 0 0 4px #fff, 0 4px 12px rgba(0,0,0,.2);">
                @yield('card')
            </div>
        </div>
    </div>
</div>
@endsection
