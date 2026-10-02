@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Onboarding Link to Complete Registration</h2>

    <div class="success-box">
        <strong>Great news!</strong> Your signed SLA has been approved. This is the final step: complete your payment and you'll be taken straight to register your account.
    </div>

    <h3>Organization Information</h3>
    <table class="details">
        <tr>
            <td>Organization:</td>
            <td><strong>{{ $request->organization_name }}</strong></td>
        </tr>
        <tr>
            <td>Contact:</td>
            <td>{{ $request->contact_name }}</td>
        </tr>
        <tr>
            <td>Email:</td>
            <td>{{ $request->contact_email }}</td>
        </tr>
        @isset($suggestedUsername)
        <tr>
            <td>Organization Username:</td>
            <td><strong>{{ $suggestedUsername }}</strong></td>
        </tr>
        @endisset
    </table>

    @isset($suggestedUsername)
    <div class="info-box">
        <strong>🔑 Your Organization Username:</strong> <strong>{{ $suggestedUsername }}</strong> — this is auto-assigned from your organization name and will be pre-filled on the registration form after payment. You can update it later under <em>Organization &rsaquo; Branding</em>.
    </div>
    @endisset

    <h3>Pay &amp; Complete Your Organization Registration</h3>
    <p>Click the button below to confirm your subscription package and pay securely via Paystack. Once payment is confirmed, you'll be taken straight to register your account:</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $paymentUrl }}"
           style="display: inline-block; background: linear-gradient(135deg, #166534 0%, #14532d 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px;">
           Pay &amp; Complete Your Organization Registration
        </a>
    </div>

    <div class="info-box">
        <strong>What happens next?</strong>
        <ul class="list" style="margin-top: 8px;">
            <li>Confirm your subscription package</li>
            <li>Complete payment securely via Paystack</li>
            <li>You'll be taken directly to register and activate your account</li>
        </ul>
    </div>

    <div class="warning-box">
        <strong>Important:</strong> This payment link is unique to your organization. Please keep it confidential and complete payment at your earliest convenience.
    </div>

    <hr class="divider">

    <p style="color: #6b7280; font-size: 12px;">
        If you have any questions, contact the ministry's finance office.<br>
        Kano State Ministry of Land &amp; Physical Planning — PHS Portal
    </p>
@endsection
