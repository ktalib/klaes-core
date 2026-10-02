@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Test Email from {{ env('APP_NAME') }}</h2>
    
    <div class="success-box">
        <strong>✅ Email System Test Successful!</strong><br>
        This is a test message from your {{ env('APP_NAME') }} application.
    </div>
    
    <h3>Test Information</h3>
    <table class="details">
        <tr>
            <td>Application:</td>
            <td>{{ env('APP_NAME') }}</td>
        </tr>
        <tr>
            <td>Test Sent:</td>
            <td>{{ now()->format('F j, Y \a\t g:i A') }}</td>
        </tr>
        <tr>
            <td>Status:</td>
            <td><span class="badge badge-success">DELIVERED</span></td>
        </tr>
    </table>
    
    <p>If you received this email, your email configuration is working correctly!</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('home') }}" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Return to Application</a>
    </div>
    
    <hr class="divider">
    
    <p style="color: #6b7280; font-size: 12px;">
        This is an automated test email. You can safely ignore this message if you did not initiate this test.
    </p>
@endsection
