<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New File Search Request</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { background: #4f46e5; color: #fff; padding: 18px 24px; font-size: 18px; font-weight: 700; }
        .content { padding: 24px; }
        .req-no { font-size: 20px; font-weight: 800; color: #4f46e5; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 8px 10px; border-bottom: 1px solid #eef2f7; font-size: 14px; }
        td.label { color: #6b7280; font-weight: 600; width: 40%; }
        .footer { margin-top: 28px; font-size: 13px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">📁 New File Search Request (FR)</div>
        <div class="content">
            <p class="req-no">{{ $fr->request_no }}</p>
            <p>A file search request has been routed to you for a physical search. Please open the
               KLAES mobile app to action it and feed back <strong>Found / Not Found</strong>.</p>

            <table>
                <tr><td class="label">File Number</td><td><strong>{{ $fr->file_number }}</strong></td></tr>
                <tr><td class="label">File Title</td><td>{{ $fr->file_title ?: '—' }}</td></tr>
                <tr><td class="label">Expected Location</td><td>{{ $fr->current_location ?: '—' }}</td></tr>
                <tr><td class="label">Requested By</td><td>{{ $requesterName }}</td></tr>
                <tr><td class="label">Requested At</td><td>{{ optional($fr->created_at)->format('d/m/Y H:i') }}</td></tr>
            </table>

            <p class="footer">You're receiving this because you are an SCB Monitor (file searcher).<br>
            Kano State Ministry of Land &amp; Physical Planning — KLAES File Tracking.</p>
        </div>
    </div>
</body>
</html>
