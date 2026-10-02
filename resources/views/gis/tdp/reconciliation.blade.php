@extends('layouts.app')
@section('page-title')
    {{ __('TDP Reconciliation') }}
@endsection

{{--
    GIS → Title Deed Plan Management → Reconciliation.

    The LGA folder names on the GIS server against the KLAES LGA list (the
    `lgas` table). Nothing is corrected here: the report names the folders
    nobody can account for and the LGAs with nowhere to file a plan, and the
    GIS team decides which side is wrong.
--}}

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', [
        'PageTitle' => 'TDP Reconciliation',
        'PageDescription' => 'GIS · Title Deed Plan folders against the KLAES LGA list',
    ])
    @include('instrument_workflow.partials.styles')

    <div class="iw-page space-y-5" style="max-width:1200px">
        @include('instrument_workflow.partials.flash')

        <div class="iw-crumbs" style="margin-bottom:0">
            <a href="{{ route('tdp.index') }}">Title Deed Plan Management</a><span>/</span><span class="text-gray-900">Reconciliation</span>
        </div>

        @unless($status['reachable'])
            <div class="tdp-store tdp-store-bad">
                <div class="tdp-store-icon"><i data-lucide="plug-zap" class="h-5 w-5"></i></div>
                <div>
                    <div class="tdp-store-title">Nothing to reconcile yet</div>
                    <p class="tdp-store-text">{{ $status['message'] }}</p>
                    <a href="{{ route('tdp.index') }}" class="iw-btn iw-btn-light iw-btn-sm" style="margin-top:10px">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to the library
                    </a>
                </div>
            </div>
        @else
            <div class="tdp-tiles">
                <div class="tdp-tile">
                    <div class="tdp-tile-icon green"><i data-lucide="check-circle" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ count($report['matched']) }}</div>
                        <div class="tdp-tile-label">Folders matched to an LGA</div>
                    </div>
                </div>
                <div class="tdp-tile">
                    <div class="tdp-tile-icon red"><i data-lucide="folder-x" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ count($report['folders_without_lga']) }}</div>
                        <div class="tdp-tile-label">Folders with no KLAES LGA</div>
                    </div>
                </div>
                <div class="tdp-tile">
                    <div class="tdp-tile-icon purple"><i data-lucide="map-pin-off" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ count($report['lgas_without_folder']) }}</div>
                        <div class="tdp-tile-label">LGAs with no folder</div>
                    </div>
                </div>
                <div class="tdp-tile">
                    <div class="tdp-tile-icon blue"><i data-lucide="files" class="h-5 w-5"></i></div>
                    <div>
                        <div class="tdp-tile-value">{{ number_format($report['plan_count']) }}</div>
                        <div class="tdp-tile-label">Plans across {{ $report['folder_count'] }} folders</div>
                    </div>
                </div>
            </div>

            <div class="iw-alert info">
                <i data-lucide="info" class="h-4 w-4"></i>
                <span>
                    Comparing <code class="iw-mono">{{ $status['root'] }}</code> with the
                    {{ $report['lga_count'] }} LGAs in the {{ $report['lga_source'] }}. Names are compared without
                    case or punctuation, so <code class="iw-mono">aba-north</code> matches <code class="iw-mono">Aba North</code>.
                    This report changes nothing on disk.
                </span>
            </div>

            <div class="iw-card">
                <div class="iw-card-head" style="padding:16px 18px">
                    <div>
                        <div class="iw-card-title" style="font-size:15px">
                            <span class="iw-icon-tile gray"><i data-lucide="folder-x" class="h-4 w-4"></i></span>
                            Folders with no matching KLAES LGA
                        </div>
                        <div class="iw-card-sub">Plans filed here will not be found by LGA — rename the folder, or add the LGA.</div>
                    </div>
                </div>
                <div style="overflow-x:auto">
                    <table class="tdp-table">
                        <thead><tr><th>Folder</th><th style="text-align:right">Plans</th><th>Closest KLAES LGA</th><th></th></tr></thead>
                        <tbody>
                        @forelse($report['folders_without_lga'] as $row)
                            <tr>
                                <td><span class="iw-mono">{{ $row['folder'] }}</span></td>
                                <td style="text-align:right" class="tdp-muted">{{ number_format($row['file_count']) }}</td>
                                <td>{{ $row['closest'] ? $row['closest'] . ' ?' : '—' }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('tdp.index', ['lga' => $row['folder']]) }}" class="iw-btn iw-btn-light iw-btn-sm">
                                        <i data-lucide="folder-open" class="h-4 w-4"></i> Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="iw-empty" style="margin:16px">Every folder in the store matches an KLAES LGA.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="iw-card">
                <div class="iw-card-head" style="padding:16px 18px">
                    <div>
                        <div class="iw-card-title" style="font-size:15px">
                            <span class="iw-icon-tile gray"><i data-lucide="map-pin-off" class="h-4 w-4"></i></span>
                            KLAES LGAs with no folder
                        </div>
                        <div class="iw-card-sub">No plans have been filed for these yet; the folder is created on the first upload.</div>
                    </div>
                </div>
                <div class="iw-card-body">
                    @if(count($report['lgas_without_folder']))
                        <div class="tdp-chips">
                            @foreach($report['lgas_without_folder'] as $name)
                                <span class="tdp-chip">{{ $name }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="iw-empty">Every KLAES LGA has a folder in the TDP store.</div>
                    @endif
                </div>
            </div>

            <div class="iw-card">
                <div class="iw-card-head" style="padding:16px 18px">
                    <div>
                        <div class="iw-card-title" style="font-size:15px">
                            <span class="iw-icon-tile green"><i data-lucide="check-circle" class="h-4 w-4"></i></span>
                            Matched folders
                        </div>
                        <div class="iw-card-sub">A folder spelled differently from the LGA still matches; the difference is shown.</div>
                    </div>
                </div>
                <div style="overflow-x:auto">
                    <table class="tdp-table">
                        <thead><tr><th>Folder</th><th>KLAES LGA</th><th style="text-align:right">Plans</th><th>Spelling</th></tr></thead>
                        <tbody>
                        @forelse($report['matched'] as $row)
                            <tr>
                                <td><span class="iw-mono">{{ $row['folder'] }}</span></td>
                                <td>{{ $row['lga'] }}</td>
                                <td style="text-align:right" class="tdp-muted">{{ number_format($row['file_count']) }}</td>
                                <td>
                                    @if($row['exact'])
                                        <span class="tdp-pill green">exact</span>
                                    @else
                                        <span class="tdp-pill yellow">differs</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="iw-empty" style="margin:16px">No folder in the store matches an KLAES LGA yet.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endunless
    </div>
</div>

<style>
    .tdp-store { display: flex; gap: 14px; align-items: flex-start; border-radius: 16px; padding: 16px 18px; border: 1px solid #fecaca; background: #fff7f7; }
    .tdp-store-icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex: none; background: #fee2e2; color: #b91c1c; }
    .tdp-store-title { font-size: 15px; font-weight: 700; color: #111827; }
    .tdp-store-text { font-size: 13px; color: #4b5563; margin-top: 3px; }

    .tdp-tiles { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .tdp-tile { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px 16px; }
    .tdp-tile-icon { width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: none; }
    .tdp-tile-icon.blue { background: #eff6ff; color: #2563eb; }
    .tdp-tile-icon.green { background: #f0fdf4; color: #16a34a; }
    .tdp-tile-icon.purple { background: #f5f3ff; color: #7c3aed; }
    .tdp-tile-icon.red { background: #fee2e2; color: #b91c1c; }
    .tdp-tile-value { font-size: 20px; font-weight: 700; color: #111827; line-height: 1.1; }
    .tdp-tile-label { font-size: 12px; color: #6b7280; margin-top: 2px; }

    .tdp-table { width: 100%; font-size: 13.5px; border-collapse: collapse; }
    .tdp-table thead tr { background: #f9fafb; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
    .tdp-table th { padding: 11px 16px; font-weight: 600; white-space: nowrap; }
    .tdp-table td { padding: 11px 16px; border-top: 1px solid #f3f4f6; vertical-align: middle; }
    .tdp-muted { color: #6b7280; font-size: 12.5px; }
    .tdp-pill { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #f3f4f6; color: #4b5563; }
    .tdp-pill.green { background: #dcfce7; color: #166534; }
    .tdp-pill.yellow { background: #fef9c3; color: #854d0e; }
    .tdp-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .tdp-chip { font-size: 12.5px; font-weight: 600; color: #4b5563; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 99px; padding: 5px 12px; }
    .iw-card code { background: #f3f4f6; border-radius: 5px; padding: 1px 5px; font-size: 12px; color: #374151; }
</style>
@endsection
