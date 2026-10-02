@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Legal Search Report Is Ready</h2>

    <div class="success-box">
        <strong>Approved.</strong> Your Online Legal Search request has been approved and the report is attached to this email as a PDF.
    </div>

    @if($searchRequest->basketSize() > 1)
        <div class="info-box" style="background:#eff6ff;border:1px solid #bfdbfe;">
            <strong>This is file {{ $searchRequest->basketPosition() }} of {{ $searchRequest->basketSize() }} from your request.</strong>
            You paid for {{ $searchRequest->basketSize() }} files in one transaction; each is approved and emailed separately, so this
            attachment covers <strong>only {{ $searchRequest->file_number ?: 'this file' }}</strong>.
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
            <td>Approved By:</td>
            <td>{{ $searchRequest->reviewer_name ?: 'The Ministry' }}@if($searchRequest->reviewer_rank) — {{ $searchRequest->reviewer_rank }}@endif</td>
        </tr>
        <tr>
            <td>Approved On:</td>
            <td>{{ optional($searchRequest->reviewed_at)->format('F j, Y \a\t g:i A') }}</td>
        </tr>
        <tr>
            <td>Payment Reference:</td>
            <td>{{ $searchRequest->tracking_id ?: $searchRequest->reference }}</td>
        </tr>
    </table>

    <div class="info-box">
        <strong>📎 Attached:</strong>
        <ul style="margin:8px 0 0; padding-left: 18px;">
            <li>Your <strong>Legal Search Report</strong> (PDF) — issued to {{ $searchRequest->requester_email }}, quoting request number {{ $searchRequest->request_no }}.</li>
            @if($searchRequest->payment)
                <li>Your <strong>Payment Invoice</strong> ({{ $invoiceNumber }}) for the search fee.</li>
            @endif
        </ul>
        Please keep both for your records.
    </div>

    <div class="warning-box" style="background:#fffbeb;border:1px solid #fcd34d;padding:14px;border-radius:6px;margin:18px 0;">
        <strong>⏳ Validity:</strong> this search report is <strong>valid for 30 days from the date of issue</strong>
        ({{ optional($searchRequest->reviewed_at)->format('F j, Y') }}@if($searchRequest->reviewed_at) — expires {{ $searchRequest->reviewed_at->copy()->addDays(30)->format('F j, Y') }}@endif).
        After that a fresh search is required, as the records may have changed.
    </div>

    <p style="font-size: 13px; color: #64748b;">
        N.B: This search report is deduced from the records available on the file and does not represent any document in the possession of any body.
    </p>
@endsection
