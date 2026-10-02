@extends('layouts.app')

@section('page-title')
    {{ __('SLTR Certificate of Occupancy') }}
@endsection

{{--
 | SLTR Certificate of Occupancy — the CofO Workflow screens (Front Page and CofO).
 |
 | The ST screen (programmes/cofo_complete) copied for SLTR: same tiles, rail, filters and
 | table, same shared partials, in the SLTR lime theme. One view serves both screens; the
 | $screen variable only changes which actions the rows offer and which stage is lit.
 |
 | The rows come from SltrCofoPipeline, which also produces the rail counts, so the two
 | always agree.
--}}

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', [
        'PageTitle' => 'SLTR Certificate of Occupancy',
        'PageDescription' => 'Systematic Land Titling · CofO Workflow — front page, Title Deed Plan and the complete certificate',
    ])
    @include('instrument_workflow.partials.styles')
    @include('sltr_cofo.partials.styles')
    @include('sltr_cofo.partials.theme')

    @php
        $isFrontPage = $screen === 'front_page';
        $screenRoute = $isFrontPage ? 'sltr-cofo.front-page' : 'sltr-cofo.index';
    @endphp

    <div class="iw-page cw-page sltr-theme space-y-5">
        <div class="sltr-band"></div>

        @include('instrument_workflow.partials.flash')

        @if (session('sltr_cofo_print'))
            <div class="iw-alert info" style="display:flex; align-items:center; justify-content:space-between; gap:12px">
                <span>{{ __('The front page is ready to print.') }}</span>
                <a href="{{ route('sltr-cofo.front-page.print', session('sltr_cofo_print')) }}" target="_blank" class="iw-btn iw-btn-primary iw-btn-sm">
                    <i data-lucide="printer" class="h-4 w-4"></i> {{ __('Print front page') }}
                </a>
            </div>
        @endif

        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
            <div class="iw-crumbs" style="margin-bottom:0">
                <span>SLTR</span><span>/</span><span>Certificate</span><span>/</span>
                <span>CofO Workflow</span><span>/</span>
                <span class="text-gray-900">{{ $isFrontPage ? 'Front Page' : 'CofO' }}</span>
            </div>
            <nav class="sltr-tabs" aria-label="{{ __('CofO Workflow screens') }}">
                {{-- Each tab is its own module (SLTR - CofO Front Page / SLTR - CofO); a tab
                     the user cannot open is not shown. --}}
                @if ($canSeeFrontPage)
                    <a href="{{ route('sltr-cofo.front-page') }}" class="sltr-tab {{ $isFrontPage ? 'active' : '' }}">
                        <i data-lucide="file-text" class="h-4 w-4"></i> {{ __('Front Page') }}
                    </a>
                @endif
                @if ($canSeeCofo)
                    <a href="{{ route('sltr-cofo.index') }}" class="sltr-tab {{ $isFrontPage ? '' : 'active' }}">
                        <i data-lucide="file-cog" class="h-4 w-4"></i> {{ __('CofO') }}
                    </a>
                @endif
            </nav>
        </div>

        {{-- ── Summary tiles ──────────────────────────────────────────────── --}}
        <div class="cw-tiles">
            <a href="{{ route($screenRoute) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                <span class="cw-icon-tile blue"><i data-lucide="layers" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['live']) }}</div>
                    <div class="cw-tile-label">
                        Files in the workflow
                        <span class="cw-sub">{{ number_format($totals['front_pages']) }} with a front page</span>
                    </div>
                </div>
            </a>
            <a href="{{ route($screenRoute, ['status' => 'registered']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                <span class="cw-icon-tile purple"><i data-lucide="stamp" class="h-5 w-5"></i></span>
                <div>
                    <div class="cw-tile-value">{{ number_format($totals['registered']) }}</div>
                    <div class="cw-tile-label">Registered (Deeds)</div>
                </div>
            </a>
            @if ($isFrontPage)
                <a href="{{ route($screenRoute, ['status' => 'front_page_due']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                    <span class="cw-icon-tile yellow"><i data-lucide="file-plus" class="h-5 w-5"></i></span>
                    <div>
                        <div class="cw-tile-value">{{ number_format($totals['registered'] - $totals['front_pages']) }}</div>
                        <div class="cw-tile-label">Front page due</div>
                    </div>
                </a>
                <a href="{{ route($screenRoute, ['status' => 'front_page_done']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                    <span class="cw-icon-tile green"><i data-lucide="file-check" class="h-5 w-5"></i></span>
                    <div>
                        <div class="cw-tile-value">{{ number_format($totals['front_pages']) }}</div>
                        <div class="cw-tile-label">Front pages generated</div>
                    </div>
                </a>
            @else
                <a href="{{ route($screenRoute, ['status' => 'complete']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                    <span class="cw-icon-tile green"><i data-lucide="book-copy" class="h-5 w-5"></i></span>
                    <div>
                        <div class="cw-tile-value">{{ number_format($totals['complete']) }}</div>
                        <div class="cw-tile-label">Complete (front + back)</div>
                    </div>
                </a>
                <a href="{{ route($screenRoute, ['status' => 'awaiting']) }}" class="iw-card cw-tile hover:shadow-md transition-shadow">
                    <span class="cw-icon-tile yellow"><i data-lucide="clock" class="h-5 w-5"></i></span>
                    <div>
                        <div class="cw-tile-value">{{ number_format($totals['awaiting']) }}</div>
                        <div class="cw-tile-label">Awaiting TDP from GIS</div>
                    </div>
                </a>
            @endif
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
             squeezed until it scrolled sideways when the browser was zoomed. --}}
        <div class="iw-layout sltr-layout">
            {{-- ── Main column ────────────────────────────────────────────── --}}
            <div class="iw-card" style="overflow:hidden">
                <div class="iw-card-head" style="align-items:center">
                    <div>
                        <div class="iw-card-title">
                            <span class="iw-icon-tile blue"><i data-lucide="list-checks" class="h-5 w-5"></i></span>
                            {{ $isFrontPage ? __('Front pages') : __('Certificates') }}
                        </div>
                        <div class="iw-card-sub">
                            {{ number_format($certificates->count()) }}
                            {{ \Illuminate\Support\Str::plural('file', $certificates->count()) }}
                            · front page from KLAES/SLTR, back page from KANGIS/GIS
                        </div>
                    </div>
                </div>

                @include('sltr_cofo.partials.pipeline', [
                    'stages' => $stages,
                    'stageCounts' => $stageCounts,
                    'current' => $isFrontPage ? 'front_page' : 'tdp',
                ])

                {{-- ── Filters ────────────────────────────────────────────── --}}
                <form method="GET" action="{{ route($screenRoute) }}" class="cw-filters">
                    <div class="iw-input-group">
                        <i data-lucide="search"></i>
                        <input type="text" name="q" value="{{ $search }}" class="iw-input"
                               placeholder="{{ __('File number, registration number, holder, plot or district') }}">
                    </div>
                    <select name="status" class="iw-select" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="registered" @selected($status === 'registered')>{{ __('Registered (Deeds)') }}</option>
                        <option value="unregistered" @selected($status === 'unregistered')>{{ __('Not registered yet') }}</option>
                        <option value="front_page_due" @selected($status === 'front_page_due')>{{ __('Front page due') }}</option>
                        <option value="front_page_done" @selected($status === 'front_page_done')>{{ __('Front page generated') }}</option>
                        <option value="complete" @selected($status === 'complete')>{{ __('Complete (front + back)') }}</option>
                        <option value="awaiting" @selected($status === 'awaiting')>{{ __('Awaiting TDP') }}</option>
                    </select>
                    <button class="iw-btn iw-btn-primary iw-btn-sm" type="submit">
                        <i data-lucide="filter" class="h-4 w-4"></i> {{ __('Apply') }}
                    </button>
                    @if ($search !== '' || $status !== '')
                        <a href="{{ route($screenRoute) }}" class="iw-btn iw-btn-light iw-btn-sm">
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
                                <th>{{ $isFrontPage ? __('Registration') : __('Back page (TDP)') }}</th>
                                <th style="text-align:right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($certificates as $cert)
                                <tr class="cw-row">
                                    <td style="white-space:nowrap">
                                        <span class="cw-file">{{ $cert->file_no ?: '—' }}</span>
                                        @if ($cert->registration_number)
                                            <div class="cw-sub iw-mono" style="font-size:11px">{{ $cert->registration_number }}</div>
                                        @endif
                                    </td>
                                    <td style="max-width:190px">
                                        <div class="font-medium text-gray-900 truncate" title="{{ $cert->holder_name }}">
                                            {{ $cert->holder_name ?: '—' }}
                                        </div>
                                        <div style="margin-top:3px">
                                            <span class="cw-pill {{ $cert->stages_done['rofo'] ? 'green' : '' }}" style="font-size:10px"
                                                  title="{{ $cert->stages_done['rofo'] ? '' : __('No SLTR recommendation with a generated RofO for this file number') }}">
                                                {{ $cert->stages_done['rofo'] ? 'RofO' : 'No RofO in KLAES' }}
                                            </span>
                                            @if ($cert->stages_done['registration'])
                                                <span class="cw-pill purple" style="font-size:10px">Registered</span>
                                            @else
                                                <span class="cw-pill" style="font-size:10px">Not registered</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td style="max-width:170px">
                                        <div class="truncate">{{ $cert->plot_no ?: '—' }}</div>
                                        <div class="cw-sub truncate">
                                            {{ collect([$cert->district, $cert->lga])->filter()->implode(', ') }}
                                        </div>
                                    </td>
                                    {{-- Stage and status in one column: two columns made the table
                                         wider than its card once the browser was zoomed. --}}
                                    <td>
                                        @include('sltr_cofo.partials.status_pill', ['status' => $cert->fsm_status])
                                        <div class="cw-sub" style="margin-top:3px">
                                            {{ $stages[$cert->current_stage]['label'] ?? $cert->current_stage }}
                                            · {{ $stages[$cert->current_stage]['owner'] ?? '' }}
                                            · {{ $cert->stages_complete }}/{{ count($stages) }}
                                        </div>
                                    </td>

                                    @if ($isFrontPage)
                                        <td style="white-space:nowrap">
                                            @php
                                                $regNo = $cert->registration_number;
                                                $regDate = $cert->deed->deeds_date ?? null;
                                            @endphp
                                            @if ($regNo)
                                                <span class="iw-mono font-semibold text-gray-900">{{ $regNo }}</span>
                                                <div class="cw-sub">
                                                    {{ __('Vol') }} {{ $cert->deed->volume_no ?? '—' }}
                                                    · {{ __('Page') }} {{ $cert->deed->page_no ?? '—' }}
                                                    @if ($regDate) · {{ \Carbon\Carbon::parse($regDate)->format('d M Y') }} @endif
                                                </div>
                                            @else
                                                <span class="cw-pill">{{ __('Awaiting Deeds') }}</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right; white-space:nowrap">
                                            @if ($cert->stages_done['front_page'])
                                                <a href="{{ route('sltr-cofo.front-page.print', $cert->capture_id) }}" target="_blank" class="iw-btn iw-btn-light iw-btn-sm">
                                                    <i data-lucide="printer" class="h-4 w-4"></i> {{ __('Front page') }}
                                                </a>
                                                @if ($canEdit)
                                                    <a href="{{ route('sltr-cofo.generate', ['file' => $cert->file_no]) }}" class="iw-btn iw-btn-light iw-btn-sm">
                                                        <i data-lucide="pencil" class="h-4 w-4"></i> {{ __('Edit') }}
                                                    </a>
                                                @endif
                                            @elseif ($cert->stages_done['registration'] && $canEdit)
                                                <a href="{{ route('sltr-cofo.generate', ['file' => $cert->file_no]) }}" class="iw-btn iw-btn-primary iw-btn-sm">
                                                    <i data-lucide="file-plus" class="h-4 w-4"></i> {{ __('Generate front page') }}
                                                </a>
                                            @else
                                                <button type="button" class="iw-btn iw-btn-light iw-btn-sm" disabled
                                                        title="{{ $cert->stages_done['registration'] ? __('You do not have permission to generate front pages.') : __('The file must be registered with Deeds as an SLTR CofO before its front page is generated.') }}">
                                                    <i data-lucide="file-plus" class="h-4 w-4"></i> {{ __('Generate front page') }}
                                                </button>
                                            @endif
                                        </td>
                                    @else
                                        <td style="white-space:nowrap">
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

                                            {{-- Upload is the fallback for a plan not yet in the GIS store,
                                                 and needs a front page to attach to. --}}
                                            @if ($canEdit && $cert->stages_done['front_page'] && !$cert->has_tdp)
                                                <form method="POST" action="{{ route('sltr-cofo.tdp', $cert->capture_id) }}" enctype="multipart/form-data" class="sltr-upload" style="margin-top:4px">
                                                    @csrf
                                                    <input type="file" name="tdp_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                                    <button type="submit" class="iw-btn iw-btn-light iw-btn-sm">
                                                        <i data-lucide="upload" class="h-4 w-4"></i> {{ __('Attach') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($cert->has_tdp && $cert->stages_done['front_page'])
                                                <a href="{{ route('sltr-cofo.print', $cert->capture_id) }}"
                                                   target="_blank" class="iw-btn iw-btn-primary iw-btn-sm">
                                                    <i data-lucide="printer" class="h-4 w-4"></i> {{ __('Print complete') }}
                                                </a>
                                            @else
                                                <button type="button" class="iw-btn iw-btn-light iw-btn-sm" disabled
                                                        title="{{ $cert->stages_done['front_page'] ? __('No back page yet') : __('No front page yet') }}">
                                                    <i data-lucide="printer" class="h-4 w-4"></i> {{ __('Print complete') }}
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:40px 18px">
                                        <div class="iw-empty">
                                            <i data-lucide="inbox" class="h-6 w-6 mx-auto mb-2 text-gray-400"></i>
                                            @if ($search !== '' || $status !== '')
                                                {{ __('No files match these filters.') }}
                                            @else
                                                {{ __('No SLTR files have reached the CofO stage yet.') }}
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
                            {{ __('The first two happen before a file reaches this screen. The front page prints from the Deeds instrument capture; the TDP may arrive before or after it.') }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    @include($footerPartial ?? 'admin.footer')
</div>
@endsection
