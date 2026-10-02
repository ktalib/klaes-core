{{--
 | SLTR's own copy of cofo_workflow/partials/pipeline (cloned 2026-10-02).
 |
 | The SLTR Certificate of Occupancy pipeline, as a numbered rail. Used by the two CofO
 | Workflow screens (sltr-cofo.front-page, sltr-cofo.index) and by the SLTR Recommendations
 | screen and its New Recommendation form, which prepend a Recommendation step
 | (SltrCofoPipeline::RECOMMENDATION_STAGE) and show no counts or links.
 |
 | Expects:
 |   $stages       array<string, array{label,owner,required,description}>  — SltrCofoPipeline::STAGES
 |   $stageCounts  array<string, int>   how many files have reached each stage
 |   $current      string|null          optional: highlight the stage this screen is
 |   $links        array<string, string> optional: stage => URL; linked stages are clickable
 |   $showCounts   bool                 optional, default true: false hides the count bubbles
 |   $compact      bool                 optional: smaller dots, for a modal or narrow card
 |
 | Styling comes from sltr_cofo/partials/styles (cw-flow*), which the including page must
 | also pull in.
--}}
@php
    $stages = $stages ?? [];
    $stageCounts = $stageCounts ?? [];
    $current = $current ?? null;
    $links = $links ?? [];
    $showCounts = $showCounts ?? true;
    $compact = $compact ?? false;
@endphp

@if (!empty($stages))
    {{-- --cw-steps drives the grid's column count, so the rail always spans the full width
         of whatever card it sits in. --}}
    <nav class="cw-flow-wrap {{ $compact ? 'cw-flow-compact' : '' }}" aria-label="{{ __('CofO workflow') }}">
        <ol class="cw-flow" style="--cw-steps: {{ max(1, count($stages)) }}">
            @foreach ($stages as $key => $stage)
                @php
                    $reached = $stageCounts[$key] ?? 0;
                    $href = $links[$key] ?? null;
                    $tag = $href ? 'a' : 'span';
                @endphp
                <li class="cw-flow-step {{ $current === $key ? 'is-active' : '' }} {{ $showCounts ? ($reached === 0 ? 'is-zero' : 'has-cards') : '' }}">
                    <{{ $tag }} @if ($href) href="{{ $href }}" @endif class="cw-flow-dot {{ $href ? 'is-link' : '' }}"
                        title="{{ $stage['description'] ?? '' }}{{ isset($stage['owner']) ? ' — ' . $stage['owner'] : '' }}">
                        {{ $loop->iteration }}
                        @if ($showCounts)
                            <span class="cw-flow-count">{{ number_format($reached) }}</span>
                        @endif
                    </{{ $tag }}>
                    <{{ $tag }} @if ($href) href="{{ $href }}" @endif class="cw-flow-name">{{ $stage['label'] ?? $key }}</{{ $tag }}>
                </li>
            @endforeach
        </ol>
    </nav>
@endif
