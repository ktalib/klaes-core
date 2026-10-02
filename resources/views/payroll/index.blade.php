@extends('layouts.app')
@section('page-title')
    {{ __('Payroll Management') }}
@endsection
 
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/payroll.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/payroll.js') }}" defer></script>
@endpush

@section('content')
    <div id="payroll-app" data-attendance-only="{{ request()->query('url') === 'attendance' ? '1' : '0' }}" class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="container mx-auto py-6 space-y-6">
            @include('payroll.partials.page-header')
            @include('payroll.partials.stats-cards')
            @include('payroll.partials.tab-panel')
        </div>

        @include('payroll.partials.dialogs')

        @include('admin.footer')
    </div>
@endsection