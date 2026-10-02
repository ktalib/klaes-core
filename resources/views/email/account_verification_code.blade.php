{{-- The sign-in verification code.

     DELIBERATELY NOT email.layouts.master. That layout is the PHS one — its
     footer names the Property History Search Portal and it pulls three remote
     logos off app.klaes.ng. Neither belongs on a staff sign-in code: the wrong
     service in the footer reads as a phishing mail, and remote images are the
     first thing a mail client blocks, which would leave the code sitting in an
     empty frame.

     So: one table, inline styles, no images, no links. Everything a filter
     dislikes about a code email is absent, and the code itself is plain text
     that survives every client down to a phone on 2G. --}}
<table width="100%" cellpadding="0" cellspacing="0" border="0"
    style="background-color:#f5f7fa;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Arial,sans-serif;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                style="max-width:520px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">

                <tr>
                    <td style="padding:20px 28px;background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:#0f172a;letter-spacing:0.02em;">KLAES</div>
                        <div style="margin-top:4px;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#64748b;">
                            Account setup
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 14px;font-size:15px;color:#0f172a;">
                            Hello <strong>{{ $recipientName }}</strong>,
                        </p>

                        <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
                            Use the code below to confirm this email address and finish setting up your
                            KLAES account. Type it into the confirmation card that is waiting on your screen.
                        </p>

                        {{-- The code. Big, spaced, and selectable as text — people copy it out of the
                             mail on the same machine at least as often as they read it off a phone. --}}
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
                            <tr>
                                <td align="center"
                                    style="padding:18px 12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;">
                                    <div style="font-size:32px;font-weight:800;letter-spacing:0.35em;color:#065f46;font-family:'Courier New',Courier,monospace;">{{ $code }}</div>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#475569;">
                            The code expires in <strong>{{ $expiresInMinutes }} minutes</strong>. If it runs
                            out, ask for another one from the same card.
                        </p>

                        <p style="margin:0;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-left:3px solid #107c41;border-radius:8px;font-size:13px;line-height:1.55;color:#334155;">
                            <strong style="color:#0f172a;">Nobody from the Ministry will ask you for this code.</strong>
                            If you did not try to sign in to KLAES{{ $requestedFrom ? ' (the request came from ' . $requestedFrom . ')' : '' }},
                            ignore this email and tell ICT — someone has your password.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 28px;background:#1e3a5f;text-align:center;">
                        <div style="font-size:12px;line-height:1.5;color:#e2e8f0;">
                            Kano State Ministry of Land &amp; Physical Planning
                        </div>
                        <div style="margin-top:6px;font-size:11px;color:#94a3b8;">
                            Automated message — please do not reply.
                        </div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
