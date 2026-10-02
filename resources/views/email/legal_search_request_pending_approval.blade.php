@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Online Legal Search Request Awaiting Your Approval</h2>

    <p>Dear {{ $approver->name ?: $approver->username }},</p>

    <p>A member of the public has submitted an Online Legal Search request. It will not be released until it is approved.</p>

    @if($searchRequest->basketSize() > 1)
        <div class="info-box" style="background:#eff6ff;border:1px solid #bfdbfe;">
            <strong>Part of a {{ $searchRequest->basketSize() }}-file request</strong> (file {{ $searchRequest->basketPosition() }} of
            {{ $searchRequest->basketSize() }}), paid for in one transaction. Each file has its own request number and its own
            approve/decline decision — approving this one does not approve the others.
            <table class="details" style="margin-top:10px;">
                @foreach($searchRequest->basketSiblings() as $sibling)
                    <tr>
                        <td>{{ $sibling->file_number ?: '—' }}</td>
                        <td><strong>{{ $sibling->request_no }}</strong>@if($sibling->id === $searchRequest->id) &nbsp;(this request) @endif</td>
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
            <td>Purpose of Search:</td>
            <td><strong>{{ $searchRequest->purpose ?: '—' }}</strong></td>
        </tr>
        <tr>
            <td>Requester:</td>
            <td>{{ $searchRequest->requester_email }}</td>
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

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $queueUrl }}" class="btn" style="display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Review Request in KLAES</a>
    </div>

    <div class="info-box">
        On approval, the Legal Search report is generated and emailed to the requester as a PDF automatically. If you decline the request, the requester is told why.
    </div>
@endsection
