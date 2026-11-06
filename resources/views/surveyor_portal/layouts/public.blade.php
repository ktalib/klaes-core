@extends('surveyor_portal.layouts.app')

@section('body')
    @include('surveyor_portal.partials.public_nav')
    <main class="mx-auto px-4 py-6" style="max-width:1280px;">
        <h1 class="text-xl font-bold mb-4">@yield('heading')</h1>
        @if (session('status'))<div class="ks-flash ok">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="ks-flash bad">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif
        @yield('main')
    </main>
@endsection
