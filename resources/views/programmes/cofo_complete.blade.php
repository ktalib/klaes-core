@extends('layouts.app')

@section('page-title')
    {{ __('ST Certificate of Occupancy') }}
@endsection

{{--
 | ST Certificate of Occupancy — the CofO Workflow screen.
 |
 |     FRONT  =  C of O Front Page   KLAES / ST   (programmes.certificates)
 |     BACK   =  TDP                 KANGIS / GIS (the Title Deed Plan store, by Kano LGA)
 |
 | Built to the same shape as the ALAES CofO Work Cards queue — summary tiles, the workflow
 | as a numbered rail, filters, then the table — so the two systems read the same way. The
 | cw- classes come from cofo_workflow/partials/styles, the iw- ones from
 | instrument_workflow/partials/styles.
 |
 | Two rules this screen is built around:
 |
 |   1. The pre-conditions (RofO, Deeds registration) are STATE, not steps. They happened
 |      elsewhere. They are shown and never chased from here.
 |   2. Ordering is not enforced. "Awaiting TDP" is normal, not an error — the plan comes
 |      from another department. Only the combined certificate needs both sides.
--}}

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', [
        'PageTitle' => 'ST Certificate of Occupancy',
        'PageDescription' => 'Sectional Titling · CofO Workflow — front page, Title Deed Plan and the complete certificate',
    ])
    @include('instrument_workflow.partials.styles')
    @include('cofo_workflow.partials.styles')

    <div class="iw-page cw-page space-y-5">
        @include('instrument_workflow.partials.flash')

        <div class="iw-crumbs" style="margin-bottom:0">
            <span>Sectional Titling</span><span>/</span><span>Certificate</span><span>/</span>
            <span class="text-gray-900">CofO Workflow</span>
        </div>

        {{-- ── Summary tiles ──────────────────────────────────────────────── --}}
        <div class="cw-tiles">
            <a href="{{ route('programmes.cofo_complete') }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                <span class="cw-icon-tile blue"><i data-lucide="layers" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['live']) }}</div>
                    {{-- Units in the workflow: a RofO has been issued, so the certificate is
                         due. Most have no CofO record yet, which is the point of the queue. --}}
                    <div class="cw-tile-label">
                        Units awaiting a certificate
                        <span class="cw-sub">{{ number_format($totals['captured']) }} captured so far</span>
                    </div>
                </div>
            </a>
            <a href="{{ route('programmes.cofo_complete', ['status' => 'registered']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                <span class="cw-icon-tile purple"><i data-lucide="stamp" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['registered']) }}</div>
                    <div class="cw-tile-label">Registered (Deeds)</div>
                </div>
            </a>
            <a href="{{ route('programmes.cofo_complete', ['status' => 'complete']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                <span class="cw-icon-tile green"><i data-lucide="book-copy" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['complete']) }}</div>
                    <div class="cw-tile-label">Complete (front + back)</div>
                </div>
            </a>
            <a href="{{ route('programmes.cofo_complete', ['status' => 'awaiting']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                <span class="cw-icon-tile yellow"><i data-lucide="clock" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['awaiting']) }}</div>
                    <div class="cw-tile-label">Awaiting TDP from GIS</div>
                </div>
            </a>
            <div class="iw-card cw-tile">
                <span class="cw-icon-tile gray"><i data-lucide="badge-check" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['originals']) }}</div>
                    <div class="cw-tile-label">Originals issued</div>
                </div>
            </div>
        </div>

        {{-- No inline grid-template-columns here: an inline value beats the stylesheet's
             media query, so the side panel never dropped below the table and the table was
             squeezed until it scrolled sideways when the browser was zoomed. The layout
             lives in cofo_workflow/partials/styles (.cw-layout). --}}
        <div class="iw-layout cw-layout">
            {{-- ── Main column ────────────────────────────────────────────── --}}
            <div class="iw-card" style="overflow:hidden">
                <div class="iw-card-head" style="align-items:center">
                    <div>
                        <div class="iw-card-title">
                            <span class="iw-icon-tile blue"><i data-lucide="list-checks" class="h-5 w-5"></i></span>
                            {{ $status === 'complete' ? 'Complete certificates' : ($status === 'awaiting' ? 'Awaiting TDP' : 'All certificates') }}
                        </div>
                        <div class="iw-card-sub">
                            {{ number_format($certificates->count()) }}
                            {{ \Illuminate\Support\Str::plural('certificate', $certificates->count()) }}
                            · front page from KLAES/ST, back page from KANGIS/GIS
                        </div>
                    </div>
                    <a href="{{ route('programmes.certificates') }}" class="iw-btn iw-btn-light iw-btn-sm">
                        <i data-lucide="file-plus" class="h-4 w-4"></i> Front Page screen
                    </a>
                </div>

                {{-- The workflow, in order. A display, not a set of buttons: the first two
                     stages happen elsewhere and the rest are driven from the row actions.
                     Same partial the Front Page screen uses, so the two cannot drift. --}}
                @include('cofo_workflow.partials.pipeline', [
                    'stages' => $stages,
                    'stageCounts' => $stageCounts,
                    'current' => 'tdp',
                ])

                {{-- ── Filters ────────────────────────────────────────────── --}}
                <form method="GET" action="{{ route('programmes.cofo_complete') }}" class="cw-filters">
                    <div class="iw-input-group">
                        <i data-lucide="search"></i>
                        <input type="text" name="q" value="{{ $search }}" class="iw-input"
                               placeholder="{{ __('File number, certificate number, holder or plot') }}">
                    </div>
                    <select name="status" class="iw-select" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="complete" @selected($status === 'complete')>{{ __('Complete (front + back)') }}</option>
                        <option value="awaiting" @selected($status === 'awaiting')>{{ __('Awaiting TDP') }}</option>
                        <option value="registered" @selected($status === 'registered')>{{ __('Registered (Deeds)') }}</option>
                    </select>
                    <button class="iw-btn iw-btn-primary iw-btn-sm" type="submit">
                        <i data-lucide="filter" class="h-4 w-4"></i> {{ __('Apply') }}
                    </button>
                    @if ($search !== '' || $status !== '')
                        <a href="{{ route('programmes.cofo_complete') }}" class="iw-btn iw-btn-light iw-btn-sm">
                            <i data-lucide="x" class="h-4 w-4"></i> {{ __('Clear') }}
                        </a>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="cw-table">
                        <thead>
                            <tr>
                                <th>{{ __('File No') }}</th>
                                <th>{{ __('Holder') }}</th>
                                <th>{{ __('Property') }}</th>
                                <th>{{ __('Status / stage') }}</th>
                                <th>{{ __('Back page (TDP)') }}</th>
                                <th style="text-align:right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($certificates as $cert)
                                <tr class="cw-row">
                                    <td style="white-space:nowrap">
                                        <span class="cw-file">{{ $cert->file_no ?: '—' }}</span>
                                        @if ($cert->certificate_number)
                                            <div class="cw-sub iw-mono" style="font-size:11px">{{ $cert->certificate_number }}</div>
                                        @endif
                                    </td>
                                    <td style="max-width:190px">
                                        <div class="font-medium text-gray-900 truncate" title="{{ $cert->holder_name }}">
                                            {{ $cert->holder_name ?: '—' }}
                                        </div>
                                        {{-- The two pre-conditions, as state. Never blocked on.
                                             "Captured" says what an unregistered row actually is:
                                             the CofO data is in, but with no registration
                                             particulars there is no front page to print. --}}
                                        <div style="margin-top:3px">
                                            {{-- A stranded RofO is real and signed, but filed under the
                                                 unit id this record carried before subapplications was
                                                 renumbered. The RofO Applications screen joins on the
                                                 current id and so reports "Not Generated". Saying so here
                                                 is the point: two screens disagreeing with no explanation
                                                 is worse than either answer. --}}
                                            @if ($cert->rofo_stranded ?? false)
                                                <span class="cw-pill red" style="font-size:10px"
                                                      title="A signed RofO exists for this unit but is filed under unit id {{ $cert->sub_application_id }}, while subapplications now uses {{ $cert->canonical_unit_id }}. The RofO Applications screen cannot see it.">
                                                    RofO — link broken
                                                </span>
                                            @else
                                                <span class="cw-pill {{ $cert->stages_done['rofo'] ? 'green' : '' }}" style="font-size:10px">
                                                    {{ $cert->stages_done['rofo'] ? 'RofO' : 'No RofO' }}
                                                </span>
                                            @endif
                                            {{-- Three distinct states, not two. Most rows in this queue
                                                 have no CofO record at all — they are units whose RofO is
                                                 issued and whose certificate has not been started. Saying
                                                 "captured" for those claimed work nobody had done. --}}
                                            @if ($cert->stages_done['registration'])
                                                <span class="cw-pill purple" style="font-size:10px">Registered</span>
                                            @elseif ($cert->cofo_id)
                                                <span class="cw-pill" style="font-size:10px">Captured, not registered</span>
                                            @else
                                                <span class="cw-pill" style="font-size:10px">No CofO record yet</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td style="max-width:170px">
                                        <div class="truncate">
                                            {{ collect([$cert->plot_no, $cert->block_no, $cert->flat_no])->filter()->implode(' / ') ?: '—' }}
                                        </div>
                                        <div class="cw-sub truncate">
                                            {{ collect([$cert->property_district, $cert->property_lga])->filter()->implode(', ') }}
                                        </div>
                                    </td>
                                    {{-- Stage and status in one column: two columns made the table
                                         wider than its card once the browser was zoomed. --}}
                                    <td>
                                        @include('cofo_workflow.partials.status_pill', ['status' => $cert->fsm_status])
                                        <div class="cw-sub" style="margin-top:3px">
                                            {{ $stages[$cert->current_stage]['label'] ?? $cert->current_stage }}
                                            · {{ $stages[$cert->current_stage]['owner'] ?? '' }}
                                            · {{ $cert->stages_complete }}/{{ count($stages) }}
                                        </div>
                                    </td>
                                    <td style="white-space:nowrap">
                                        {{-- Two sources, and the badge says which. A plan read off the GIS
                                             server is the real thing; an upload is the stand-in kept for
                                             plans that have not been filed there yet. --}}
                                        @if ($cert->tdp_source === 'gis')
                                            <span class="cw-pill green"><i data-lucide="hard-drive" class="h-3 w-3"></i> {{ __('On GIS server') }}</span>
                                            <div class="cw-sub">{{ $cert->tdp_plan['lga'] }}</div>
                                            <a href="{{ route('tdp.file', ['path' => $cert->tdp_plan['relative_path']]) }}"
                                               target="_blank" class="cw-link" style="font-size:11px">{{ __('View plan') }}</a>
                                        @elseif ($cert->tdp_source === 'upload')
                                            <span class="cw-pill blue"><i data-lucide="upload" class="h-3 w-3"></i> {{ __('Attached') }}</span>
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cert->tdp->file_path) }}"
                                               target="_blank" class="cw-link" style="font-size:11px">{{ __('View plan') }}</a>
                                        @else
                                            <span class="cw-pill yellow"><i data-lucide="clock" class="h-3 w-3"></i> {{ __('Awaiting') }}</span>
                                            <div class="cw-sub">{{ __('from KANGIS / GIS') }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:right; white-space:nowrap">
                                        {{-- The back page is only sought once the certificate is
                                             registered. Filing a TDP against an unregistered certificate
                                             attaches a plan to something that cannot yet be issued, and
                                             the back page would sit waiting on a front page that has no
                                             registration particulars to print. --}}
                                        @if ($cert->stages_done['registration'])
                                            <a href="{{ route('tdp.index', ['q' => $cert->file_no]) }}" class="iw-btn iw-btn-light iw-btn-sm">
                                                <i data-lucide="search" class="h-4 w-4"></i> {{ __('Find TDP') }}
                                            </a>
                                        @else
                                            <button type="button" class="iw-btn iw-btn-light iw-btn-sm" disabled
                                                    title="{{ __('The certificate must be registered with Deeds before its Title Deed Plan is filed.') }}">
                                                <i data-lucide="search" class="h-4 w-4"></i> {{ __('Find TDP') }}
                                            </button>
                                        @endif
                                        @if ($cert->has_tdp)
                                            <a href="{{ route('programmes.cofo_complete.print', $cert->sub_application_id) }}"
                                               target="_blank" class="iw-btn iw-btn-primary iw-btn-sm">
                                                <i data-lucide="printer" class="h-4 w-4"></i> {{ __('Print complete') }}
                                            </a>
                                        @else
                                            <button type="button" class="iw-btn iw-btn-light iw-btn-sm" disabled
                                                    title="{{ __('No back page yet') }}">
                                                <i data-lucide="printer" class="h-4 w-4"></i> {{ __('Print complete') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:40px 18px">
                                        <div class="iw-empty">
                                            <i data-lucide="inbox" class="h-6 w-6 mx-auto mb-2 text-gray-400"></i>
                                            @if ($search !== '' || $status !== '')
                                                {{ __('No certificates match these filters.') }}
                                            @else
                                                {{ __('No front pages generated yet. Generate one on the Front Page screen.') }}
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── Rail ───────────────────────────────────────────────────── --}}
            <aside class="space-y-4 iw-sticky">
                <div class="iw-card">
                    <div class="iw-card-head">
                        <div>
                            <div class="iw-card-title">
                                <span class="iw-icon-tile {{ $store['state'] === 'ready' ? 'green' : 'yellow' }}">
                                    <i data-lucide="{{ $store['state'] === 'ready' ? 'hard-drive' : 'plug-zap' }}" class="h-5 w-5"></i>
                                </span>
                                {{ __('Title Deed Plan store') }}
                            </div>
                            <div class="iw-card-sub">{{ __('KANGIS / GIS · one folder per Kano LGA') }}</div>
                        </div>
                    </div>
                    <div class="iw-card-body">
                        <p class="text-sm text-gray-600" style="margin-bottom:10px">{{ $store['message'] }}</p>
                        <div class="cw-rail-row"><span>{{ __('From the GIS store') }}</span><strong>{{ number_format($totals['from_gis']) }}</strong></div>
                        <div class="cw-rail-row"><span>{{ __('Attached here') }}</span><strong>{{ number_format($totals['from_upload']) }}</strong></div>
                        <div class="cw-rail-row"><span>{{ __('Still awaiting') }}</span><strong>{{ number_format($totals['awaiting']) }}</strong></div>
                        <a href="{{ route('tdp.index') }}" class="iw-btn iw-btn-primary iw-btn-sm" style="width:100%; margin-top:12px; justify-content:center">
                            <i data-lucide="folder-open" class="h-4 w-4"></i> {{ __('Open the plan store') }}
                        </a>
                    </div>
                </div>

                <div class="iw-card">
                    <div class="iw-card-head">
                        <div>
                            <div class="iw-card-title">
                                <span class="iw-icon-tile blue"><i data-lucide="route" class="h-5 w-5"></i></span>
                                {{ __('How this works') }}
                            </div>
                        </div>
                    </div>
                    <div class="iw-card-body">
                        <ol class="text-sm text-gray-600" style="padding-left:18px; line-height:1.7">
                            @foreach ($stages as $stage)
                                <li>
                                    <strong class="text-gray-900">{{ $stage['label'] }}</strong>
                                    <span class="cw-sub"> · {{ $stage['owner'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="cw-sub" style="margin-top:10px">
                            {{ __('The first two happen before a record reaches this screen. A front page may be generated while its TDP is still pending — that is normal.') }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- The footer belongs to every screen in this module (see programmes/rofo.blade.php).
         It was lost when this view was rebuilt around the stage rail, leaving the page
         running off the bottom of the content with no close. --}}
    @include($footerPartial ?? 'admin.footer')
</div>
@endsection
