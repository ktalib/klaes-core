{{--
 | The ST Certificate of Occupancy pipeline, as a numbered rail.
 |
 | Shared by the two screens that make up the workflow — Front Page
 | (programmes.certificates) and CofO (programmes.cofo_complete) — so the stages, their
 | order and their counts are defined once and cannot drift apart between the two.
 |
 | Expects:
 |   $stages       array<string, array{label,owner,required,description}>  — StCofoPipeline::STAGES
 |   $stageCounts  array<string, int>   how many certificates have reached each stage
 |   $current      string|null          optional: highlight the stage this screen is
 |
 | Styling comes from cofo_workflow/partials/styles (cw-flow*), which the including page
 | must also pull in.
--}}
@php
    $stages = $stages ?? [];
    $stageCounts = $stageCounts ?? [];
    $current = $current ?? null;
@endphp

@if (!empty($stages))
    {{-- --cw-steps drives the grid's column count, so the rail always spans the full width
         of whatever card it sits in. Hard-coding it is what left the rail looking truncated
         when the stage list changed. --}}
    <nav class="cw-flow-wrap" aria-label="{{ __('CofO workflow') }}">
        <ol class="cw-flow" style="--cw-steps: {{ max(1, count($stages)) }}">
            @foreach ($stages as $key => $stage)
                @php $reached = $stageCounts[$key] ?? 0; @endphp
                <li class="cw-flow-step {{ $current === $key ? 'is-active' : '' }} {{ $reached === 0 ? 'is-zero' : 'has-cards' }}">
                    <span class="cw-flow-dot" title="{{ $stage['description'] ?? '' }}{{ isset($stage['owner']) ? ' — ' . $stage['owner'] : '' }}">
                        {{ $loop->iteration }}
                        <span class="cw-flow-count">{{ number_format($reached) }}</span>
                    </span>
                    <span class="cw-flow-name">{{ $stage['label'] ?? $key }}</span>
                </li>
            @endforeach
        </ol>
    </nav>
@endif
