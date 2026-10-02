{{--
    Shared A4 shell for the three duplex documents (application, memo, conveyance).

    The one rule these documents share: the component updates are always listed in
    the officer's RANK order. That order is the instruction the client approved, and
    printing it any other way misstates what was authorised.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('doc-title', 'APU - Advance Parcel Update (Duplex)') — {{ $duplex->duplex_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background:#525659; margin:0; padding:0;
            font-family:'Times New Roman', Times, serif; font-size:11pt;
            display:flex; justify-content:center;
        }
        .a4-page {
            background:#fdf6e3; width:210mm; min-height:297mm;
            padding:22mm 20mm; box-shadow:0 0 10px rgba(0,0,0,.5);
        }
        .crest { text-align:center; margin-bottom:14px; }
        .crest h1 { font-size:15pt; margin:0; letter-spacing:.5px; }
        .crest h2 { font-size:11pt; margin:2px 0 0; font-weight:normal; }
        .doc-title {
            text-align:center; font-weight:bold; text-transform:uppercase;
            text-decoration:underline; margin:18px 0 16px; font-size:12.5pt;
        }
        .ref-row { display:flex; justify-content:space-between; font-size:10.5pt; margin-bottom:14px; }
        .meta { width:100%; border-collapse:collapse; margin-bottom:16px; font-size:10.5pt; }
        .meta td { padding:4px 0; vertical-align:top; }
        .meta td:first-child { width:38%; font-weight:bold; }
        .stage-table { width:100%; border-collapse:collapse; margin:10px 0 18px; font-size:10.5pt; }
        .stage-table th, .stage-table td { border:1px solid #333; padding:6px 8px; text-align:left; }
        .stage-table th { background:#efe7d0; text-transform:uppercase; font-size:9.5pt; letter-spacing:.4px; }
        .mono { font-family:'Courier New', monospace; }
        .body-paragraph { text-align:justify; line-height:1.55; margin-bottom:12px; }
        .sig-block { margin-top:36px; display:flex; justify-content:space-between; gap:40px; }
        .sig { flex:1; }
        .sig .line { border-bottom:1px solid #333; height:34px; }
        .sig .label { font-size:9.5pt; margin-top:4px; font-weight:bold; }
        .note { font-size:9.5pt; font-style:italic; color:#444; margin-top:14px; }
        @media print {
            body { background:#fff; }
            .a4-page { box-shadow:none; margin:0; }
        }
    </style>
</head>
<body onload="window.print()">
<div class="a4-page">

    <div class="crest">
        <h1>KANO STATE GOVERNMENT OF NIGERIA</h1>
        <h2>Ministry of Land and Physical Planning</h2>
        <h2>Kano Land Administration and Revenue Enhancement System (KLAES)</h2>
    </div>

    <div class="doc-title">@yield('doc-title', 'APU - Advance Parcel Update (Duplex)')</div>

    <div class="ref-row">
        <span><strong>Duplex Ref:</strong> <span class="mono">{{ $duplex->duplex_id }}</span></span>
        <span><strong>Date:</strong> {{ now()->format('d M, Y') }}</span>
    </div>

    @yield('doc-body')

    <div class="sig-block">
        <div class="sig">
            <div class="line"></div>
            <div class="label">Prepared by</div>
        </div>
        <div class="sig">
            <div class="line"></div>
            <div class="label">Approved by</div>
        </div>
    </div>
</div>
</body>
</html>
