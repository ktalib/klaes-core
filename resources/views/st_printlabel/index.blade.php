@extends('layouts.app')

@section('page-title')
    {{ __('ST Print File Labels') }}
@endsection

@section('content')
    @include('st_printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('st_printlabel.partials.page')

        @include('admin.footer')
    </div>
@endsection


@section('footer-scripts')
    @parent
    @include('st_printlabel.assets.js')
@endsection
