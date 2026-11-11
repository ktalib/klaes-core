@extends('surveyor_portal.layouts.portal')

@section('title', 'Beacon Register — MLPP-SG Portal')
@section('heading', 'Beacon Register')

{{-- The beacons this surveyor has used (user, 2026-11-09: "Beacon Register will contain the used Beacons only"). --}}
@push('styles')
    <style>
        .ks-reg-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .ks-reg-chips { display:flex; flex-wrap:wrap; gap:8px; }
        .ks-reg-chip { padding:6px 12px; border:1px solid var(--ks-line); border-radius:999px; background:#fff; font-size:13px; }
        .ks-reg-chip strong { font-variant-numeric:tabular-nums; }
        .ks-reg-search { display:flex; gap:8px; }
        .ks-reg-search .ks-input { width:260px; max-width:60vw; }
        .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
    </style>
@endpush

@section('main')
    <div class="ks-reg-top">
        <div class="ks-reg-chips">
            <span class="ks-reg-chip">Used <strong>{{ number_format($balance['used']) }}</strong></span>
            <span class="ks-reg-chip">Issued <strong>{{ number_format($balance['issued']) }}</strong></span>
            <span class="ks-reg-chip">Balance <strong>{{ number_format($balance['left']) }}</strong></span>
        </div>
        <form method="GET" class="ks-reg-search" role="search">
            <label class="sr-only" for="regSearch">Search used beacons</label>
            <input class="ks-input" id="regSearch" name="q" value="{{ $search }}" placeholder="Beacon, request, report, place…">
            <button class="ks-btn ks-btn-light"><i data-lucide="search" class="w-4 h-4"></i><span class="sr-only">Search</span></button>
        </form>
    </div>

    <div class="ks-card overflow-x-auto">
        <table class="ks-table" style="min-width:1080px;">
            <thead>
                <tr><th>S/N</th><th>Beacon No.</th><th>Request No.</th><th>Surveyor's Report</th><th>Easting (X)</th><th>Northing (Y)</th><th>Height (Z)</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $b)
                    @php $rep = $b->portalReport; @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="font-semibold ks-num">{{ \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no) }}</td>
                        <td class="ks-num">
                            @if ($b->register?->request)
                                <a href="{{ route('surveyor-portal.requests.show', $b->register->request) }}" style="color:var(--ks-blue);font-weight:600;">{{ $b->register->request->request_no }}</a>
                            @else — @endif
                        </td>
                        <td class="ks-num">
                            @if ($rep)
                                <a href="{{ route('surveyor-portal.reports.show', $rep->id) }}" style="color:var(--ks-blue);font-weight:600;">{{ $rep->report_ref }}</a>
                            @else — @endif
                        </td>
                        <td class="ks-num">{{ $b->coord_x !== null ? number_format((float) $b->coord_x, 3) : '—' }}</td>
                        <td class="ks-num">{{ $b->coord_y !== null ? number_format((float) $b->coord_y, 3) : '—' }}</td>
                        <td class="ks-num">{{ $b->coord_z !== null ? number_format((float) $b->coord_z, 3) : '—' }}</td>
                        <td>{{ $rep?->plot_no ?: '—' }}</td>
                        <td>{{ $rep?->district ?: '—' }}</td>
                        <td>{{ $rep?->lga ? mb_strtoupper($rep->lga) : '—' }}</td>
                        <td>{{ $rep?->location ?: ($b->location_details ?: '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="ks-help" style="text-align:center;padding:28px;">
                        @if ($search !== '')
                            No used beacon matches “{{ $search }}”.
                        @else
                            No used beacons yet. A beacon appears here once it is on a <a href="{{ route('surveyor-portal.reports.index') }}" style="color:var(--ks-blue);font-weight:600;">Surveyor's Report</a> with its coordinates.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
