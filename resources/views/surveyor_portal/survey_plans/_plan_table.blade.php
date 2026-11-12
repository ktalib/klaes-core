{{--
    Survey plans as a read-only table (user, 2026-11-12): Survey Plan Upload > Signed for a surveyor, and
    Uploaded Survey Plans > Original / Signed for a company. Params: $rows (paginator, files loaded),
    $showSurveyor, $openUrl (fn or null), $originalUrl (fn), $signedUrl (fn), $emptyText.
--}}
@php
    use App\Models\Survey\SurveyPlanFile as PlanFile;
    use App\Models\Survey\SurveyPlanUpload as Plan;
@endphp
@once
@push('styles')
    <style>
        .ks-pl-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .ks-pl-search { display:flex; gap:8px; }
        .ks-pl-search .ks-input { width:260px; max-width:60vw; }
        .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .ks-menu { position:relative; display:inline-block; }
        .ks-menu-btn { display:grid; place-items:center; width:32px; height:32px; border:1px solid var(--ks-line); border-radius:8px; background:#fff; cursor:pointer; }
        .ks-menu-list { position:absolute; right:0; top:36px; z-index:20; min-width:190px; padding:6px; border:1px solid var(--ks-line); border-radius:10px; background:#fff; box-shadow:0 12px 28px rgba(20,23,26,.14); }
        .ks-menu-list a { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:7px; color:var(--ks-ink); font-size:13px; text-decoration:none; }
        .ks-menu-list a:hover { background:#F3F6FA; }
    </style>
@endpush
@push('scripts')
<script>
(function () {
    function closeAll(except) {
        document.querySelectorAll('.ks-menu-list').forEach(function (m) {
            if (m === except) return;
            m.hidden = true;
            var b = m.parentElement.querySelector('.ks-menu-btn');
            if (b) b.setAttribute('aria-expanded', 'false');
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ks-menu-btn');
        if (btn) {
            e.stopPropagation();
            var list = btn.parentElement.querySelector('.ks-menu-list'), open = list.hidden;
            closeAll(list);
            list.hidden = !open;
            if (open) {
                // Placed against the window so a scrolling table never clips it.
                var rc = btn.getBoundingClientRect();
                list.style.position = 'fixed';
                list.style.top = (rc.bottom + 4) + 'px';
                list.style.left = Math.max(8, rc.right - list.offsetWidth) + 'px';
                list.style.right = 'auto';
            }
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (!e.target.closest('.ks-menu-list')) closeAll();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
    window.addEventListener('scroll', function () { closeAll(); }, true);
})();
</script>
@endpush
@endonce

<div class="ks-card overflow-x-auto">
    <table class="ks-table" style="min-width:1000px;">
        <thead>
            <tr>
                <th>S/N</th><th>Upload Ref</th><th>Type</th>@if ($showSurveyor)<th>Surveyor</th>@endif<th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th>
                <th>Sent To</th><th>Uploaded</th><th>Stage</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $p)
                @php $signed = $p->latestFile(PlanFile::KIND_SIGNED); @endphp
                <tr>
                    <td>{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="font-semibold ks-num">{{ $p->upload_ref }}</td>
                    <td>{{ $p->plan_type }}</td>
                    @if ($showSurveyor)
                        <td>{{ optional($p->portalAccount)->name ?: '—' }}<div class="ks-help" style="margin:0;">{{ optional($p->portalAccount)->surveyor_id_no }}</div></td>
                    @endif
                    <td>{{ $p->plot_no ?: '—' }}</td>
                    <td>{{ $p->district ?: '—' }}</td>
                    <td>{{ $p->lga ?: '—' }}</td>
                    <td>{{ $p->location ?: '—' }}</td>
                    <td>{{ $p->unit }}</td>
                    <td class="ks-num">{{ optional($p->created_at)->format('d/m/Y') }}</td>
                    <td style="white-space:nowrap;">
                        @if ($p->stage_no === Plan::STAGE_SIGNED)
                            <span class="ks-badge accepted">Signed</span>
                            @if ($signed)<div class="ks-help" style="margin:0;">{{ $signed->created_at?->format('d/m/Y') }} · {{ $p->signing_method === Plan::SIGN_DIGITAL ? 'digital' : 'by hand' }}</div>@endif
                        @else
                            {{ $p->stage_no }} · {{ $p->stage_label }}<div class="ks-help" style="margin:0;">{{ $p->stage_status }}</div>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <div class="ks-menu">
                            <button type="button" class="ks-menu-btn" aria-label="Actions" aria-haspopup="menu" aria-expanded="false"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                            <div class="ks-menu-list" role="menu" hidden>
                                @if ($openUrl)
                                    <a role="menuitem" href="{{ $openUrl($p) }}"><i data-lucide="eye" class="w-4 h-4"></i> Open</a>
                                @endif
                                <a role="menuitem" href="{{ $originalUrl($p) }}" target="_blank" rel="noopener"><i data-lucide="file-text" class="w-4 h-4"></i> Original plan</a>
                                @if ($signed)
                                    <a role="menuitem" href="{{ $signedUrl($p, $signed) }}" target="_blank" rel="noopener"><i data-lucide="file-check-2" class="w-4 h-4"></i> Signed copy</a>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $showSurveyor ? 12 : 11 }}" class="ks-help" style="text-align:center;padding:28px;">{{ $emptyText }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $rows->links() }}</div>
