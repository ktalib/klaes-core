@extends('surveyor_portal.layouts.portal')

@php $isUsed = $which === 'used'; @endphp
@section('title', ($isUsed ? 'Used Beacons' : 'Issued Beacons') . ' — MLPP-SG Portal')
@section('heading', $isUsed ? 'Used Beacons' : 'Issued Beacons')

{{-- Our Beacons (user, 2026-11-10): every beacon issued to the company, or the used ones with their coordinates. --}}
@push('styles')
    <style>
        .ks-co-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .ks-co-chips { display:flex; flex-wrap:wrap; gap:8px; }
        .ks-co-chip { padding:6px 12px; border:1px solid var(--ks-line); border-radius:999px; background:#fff; font-size:13px; }
        .ks-co-chip strong { font-variant-numeric:tabular-nums; }
        .ks-co-search { display:flex; gap:8px; }
        .ks-co-search .ks-input { width:260px; max-width:60vw; }
        .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
    </style>
@endpush

@section('main')
    <div class="ks-co-top">
        <div class="ks-co-chips">
            <span class="ks-co-chip">Issued <strong>{{ number_format($stats['issued']) }}</strong></span>
            <span class="ks-co-chip">Used <strong>{{ number_format($stats['used']) }}</strong></span>
            <span class="ks-co-chip">Not used <strong>{{ number_format($stats['left']) }}</strong></span>
        </div>
        <form method="GET" class="ks-co-search" role="search">
            <label class="sr-only" for="coSearch">Search beacons</label>
            <input class="ks-input" id="coSearch" name="q" value="{{ $search }}" placeholder="Beacon No. or surveyor…">
            <button class="ks-btn ks-btn-light"><i data-lucide="search" class="w-4 h-4"></i><span class="sr-only">Search</span></button>
        </form>
    </div>

    <div class="ks-card overflow-x-auto">
        <table class="ks-table" style="min-width:{{ $isUsed ? 1180 : 900 }}px;">
            <thead>
                <tr>
                    <th>S/N</th><th>Beacon No.</th><th>Date Issued</th><th>Surveyor</th><th>Request No.</th>
                    @if ($isUsed)
                        <th>Surveyor's Report</th><th>Easting (X)</th><th>Northing (Y)</th><th>Height (Z)</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th>
                    @else
                        <th>Job No.</th><th>Status</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $b)
                    @php $line = $b->register; $rep = $onReport->get($b->id); @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="font-semibold ks-num">{{ \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no) }}</td>
                        <td class="ks-num">{{ $line?->issue_date ? \Illuminate\Support\Carbon::parse($line->issue_date)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $line?->surveyor_name ?: '—' }}</td>
                        <td class="ks-num">{{ $line?->request?->request_no ?: '—' }}</td>
                        @if ($isUsed)
                            <td class="ks-num">{{ $rep?->report_ref ?: '—' }}</td>
                            <td class="ks-num">{{ $b->coord_x !== null ? number_format((float) $b->coord_x, 3) : '—' }}</td>
                            <td class="ks-num">{{ $b->coord_y !== null ? number_format((float) $b->coord_y, 3) : '—' }}</td>
                            <td class="ks-num">{{ $b->coord_z !== null ? number_format((float) $b->coord_z, 3) : '—' }}</td>
                            <td>{{ $rep?->plot_no ?: '—' }}</td>
                            <td>{{ $rep?->district ?: '—' }}</td>
                            <td>{{ $rep?->lga ? mb_strtoupper($rep->lga) : '—' }}</td>
                            <td>{{ $rep?->location ?: ($b->location_details ?: '—') }}</td>
                        @else
                            <td class="ks-num">{{ $line?->request?->job_no ?: '—' }}</td>
                            <td><span class="ks-badge {{ $b->used ? 'accepted' : 'issued' }}">{{ $b->used ? 'Used' : 'Not used' }}</span></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isUsed ? 13 : 7 }}" class="ks-help" style="text-align:center;padding:28px;">
                        @if ($search !== '')
                            No beacon matches “{{ $search }}”.
                        @elseif ($isUsed)
                            No used beacons yet. A beacon is used once your surveyor enters it on a Surveyor's Report with its coordinates.
                        @else
                            No beacons have been issued to your company yet.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
