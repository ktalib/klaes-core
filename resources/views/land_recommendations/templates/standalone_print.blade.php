<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Recommendation - {{ $recommendation->file_number }}</title>
    <style>
        body { margin: 0; padding: 0; background: white; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(() => window.close(), 500);">
    @include('land_recommendations.templates.print_layout', ['recommendation' => $recommendation])

    <script>
        {{-- A White Copy is never logged. log-print writes a print_logs row, which
             is what the Printed tab and its counters read — and a proof has not been
             printed in that sense. The handler is simply not attached, so printing
             one leaves the record exactly as it was and another can be run off after
             every correction. --}}
        @unless(!empty($isWhiteCopy))
        // Log the print after the dialog is closed
        window.onafterprint = function() {
            fetch('{{ route("land-recommendations.log-print", $recommendation->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            }).then(response => response.json())
              .then(data => {
                  console.log('Print logged:', data);
                  // Optionally redirect or close if not done in body onload
              })
              .catch(error => console.error('Error logging print:', error));
        };
        @endunless
    </script>
</body>
</html>
