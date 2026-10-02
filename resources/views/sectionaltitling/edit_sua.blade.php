@extends('layouts.app')
@section('page-title')
    {{ __('Edit Standalone Unit Application') }}
@endsection

@include('sectionaltitling.sub_application_form', ['isEdit' => true, 'isSUA' => true])