{{--
    Batch print of 1st Serve notices: every selected notice in one document,
    one per A4 sheet, each exactly as its single print (partials/letter).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1st Serve Notices ({{ $notices->count() }}) - Contravention of Landuse</title>
@include('special_assignment.notice.partials.print_styles')
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Print all {{ $notices->count() }} notice{{ $notices->count() === 1 ? '' : 's' }}</button>
    @if($skipped)
        <div style="margin-top:8px;font:13px Arial,sans-serif;color:#92400e">{{ $skipped }} selected item(s) were not 1st Serve notices and are left out.</div>
    @endif
</div>

@foreach($notices as $notice)
    @include('special_assignment.notice.partials.letter', ['notice' => $notice])
@endforeach

</body>
</html>
