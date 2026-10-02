@extends('email.layouts.master')

@section('content')
    <div>
        {!! $data['message'] !!}
    </div>
    <hr class="divider">
    <p class="muted" style="font-size: 12px; margin-top: 20px;">
        This is an automated notification from {{ env('APP_NAME') }}.
    </p>
@endsection
