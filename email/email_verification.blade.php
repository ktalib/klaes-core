@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Verify Your Email Address</h2>
    
    <p>Dear <strong>{{ $data['name'] }}</strong>,</p>
    
    <p>Thank you for signing up with <strong>{{ env('APP_NAME') }}</strong>. To complete your registration, please confirm your email address by clicking the button below:</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $data['url'] }}" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Verify Email Address</a>
    </div>
    
    <p>If the button above doesn't work, copy and paste this URL into your web browser:</p>
    <p style="word-break: break-all;"><a href="{{ $data['url'] }}">{{ $data['url'] }}</a></p>
    
    <div class="info-box">
        <strong>⏱️ Link Expiration:</strong> This link will expire in 60 minutes.
    </div>
    
    <p>If you didn't create this account, you can safely ignore this email.</p>
    
    <hr class="divider">
    
    <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px;">
        Thank you,<br>
        <strong>{{ env('APP_NAME') }} Team</strong>
    </p>
@endsection
