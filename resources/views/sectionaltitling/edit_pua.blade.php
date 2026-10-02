@extends('layouts.app')
@section('page-title')
    {{ __('Edit Parented Unit Application') }}
@endsection

@include('sectionaltitling.sub_application_form', ['isEdit' => true, 'isSUA' => false])