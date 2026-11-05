@extends('surveyor_portal.layouts.app')

@section('body')
<div class="min-h-screen grid lg:grid-cols-2">
    <div class="hidden lg:block" role="img" aria-label="Kano State Surveyor General's Office Portal" style="background:#0C0C0C url('{{ asset('assets/logo/kssgof-seal.jpg') }}') center / cover no-repeat;"></div>
    <div class="flex items-center justify-center px-4 py-10" style="background:#fff;">
        <div class="w-full" style="max-width:@yield('width', '440px');">
            <img src="{{ asset('assets/logo/kssgof-wordmark.png') }}" alt="KSSGOF" class="mx-auto mb-6" style="width:100%;max-width:300px;height:auto;">
            @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
            <div class="ks-card p-6" style="border-color:#C9CED4;">
                @yield('card')
            </div>
        </div>
    </div>
</div>
@endsection
