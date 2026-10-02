@extends('layouts.app')

@section('page-title')
    {{ __('Print File Labels') }}
@endsection

@section('content')
    @include('printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('printlabel.partials.page', [
            'availableFilesCount' => $availableFilesCount ?? 0,
            'showOnlyST' => $showOnlyST ?? false,
        ])

        @include('admin.footer')
    </div>
@endsection

@section('footer-scripts')
    @parent
    @include('printlabel.assets.js')
@endsection