@extends('email.layouts.master')

@section('content')
    <h1 style="color: #1e3a5f; font-size: 24px; margin-bottom: 20px;">{{ $data['subject'] ?? 'Document' }}</h1>
    
    <div style="line-height: 1.8; color: #4b5563;">
        {!! $data['message'] !!}
    </div>
    
    <hr class="divider">
    
    <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px;">
        Thank you for your attention to this matter.
    </p>
@endsection
