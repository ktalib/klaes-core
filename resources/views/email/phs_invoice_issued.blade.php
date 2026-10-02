@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Invoice for Property History Search Portal</h2>
    
    <p>Dear <strong>{{ $request->contact_name }}</strong>,</p>
    
    <p>Thank you for your onboarding request. We have received your details and your invoice is attached to this email. Please review it carefully.</p>
    
    <div class="info-box">
        <strong>📋 Invoice Summary</strong>
    </div>
    
    <table class="details">
        <tr>
            <td>Invoice Number:</td>
            <td><strong>{{ $request->invoice_number ?? 'Pending' }}</strong></td>
        </tr>
        <tr>
            <td>Organization:</td>
            <td>{{ $request->organization_name }}</td>
        </tr>
        <tr>
            <td>Package:</td>
            <td>{{ $request->initial_token_package }}</td>
        </tr>
        <tr>
            <td>Payment Reference:</td>
            <td><strong>{{ $request->paystack_reference ?: ($request->payment_reference ?: 'N/A') }}</strong></td>
        </tr>
        <tr>
            <td>Payment Method:</td>
            <td>{{ $request->paystack_reference ? 'Paystack (Online)' : 'Bank Transfer' }}</td>
        </tr>
        <tr>
            <td>Amount:</td>
            <td style="font-weight: 700; color: #10b981;">₦{{ number_format((float) $request->payment_amount, 2) }}</td>
        </tr>
    </table>

   
    <hr class="divider">
    
    <p style="color: #6b7280; font-size: 12px;">
        If you have any questions, reply to this email or contact the ministry's finance office.<br>
        Kano State Ministry of Land & Physical Planning — PHS Portal
    </p>
@endsection
