{{--
    SURCON Beacon Register, printed as the physical book (docs/Scanned Document 2.pdf, page 7):
    header with register no., quarter, month and total issued per page; the eleven columns; the
    Authorization & Audit Trail footer. 20 lines a page. Digital signatures are not added yet
    (user, 2026-10-25): the signature cells stay blank for hand signing.
--}}
@php
    $months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $quarters = [1 => '1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];
    $who = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>SURCON Beacon Register</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; margin: 0; }
        .page { page-break-after: always; padding: 4mm 2mm; }
        .page:last-child { page-break-after: auto; }
        h1 { font-size: 15px; margin: 0 0 6px; letter-spacing: .03em; }
        .meta { margin-bottom: 8px; font-size: 12px; }
        .meta strong { font-weight: bold; }
        table.book { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.book th, table.book td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; word-wrap: break-word; }
        table.book th { font-size: 10px; text-transform: uppercase; text-align: center; font-weight: bold; }
        table.book td.num { text-align: right; }
        table.book tr.blank td { height: 16px; }
        .note { font-style: italic; font-size: 10px; margin: 6px 0 10px; }
        .audit { margin-top: 10px; width: 70%; border-collapse: collapse; }
        .audit th { text-align: left; font-size: 11px; padding: 3px 6px; }
        .audit td { padding: 6px; border-bottom: 1px solid #555; }
        .no-print { margin: 8px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Print / Save as PDF</button>
    <a href="{{ route('survey-module.workflow.beacon-register.index', $filters) }}">Back to the register</a>
    @if ($capped) <strong style="color:#b91c1c;">Only the first 2,000 lines are printed; narrow the filters.</strong> @endif
</div>

@forelse ($pages as $page)
    @php
        $regNos = $page->pluck('register_no')->filter()->unique()->values();
        $qs = $page->pluck('quarter')->unique()->values();
        $ms = $page->pluck('month')->unique()->values();
        $entered = $page->map(fn ($r) => $r->creator)->filter()->unique('id');
        $reviewed = $page->whereNotNull('reviewed_at');
        $audited = $page->whereNotNull('audited_at');
    @endphp
    <div class="page">
        <h1>SURCON BEACON REGISTER (No. {{ $regNos->isNotEmpty() ? $regNos->implode(', ') : '__________' }})</h1>
        <div class="meta">
            <strong>Quarter:</strong> {{ $qs->map(fn ($q) => $quarters[$q] ?? $q)->implode(', ') }} &nbsp;|&nbsp;
            <strong>Month:</strong> {{ $ms->map(fn ($m) => $months[$m] ?? $m)->implode(', ') }} &nbsp;|&nbsp;
            <strong>Total Number Issued Per Page:</strong> {{ number_format($page->sum('total_issued')) }}
        </div>

        <table class="book">
            <colgroup>
                <col style="width:7%"><col style="width:12%"><col style="width:8%"><col style="width:13%"><col style="width:8%">
                <col style="width:13%"><col style="width:8%"><col style="width:7%"><col style="width:7%"><col style="width:8%"><col style="width:9%">
            </colgroup>
            <thead>
                <tr><th>Date</th><th>Issued Number</th><th>Plan No</th><th>Surveyor's Name</th><th>Registration</th>
                    <th>Location</th><th>Teller No</th><th>Amount (₦)</th><th>Date Received</th><th>Signature</th><th>Remark</th></tr>
            </thead>
            <tbody>
                @foreach ($page as $r)
                    <tr>
                        <td>{{ optional($r->issue_date)->format('d/m/Y') }}</td>
                        <td>{{ $r->beacon_to && strcasecmp($r->beacon_to, $r->beacon_from) !== 0 ? $r->beacon_from . ' – ' . $r->beacon_to : $r->beacon_from }}@if ($r->total_issued > 1) ({{ $r->total_issued }})@endif</td>
                        <td>{{ $r->plan_no }}</td>
                        <td>{{ $r->surveyor_name }}</td>
                        <td>{{ $r->surveyor_registration }}</td>
                        <td>{{ $r->location ?: $r->property_location }}</td>
                        <td>{{ $r->teller_no }}</td>
                        <td class="num">{{ $r->amount !== null ? number_format((float) $r->amount, 2) : '' }}</td>
                        <td>{{ optional($r->date_received)->format('d/m/Y') }}</td>
                        <td>{{ $r->received_by }}</td>
                        <td>{{ $r->remark }}</td>
                    </tr>
                @endforeach
                @for ($i = $page->count(); $i < 20; $i++)
                    <tr class="blank">@for ($c = 0; $c < 11; $c++)<td></td>@endfor</tr>
                @endfor
            </tbody>
        </table>
        <p class="note">Total Number Issued on this page: {{ number_format($page->sum('total_issued')) }} · printed {{ now()->format('d M Y H:i') }} from KLAES.</p>

        <strong>AUTHORIZATION &amp; AUDIT TRAIL</strong>
        <table class="audit">
            <tr><th>Role</th><th>Name</th><th>Date</th><th>Signature</th></tr>
            <tr><td>ENTERED BY</td><td>{{ $entered->map($who)->implode(', ') }}</td><td>{{ optional($page->max('created_at'))->format('d/m/Y') }}</td><td></td></tr>
            <tr><td>REVIEWED BY</td><td>{{ $reviewed->map(fn ($r) => $who($r->reviewer))->filter()->unique()->implode(', ') }}</td><td>{{ optional($reviewed->max('reviewed_at'))->format('d/m/Y') }}</td><td></td></tr>
            <tr><td>AUDITED BY</td><td>{{ $audited->map(fn ($r) => $who($r->auditor))->filter()->unique()->implode(', ') }}</td><td>{{ optional($audited->max('audited_at'))->format('d/m/Y') }}</td><td></td></tr>
        </table>
    </div>
@empty
    <p style="margin:20px;">No register lines match these filters.</p>
@endforelse
</body>
</html>
