<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 14px; margin: 0 0 2px; }
    .meta { color: #555; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 3px 6px; text-align: left; }
    th { background: #f1f1f1; }
    .sn { width: 50px; }
    .shelf { width: 120px; }
    .prefix { width: 80px; }
    .break { page-break-after: always; }
</style>
</head>
<body>
    {{-- One small table per page: a single large table exhausts dompdf memory. --}}
    @php $pages = array_chunk($rows, 50, true); @endphp
    @forelse ($pages as $p => $page)
        <div class="{{ $loop->last ? '' : 'break' }}">
            <h1>{{ $label }} File Numbers</h1>
            <div class="meta">
                Range: {{ $from ?: 'start' }} to {{ $to ?: 'today' }} &middot; Prefix: {{ $prefix ?: 'All' }} &middot; {{ number_format(count($rows)) }} file numbers &middot; Page {{ $p + 1 }} of {{ count($pages) }} &middot; Exported {{ now()->format('Y-m-d H:i') }}
            </div>
            <table>
                <tr><th class="sn">S/N</th><th class="prefix">File Prefix</th><th>File Number</th><th class="shelf">Shelf/Rack</th></tr>
                @foreach ($page as $i => $row)
                    <tr><td class="sn">{{ $i + 1 }}</td><td class="prefix">{{ $row['prefix'] }}</td><td>{{ $row['file_number'] }}</td><td class="shelf">{{ $row['shelf'] }}</td></tr>
                @endforeach
            </table>
        </div>
    @empty
        <h1>{{ $label }} File Numbers</h1>
    @endforelse
</body>
</html>
