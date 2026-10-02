@extends('layouts.app')

@section('page-title')
    {{ __('SLTR Print File Labels') }}
@endsection

@section('content')
    @include('sltr_printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('sltr_printlabel.partials.page')

        @include('admin.footer')
    </div>
@endsection


@section('footer-scripts')
    @parent
    @include('sltr_printlabel.assets.js')
@endsection