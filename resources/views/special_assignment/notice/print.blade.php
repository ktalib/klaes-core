{{--
    SPAS contravention notice, printed (A4, browser print).

    Layout and the Second Serve (REMINDER) letter are the Ministry template
    supplied on 2026-10-08 (spas_notice.html). A First Serve uses the same
    letter with the heading NOTICE and the Ministry's First Serve wording
    (App\Models\SpaNotice::FIRST_SERVE_SMS), unchanged.
--}}
@php
    $isSecond = $notice->notice_type === 'second';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isSecond ? 'Reminder' : 'Notice' }} - Contravention of Landuse - {{ $notice->file_number }}</title>
@include('special_assignment.notice.partials.print_styles')
</head>
<body>

<div class="no-print"><button onclick="window.print()">Print this notice</button></div>

@include('special_assignment.notice.partials.letter', ['notice' => $notice])

</body>
</html>
