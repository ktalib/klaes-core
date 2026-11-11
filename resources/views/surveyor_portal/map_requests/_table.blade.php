@php $badge = ['pending' => 'submitted', 'in_progress' => 'issued', 'fulfilled' => 'accepted', 'rejected' => 'rejected']; @endphp
<div class="ks-card overflow-x-auto">
    <table class="ks-table">
        <thead>
            <tr><th>S/N</th><th>Request No.</th><th>Date</th><th>Map</th><th>Map Type</th><th>Formats</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td>{{ $first + $loop->index }}</td>
                    <td style="white-space:nowrap;"><a href="{{ route('surveyor-portal.map-requests.show', $m) }}" style="color:var(--ks-blue);font-weight:600;">{{ $m->request_ref }}</a></td>
                    <td style="white-space:nowrap;">{{ optional($m->received_date)->format('d/m/Y') }}</td>
                    <td>{{ $m->map_name }}</td>
                    <td>{{ $m->mapTypeLabel() ?: '—' }}</td>
                    <td>{{ $m->formatsLabel() }}</td>
                    <td><span class="ks-badge {{ $badge[$m->status] ?? '' }}">{{ $m->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="ks-help" style="text-align:center;padding:24px;">No map requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
