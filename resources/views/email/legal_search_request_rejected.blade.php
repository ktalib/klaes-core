@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Legal Search Request Was Not Approved</h2>

    <p>Your Online Legal Search request has been reviewed and could not be approved at this time.</p>

    @if($searchRequest->basketSize() > 1)
        <div class="info-box" style="background:#eff6ff;border:1px solid #bfdbfe;">
            <strong>This is file {{ $searchRequest->basketPosition() }} of {{ $searchRequest->basketSize() }} from your request.</strong>
            Only <strong>{{ $searchRequest->file_number ?: 'this file' }}</strong> was declined — the other files you paid for in the
            same transaction are reviewed separately and are not affected by this decision.
            <table class="details" style="margin-top:10px;">
                @foreach($searchRequest->basketSiblings() as $sibling)
                    <tr>
                        <td>{{ $sibling->file_number ?: '—' }}</td>
                        <td><strong>{{ $sibling->request_no }}</strong>@if($sibling->id === $searchRequest->id) &nbsp;(this email) @endif</td>
                        <td style="text-transform:capitalize;">{{ $sibling->status }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <h3>Request Details</h3>
    <table class="details">
        <tr>
            <td>Request Number:</td>
            <td><strong>{{ $searchRequest->request_no }}</strong></td>
        </tr>
        <tr>
            <td>File Number:</td>
            <td><strong>{{ $searchRequest->file_number ?: '—' }}</strong></td>
        </tr>
        <tr>
            <td>Reviewed On:</td>
            <td>{{ optional($searchRequest->reviewed_at)->format('F j, Y \a\t g:i A') }}</td>
        </tr>
        <tr>
            <td>Payment Reference:</td>
            <td>{{ $searchRequest->tracking_id ?: $searchRequest->reference }}</td>
        </tr>
    </table>

    <div class="warning-box" style="background:#fffbeb;border:1px solid #fcd34d;padding:14px;border-radius:6px;margin:18px 0;">
        <strong>Reason:</strong> {{ $searchRequest->rejection_reason }}
    </div>

    <p>
        If you believe this is in error, please reply to this email or contact the Ministry quoting request number
        <strong>{{ $searchRequest->request_no }}</strong> and your payment reference above.
    </p>
@endsection
