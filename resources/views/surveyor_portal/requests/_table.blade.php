<div class="ks-card overflow-x-auto">
    <table class="ks-table">
        <thead>
            <tr><th>S/N</th><th>Request No.</th><th>Date</th><th>Request Type</th><th>Job No.</th><th>Survey</th><th>Beacons</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Status</th><th>Issued Numbers</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $q)
                <tr>
                    <td>{{ $first + $loop->index }}</td>
                    <td style="white-space:nowrap;"><a href="{{ route('surveyor-portal.requests.show', $q) }}" style="color:var(--ks-blue);font-weight:600;">{{ $q->request_no }}</a></td>
                    <td style="white-space:nowrap;">{{ optional($q->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $q->request_type ? ucfirst($q->request_type) : '—' }}</td>
                    <td style="white-space:nowrap;">{{ $q->job_no ?: '—' }}</td>
                    <td>{{ $q->job_name ?: '—' }}</td>
                    <td>{{ number_format($q->quantity) }}</td>
                    <td>{{ $q->plot_no ?: '—' }}</td>
                    <td>{{ $q->district ?: '—' }}</td>
                    <td>{{ $q->lga ? mb_strtoupper($q->lga) : '—' }}</td>
                    <td>{{ $q->location ?: '—' }}</td>
                    <td><span class="ks-badge {{ strtolower($q->status) }}">{{ $q->status === 'Rejected' ? 'Not issued' : $q->status }}</span></td>
                    <td>{{ $q->issued_numbers ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="ks-help" style="text-align:center;padding:24px;">No requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
