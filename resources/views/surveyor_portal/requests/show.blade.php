@extends('surveyor_portal.layouts.portal')

@section('title', $request->request_no . ' — MLPP-SG Portal')
@section('heading', 'Request ' . $request->request_no)

@section('main')
    @php
        $rows = [
            'Status' => null, 'Date' => optional($request->created_at)->format('d/m/Y'), 'Beacon Type' => $request->type_label,
            'Request Type' => $request->request_type ? ucfirst($request->request_type) : null,
            'Amount Paid (₦)' => $request->amount_paid !== null ? number_format((float) $request->amount_paid, 2) : null,
            'Date Paid' => optional($request->payment_date)->format('d/m/Y'),
            'Company' => $request->company_name, "Surveyor's Name" => $request->surveyor_name, 'Job No.' => $request->job_no, 'Name of Survey' => $request->job_name,
            'Number of Beacons' => $request->quantity, 'Plot No.' => $request->plot_no, 'District' => $request->district,
            'LGA' => $request->lga ? mb_strtoupper($request->lga) : null, 'Location' => $request->location,
            'Map Pin' => $request->latitude !== null ? round($request->latitude, 6) . ', ' . round($request->longitude, 6) : null, 'Remarks' => $request->remarks,
        ];
    @endphp
    <div class="ks-card p-6 mb-4">
        <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
            @foreach ($rows as $label => $value)
                <div class="flex justify-between gap-4" style="border-bottom:1px solid #ECEEF0;padding:6px 0;">
                    <dt class="ks-help">{{ $label }}</dt>
                    <dd class="font-semibold text-right">
                        @if ($label === 'Status')
                            <span class="ks-badge {{ strtolower($request->status) }}">{{ $request->status === 'Rejected' ? 'Not issued' : $request->status }}</span>
                        @else
                            {{ $value ?: '—' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        @if ($request->review_note)
            <div class="ks-flash bad" style="margin:16px 0 0;">{{ $request->review_note }}</div>
        @endif
    </div>

    @if (in_array($request->status, ['Issued', 'Accepted'], true))
        <div class="ks-card p-6">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                <h2 class="font-bold">Issued Beacons</h2>
                <div class="flex gap-2 flex-wrap">
                    @if ($request->status === 'Issued')
                        <form method="POST" action="{{ route('surveyor-portal.requests.accept', $request) }}">
                            @csrf
                            <button class="ks-btn ks-btn-green"><i data-lucide="check" class="w-4 h-4"></i> Accept Beacons</button>
                        </form>
                    @endif
                    <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.requests.slip', $request) }}" target="_blank"><i data-lucide="printer" class="w-4 h-4"></i> Print Slip</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="ks-table">
                    <thead><tr><th>S/N</th><th>Beacon No.</th></tr></thead>
                    <tbody>
                        @php $sn = 0; @endphp
                        @foreach ($request->lines as $line)
                            @foreach ($line->numbers as $n)
                                <tr><td>{{ ++$sn }}</td><td class="font-semibold">{{ \App\Models\Survey\SurveyBeaconRegister::display($n->beacon_no) }}</td></tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
