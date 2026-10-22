<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recertification Acknowledgement</title>
    @include('recertification.partials.ack_styles')
</head>
<body>
    <div class="print-bar no-print"><button onclick="window.print()">Print This Page</button></div>

    <div class="ack-break">
        @include('recertification.partials.ack_letter', ['application' => $application])
    </div>
    @include('recertification.partials.ack_collection')

    <div class="print-bar no-print"><button onclick="window.print()">Print This Page</button></div>
    <div class="klaes-corner"><img src="{{ asset('assets/logo/klaes.png') }}" alt="KLAES" /></div>

    <script>
        window.onload = function () { window.print(); };
    </script>
</body>
</html>
