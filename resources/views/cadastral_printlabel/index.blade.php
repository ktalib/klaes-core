@extends('layouts.app')

@section('page-title')
    {{ __('Cadastral File Labels') }}
@endsection

@section('content')
    @include('cadastral_printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('cadastral_printlabel.partials.page', [
            'availableFilesCount' => $availableFilesCount ?? 0,
            'showOnlyST' => $showOnlyST ?? false,
        ])

        @include('admin.footer')
    </div>
@endsection


@section('footer-scripts')
    @parent
    @include('cadastral_printlabel.assets.js')
@endsection