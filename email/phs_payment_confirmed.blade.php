@extends('email.layouts.master')

@php
    $status = $request->payment_status;
    $expected = (float) ($request->expected_amount ?? $request->payment_amount);
    $verified = (float) $request->verified_amount;
    $outstanding = (float) ($request->outstanding_amount ?? 0);
@endphp

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Payment Update - Property History Search Portal</h2>
    
    <p>Dear <strong>{{ $request->contact_name }}</strong>,</p>
    
    @if ($status === 'completed' || $status === 'overpaid')
        <div class="success-box">
            <strong>✅ Payment Confirmed!</strong><br>
            We're pleased to confirm that your payment has been received and verified.
        </div>
        @if ($status === 'overpaid')
            <div class="info-box" style="margin-top: 15px;">
                <strong>ℹ️ Overpayment Recorded:</strong> An overpayment of <strong>₦{{ number_format($verified - $expected, 2) }}</strong> was detected. Our finance office will contact you regarding this.
            </div>
        @endif
    @elseif ($status === 'incomplete')
        <div class="warning-box">
            <strong>⚠️ Balance Outstanding</strong><br>
            We have received part of your payment. Please settle the outstanding balance to proceed with activation.
        </div>
    @else
        <div class="danger-box">
            <strong>❌ Payment Outstanding</strong><br>
            Our records show that payment for your onboarding request is still outstanding.
        </div>
    @endif
    
    <h3>Payment Summary</h3>
    <table class="details">
        <tr>
            <td>Organization:</td>
            <td>{{ $request->organization_name }}</td>
        </tr>
        <tr>
            <td>Invoice Number:</td>
            <td>{{ $request->invoice_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Expected Amount:</td>
            <td>₦{{ number_format($expected, 2) }}</td>
        </tr>
        <tr>
            <td>Amount Received:</td>
            <td style="color: #10b981; font-weight: 700;">₦{{ number_format($verified, 2) }}</td>
        </tr>
        @if ($outstanding > 0)
        <tr>
            <td>Outstanding Balance:</td>
            <td style="color: #ef4444; font-weight: 700;">₦{{ number_format($outstanding, 2) }}</td>
        </tr>
        @endif
    </table>
    
    @if ($outstanding > 0)
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://app.klaes.ng/payment" class="btn" style="display: inline-block; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Pay Outstanding Balance</a>
    </div>
    @else
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://app.klaes.ng" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Access Your Account</a>
    </div>
    @endif
    
    <hr class="divider">
    
    <p style="color: #6b7280; font-size: 12px;">
        For questions about this payment, reply to this email or contact the ministry's finance office.<br>
        Kano State Ministry of Land & Physical Planning — PHS Portal
    </p>
@endsection
