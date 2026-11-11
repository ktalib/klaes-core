@extends('layouts.app')

@section('page-title')
    {{ __('Survey Print File Labels') }}
@endsection

@section('content')
    @include('survey_printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('survey_printlabel.partials.page')

        @include('admin.footer')
    </div>
@endsection


@section('footer-scripts')
    @parent
    @include('survey_printlabel.assets.js')
@endsection