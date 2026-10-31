{{-- Created By + Date Created cells for Survey listings (user, 2026-10-30). Pass $row (a SurveyModel with creator loaded). --}}
@php
    $by = $row->creator ? trim(($row->creator->first_name ?? '') . ' ' . ($row->creator->last_name ?? '')) : '';
@endphp
<td style="white-space:nowrap;">@if ($by !== ''){{ $by }}@else<span style="color:var(--gray-400);">—</span>@endif</td>
<td style="white-space:nowrap;">
    @if ($row->created_at)
        {{ $row->created_at->format('d M Y') }}
        <span style="display:block;font-size:11px;color:var(--gray-500);">{{ $row->created_at->format('H:i') }}</span>
    @else
        <span style="color:var(--gray-400);">—</span>
    @endif
</td>
