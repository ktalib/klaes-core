@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Legal Search Request Has Been Received</h2>

    <p>Thank you. Your Online Legal Search request has been submitted to the Ministry and is now awaiting review by the Director / Deputy Director.</p>

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
            <td>Purpose of Search:</td>
            <td><strong>{{ $searchRequest->purpose ?: '—' }}</strong></td>
        </tr>
        <tr>
            <td>Payment Reference:</td>
            <td>{{ $searchRequest->tracking_id ?: $searchRequest->reference }}</td>
        </tr>
        <tr>
            <td>Submitted:</td>
            <td>{{ optional($searchRequest->submitted_at)->format('F j, Y \a\t g:i A') }}</td>
        </tr>
    </table>

    @if($searchRequest->basketSize() > 1)
        <div class="info-box" style="background:#eff6ff;border:1px solid #bfdbfe;">
            <strong>This is file {{ $searchRequest->basketPosition() }} of {{ $searchRequest->basketSize() }} in your request.</strong>
            You paid for {{ $searchRequest->basketSize() }} files in one transaction, and each is searched, reviewed and reported
            on separately — so you will receive {{ $searchRequest->basketSize() }} emails like this one, one per file, and later
            {{ $searchRequest->basketSize() }} separate report emails as each is approved.
            <table class="details" style="margin-top:10px;">
                @foreach($searchRequest->basketSiblings() as $sibling)
                    <tr>
                        <td>{{ $sibling->file_number ?: '—' }}</td>
                        <td><strong>{{ $sibling->request_no }}</strong>@if($sibling->id === $searchRequest->id) &nbsp;(this email) @endif</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="info-box">
        <strong>What happens next:</strong> once a Director or Deputy Director approves your request, the full Legal Search report will be emailed to
        <strong>{{ $searchRequest->requester_email }}</strong> as a PDF attachment. No further action is needed from you.
    </div>

    <p style="font-size: 13px; color: #64748b;">
        Please quote request number <strong>{{ $searchRequest->request_no }}</strong> in any correspondence about this search.
    </p>
@endsection
