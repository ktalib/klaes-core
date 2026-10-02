<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ env('APP_NAME') }}</title>
    <style type="text/css">
        @media only screen and (max-width: 480px) {
            body, table, td, div, p, a { -webkit-text-size-adjust: 100% !important; -ms-text-size-adjust: 100% !important; }
            body { width: 100% !important; min-width: 100% !important; }
            .mobile-hide { display: none !important; }
            .mobile-center { text-align: center !important; }
            .header-logo { max-width: 36px !important; width: 36px !important; }
            .header-title { font-size: 11px !important; }
        }
        * { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; margin: 0; padding: 0; }
        html, body { height: 100% !important; width: 100% !important; }
        body { background-color: #f5f7fa; }
        body, table, td, div, p, a { -webkit-font-smoothing: antialiased; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: nearest-neighbor; }
        .wrapper { width: 100%; table-layout: fixed; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        .container { max-width: 700px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8c 100%); color: white; padding: 0; }
        .header-content { padding: 30px 20px; text-align: center; }
        .header-logos { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: white; }
        .logo { max-width: 100px; height: auto; }
        .logo-left { text-align: left; }
        .logo-right { text-align: right; }
        .body-content { padding: 40px 30px; background: white; }
        .footer { background-color: #1e3a5f; color: #ffffff; padding: 30px 20px; font-size: 13px; text-align: center; }
        .footer-logos { display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 20px; }
        .footer-logo { max-width: 80px; height: auto; }
        .divider { border: 0; border-top: 2px solid #e5e7eb; margin: 20px 0; }
        h1 { font-size: 28px; font-weight: 700; color: #1f2937; margin-bottom: 10px; }
        h2 { font-size: 20px; font-weight: 700; color: #1e3a5f; margin-top: 25px; margin-bottom: 15px; }
        h3 { font-size: 16px; font-weight: 700; color: #374151; margin-top: 20px; margin-bottom: 12px; }
        p { font-size: 15px; color: #4b5563; line-height: 1.6; margin-bottom: 15px; }
        a { color: #0ea5e9; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 15px 0; border: none; cursor: pointer; }
        .btn:hover { text-decoration: none; opacity: 0.9; }
        .btn-primary { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .info-box { background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box strong { color: #0c4a6e; }
        .success-box { background: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .warning-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .danger-box { background: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px; }
        table.details { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.details td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        table.details td:first-child { font-weight: 700; color: #1f2937; width: 35%; }
        table.details tr:last-child td { border-bottom: none; }
        .list { margin: 12px 0; padding-left: 20px; }
        .list li { margin-bottom: 8px; color: #4b5563; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .footer-links { margin-top: 15px; }
        .footer-links a { color: #ffffff; font-size: 12px; margin: 0 10px; }
        .muted { color: #6b7280; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        .spacer { height: 20px; }
    </style>
</head>
<body>
    <table class="wrapper" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table class="container" width="100%" cellpadding="0" cellspacing="0" style="max-width: 700px; margin: 0 auto;">
                    <!-- Header with Ministry Logos -->
                    <tr>
                        <td style="padding: 10px 12px; background: white; border-bottom: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="12%" style="text-align: left; vertical-align: middle; padding-right: 6px; overflow: hidden;">
                                        <img class="header-logo" src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="Ministry Logo" width="52" height="52" style="max-width: 52px; width: 52px; height: auto; display: block; object-fit: contain;">
                                    </td>
                                    <td width="76%" style="text-align: center; vertical-align: middle; padding: 0 6px;">
                                        <h3 class="header-title" style="margin: 0; color: #1e3a5f; font-size: 14px; font-weight: 700; line-height: 1.3; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ env('APP_NAME') }}</h3>
                                    </td>
                                    <td width="12%" style="text-align: right; vertical-align: middle; padding-left: 6px; overflow: hidden;">
                                        <img class="header-logo" src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Ministry Logo" width="52" height="52" style="max-width: 52px; width: 52px; height: auto; display: block; object-fit: contain; margin-left: auto;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main Header -->
                    <tr>
                        <td class="header">
                            <div class="header-content">
                                @if(isset($logoUrl))
                                    <img src="{{ $logoUrl }}" alt="Logo" style="max-height: 80px; margin-bottom: 15px;">
                                @endif
                                @if(isset($headerTitle))
                                    <h1 style="color: white; margin: 0; font-size: 24px;">{{ $headerTitle }}</h1>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td class="body-content">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 15px;">
                                <tr>
                                    <td width="20%" style="text-align: right; vertical-align: middle; padding-right: 12px;">
                                        <img src="http://app.klaes.ng/assets/logo/phs-light-logo.jpeg" alt="PHS Portal" style="max-width: 45px; height: auto; display: inline-block;">
                                    </td>
                                    <td width="60%" style="text-align: center; vertical-align: middle;">
                                        <p style="color: white; margin: 0; font-size: 12px; line-height: 1.5;">
                                            <strong>Kano State Ministry of Land &amp; Physical Planning</strong><br>
                                            Property History Search Portal
                                        </p>
                                    </td>
                                    <td width="20%" style="text-align: left; vertical-align: middle; padding-left: 12px;">
                                        <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="KLAES" style="max-width: 45px; height: auto; display: inline-block;">
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #d1d5db; margin: 10px 0; font-size: 12px;">
                                This is an automated email. Please do not reply to this message.
                            </p>
                            <p style="color: #9ca3af; margin: 10px 0; font-size: 11px;">
                                &copy; {{ date('Y') }} {{ env('APP_NAME') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
