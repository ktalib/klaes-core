{{-- An approved company's MLPP-SG portal login, sent once (user, 2026-11-10). One table, inline styles, no images,
     as the sign-in code email. --}}
<table width="100%" cellpadding="0" cellspacing="0" border="0"
    style="background-color:#f5f7fa;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Arial,sans-serif;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                style="max-width:520px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:20px 28px;background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:#0f172a;letter-spacing:0.02em;">MLPP-SG Portal</div>
                        <div style="margin-top:4px;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#64748b;">
                            {{ $surveyorId ? 'Surveyor login' : 'Company login' }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 14px;font-size:15px;color:#0f172a;">Hello <strong>{{ $companyName }}</strong>,</p>
                        <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
                            @if ($surveyorId)
                                The Office of the Surveyor General has opened your MLPP-SG Portal account. Your Surveyor ID is
                                <strong>{{ $surveyorId }}</strong>. Sign in with the details below to request beacons and submit your Surveyor's Reports.
                            @else
                                Your company registration has been approved by the Office of the Surveyor General.
                                Sign in to the MLPP-SG Portal with the details below to see your surveyors, your beacons and
                                your Surveyor's Reports.
                            @endif
                        </p>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;">
                            <tr>
                                <td style="padding:14px 16px;font-size:14px;color:#065f46;">
                                    <div>Username: <strong style="font-family:'Courier New',Courier,monospace;font-size:16px;">{{ $username }}</strong></div>
                                    <div style="margin-top:6px;">Password: <strong style="font-family:'Courier New',Courier,monospace;font-size:16px;">{{ $password }}</strong></div>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#475569;">
                            Sign in at <a href="{{ $loginUrl }}" style="color:#1e3a5f;font-weight:700;">{{ $loginUrl }}</a>.
                            This password is temporary: you will choose a new one at your first sign-in.
                        </p>
                        <p style="margin:0;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-left:3px solid #107c41;border-radius:8px;font-size:13px;line-height:1.55;color:#334155;">
                            <strong style="color:#0f172a;">These details are sent only once.</strong>
                            Keep them safe. Nobody from the Ministry will ask you for your password.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;background:#1e3a5f;text-align:center;">
                        <div style="font-size:12px;line-height:1.5;color:#e2e8f0;">Kano State Ministry of Land &amp; Physical Planning</div>
                        <div style="margin-top:6px;font-size:11px;color:#94a3b8;">Automated message — please do not reply.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
