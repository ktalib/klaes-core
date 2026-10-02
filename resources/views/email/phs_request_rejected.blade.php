@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Update on Your PHS Portal Onboarding Request</h2>

    <p>Dear <strong>{{ $request->contact_name }}</strong>,</p>

    <p>Thank you for submitting your onboarding request for the PHS Portal. We appreciate your interest in our services.</p>
    
    <div class="danger-box">
        <strong>Request Status: Rejected</strong><br>
        Unfortunately, your request could not be approved at this time.
    </div>
    
    <h3>Reason for Rejection</h3>
    <div style="background: #f3f4f6; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #1f2937;">{{ $request->rejection_reason }}</p>
    </div>
    
    <h3>What You Can Do</h3>
    <ul class="list">
        <li>If you believe this decision was made in error, you may request a review</li>
        <li>Address the concerns mentioned above and submit a new request</li>
        <li>Contact our support team for more information</li>
    </ul>
    
    <h3>Request Details</h3>
    <table class="details">
        <tr>
            <td>Organization:</td>
            <td>{{ $request->organization_name }}</td>
        </tr>
        <tr>
            <td>Request Date:</td>
            <td>{{ $request->created_at->format('F j, Y') }}</td>
        </tr>
        <tr>
            <td>Decision Date:</td>
            <td>{{ now()->format('F j, Y') }}</td>
        </tr>
    </table>
    
    <h3>Next Steps</h3>
    <p>You may submit a new request at any time. To do so:</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $resubmitUrl ?? 'https://app.klaes.ng/phs/onboard' }}" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Submit New Request</a>
    </div>
    
    <hr class="divider">
    
    <p style="color: #6b7280; font-size: 12px;">
        If you have questions about this decision or need further assistance, please reach out to our support team. We're happy to help clarify any concerns.<br>
        <strong>Kano State Ministry of Land & Physical Planning — PHS Portal</strong>
    </p>
@endsection
