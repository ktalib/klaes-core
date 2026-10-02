@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Welcome to {{ env('APP_NAME') }}</h2>
    
    <p>Dear <strong>{{ $data['name'] }}</strong>,</p>
    
    <div class="success-box">
        <p>We are excited to have you on board and look forward to providing you with an exceptional experience.</p>
    </div>
    
    <h3>Your Account Details</h3>
    
    <table class="details">
        <tr>
            <td>App Link:</td>
            <td><a href="{{ $data['url'] }}">{{ $data['url'] }}</a></td>
        </tr>
        <tr>
            <td>Username/Email:</td>
            <td><strong>{{ $data['email'] }}</strong></td>
        </tr>
        <tr>
            <td>Password:</td>
            <td><code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px;">{{ $data['password'] }}</code></td>
        </tr>
    </table>
    
    <div class="warning-box">
        <strong>⚠️ Important:</strong> Please change your password immediately after your first login for security purposes.
    </div>
    
    <h3>Next Steps</h3>
    <ul class="list">
        <li>Log in using your credentials above</li>
        <li>Update your profile information</li>
        <li>Explore the platform features</li>
    </ul>
    
    <p>We hope you enjoy your experience with us. If you have any questions or feedback, feel free to reach out to our support team.</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $data['url'] }}" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Go to Platform</a>
    </div>
    
    <hr class="divider">
    
    <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px;">
        Thank you for choosing {{ env('APP_NAME') }}!
    </p>
@endsection
