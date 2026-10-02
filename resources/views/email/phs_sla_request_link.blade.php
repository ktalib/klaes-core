@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Documents Have Been Approved — Select Your Package &amp; Sign Your SLA</h2>

    <div class="success-box">
        <strong>Good news!</strong> Your onboarding documents for the PHS Portal have been reviewed and approved. The next step is to select your subscription package and sign your Service Level Agreement (SLA).
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
    </table>

    <h3>Select Your Package, Sign &amp; Upload Your SLA</h3>
    <p>Open the secure page below to choose your subscription package, download the Service Level Agreement, sign it, and upload the signed copy:</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $uploadUrl }}"
           style="display: inline-block; background: linear-gradient(135deg, #14532d 0%, #14532d 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px;">
            Select Package &amp; Sign SLA
        </a>
    </div>

    <div class="info-box">
        <strong>What happens next?</strong>
        <ul class="list" style="margin-top: 8px;">
            <li>Select your subscription package and download, sign &amp; upload the SLA (no payment yet)</li>
            <li>Our Legal team reviews your signed SLA</li>
            <li>You will receive a secure payment &amp; onboarding link to complete payment and register your account</li>
        </ul>
    </div>

    <div class="warning-box">
        <strong>Important:</strong> This link is unique to your organization. Please keep it confidential.
    </div>

    <hr class="divider">

    <p style="color: #6b7280; font-size: 12px;">
        If you have any questions, contact the ministry's legal office.<br>
        Kano State Ministry of Land &amp; Physical Planning — PHS Portal
    </p>
@endsection
