{{--
    Combined print for a subdivision batch.

    $head / $bodies come from rendering each child through its own print view, so
    this file deliberately holds no letter markup of its own — the individual and
    batch documents can never diverge.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Recommendation Print &mdash; {{ $mother }} ({{ $count }})</title>

    {{-- The head of the child template: its styles, fonts and metadata. --}}
    {!! $head !!}

    <style>
        /* A zero-height marker placed BETWEEN letters. Wrapping each letter in a
           box instead would put a new block in the child's page flow and can push
           its last lines onto an extra sheet. */
        .batch-print-break {
            height: 0;
            margin: 0;
            border: 0;
            break-before: page;
            page-break-before: always;
        }

        .batch-print-bar {
            position: sticky; top: 0; z-index: 99999;
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px;
            background: #4c1d95; color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 13px; line-height: 1.4;
        }
        .batch-print-bar strong { font-weight: 700; }
        .batch-print-bar button {
            margin-left: auto; padding: 6px 14px; border: 0; border-radius: 6px;
            background: #fff; color: #4c1d95; font-weight: 700; font-size: 12px; cursor: pointer;
        }
        @media print {
            .batch-print-bar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="batch-print-bar">
        <span>Batch <strong>{{ $mother }}</strong> &mdash; {{ $count }} {{ \Illuminate\Support\Str::plural('recommendation', $count) }}</span>
        <button type="button" onclick="window.print()">Print all</button>
    </div>

    @foreach($bodies as $i => $body)
        @if($i > 0)<div class="batch-print-break"></div>@endif
        {!! $body !!}
    @endforeach

    <script>
        // The per-record scripts are stripped server-side, so logging is done once
        // here for the whole batch after the print dialog closes.
        var BATCH_IDS  = @json($ids);
        var LOG_URL    = '{{ url('land-recommendations') }}';
        var CSRF_TOKEN = '{{ csrf_token() }}';
        var logged     = false;

        window.onafterprint = function () {
            if (logged) return;
            logged = true;

            BATCH_IDS.forEach(function (id) {
                fetch(LOG_URL + '/' + id + '/log-print', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                }).catch(function (e) { console.error('Failed to log print for ' + id, e); });
            });
        };
    </script>
</body>
</html>
