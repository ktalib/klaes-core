@extends('layouts.app')

@section('page-title')
    {{ __('KANGIS Print File Labels') }}
@endsection

@section('content')
    @include('kangis_printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('kangis_printlabel.partials.page')

        @include('admin.footer')
    </div>
@endsection


@section('footer-scripts')
    @parent
    @include('kangis_printlabel.assets.js')
@endsection