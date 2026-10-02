@extends('email.layouts.master')

@section('content')
    @if($forAdmin)
        <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">New PHS Feedback / Complaint</h2>
        <p>A PHS Portal member has reported an issue with a property history search. Please review the details below and follow up.</p>
    @else
        <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">We've received your feedback</h2>
        <p>Thank you for letting us know. Your complaint has been logged and will be reviewed by the Ministry. A summary is below for your records.</p>
    @endif

    <h3 style="margin-top: 30px;">Complaint Details</h3>
    <table class="details">
        <tr>
            <td>Type of issue:</td>
            <td><strong>{{ $feedback->categoryLabel() }}</strong></td>
        </tr>
        @if($feedback->subject)
        <tr>
            <td>Subject:</td>
            <td>{{ $feedback->subject }}</td>
        </tr>
        @endif
        @if($feedback->file_number)
        <tr>
            <td>File Number:</td>
            <td><strong>{{ $feedback->file_number }}</strong></td>
        </tr>
        @endif
        @if($feedback->reference_no)
        <tr>
            <td>Search Reference:</td>
            <td>{{ $feedback->reference_no }}</td>
        </tr>
        @endif
        <tr>
            <td>Submitted:</td>
            <td>{{ ($feedback->created_at ?? now())->format('F j, Y \a\t g:i A') }}</td>
        </tr>
    </table>

    <h3>Message</h3>
    <p style="white-space: pre-line; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px;">{{ $feedback->message }}</p>

    @if($forAdmin)
        <h3 style="margin-top: 30px;">Submitted By</h3>
        <table class="details">
            <tr>
                <td>Organization:</td>
                <td><strong>{{ optional($feedback->institution)->name ?? 'Unknown' }}</strong></td>
            </tr>
            <tr>
                <td>Member:</td>
                <td>{{ optional($feedback->member)->name ?? 'Unknown' }}</td>
            </tr>
            @if(optional($feedback->member)->email)
            <tr>
                <td>Member Email:</td>
                <td><a href="mailto:{{ $feedback->member->email }}">{{ $feedback->member->email }}</a></td>
            </tr>
            @endif
        </table>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $adminDashboardUrl }}" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Review in Dashboard</a>
        </div>

        <hr class="divider">
        <p style="color: #6b7280; font-size: 12px;">
            Log in to the PHS admin dashboard to triage and respond to this complaint.
        </p>
    @else
        <hr class="divider">
        <p style="color: #6b7280; font-size: 12px;">
            You do not need to reply to this email. The Ministry will get back to you if any further information is required.
        </p>
    @endif
@endsection
