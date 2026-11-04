{{-- Update Project: the records captured under the project as a tree, Farmer > Farm > Land > OP (user, 2026-11-03).
     A farm is one line of the farmers list; its lands are the plot numbers it produced, and each land shows its OP.
     $records from ProjectController::records(). Search and paging are plain GET links, apart from the Update form. --}}
@php
    $t = $records['totals'];
    $fmtHa = fn ($v) => $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $farmers = $records['farmers'];
@endphp
<style>
    .survey-proto .pr-tree td, .survey-proto .pr-tree th { vertical-align: middle; }
    .survey-proto .pr-toggle { padding: 2px 8px; }
    .survey-proto .pr-farms { background: var(--gray-50, #f9fafb); padding: 8px 10px 12px; }
    .survey-proto .pr-farms > table, .survey-proto .pr-lands > table { width: 100%; font-size: 13px; border-collapse: collapse; }
    .survey-proto .pr-farms th, .survey-proto .pr-lands th { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--gray-600, #4b5563); padding: 6px 8px; text-align: left; white-space: nowrap; }
    .survey-proto .pr-farms td, .survey-proto .pr-lands td { padding: 6px 8px; border-top: 1px solid var(--gray-200, #e5e7eb); }
    .survey-proto .pr-lands { background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; padding: 4px 6px 6px; margin: 2px 0 6px 28px; }
    .survey-proto .pr-op { display: inline-block; padding: 1px 8px; border-radius: 999px; background: #ecfdf5; color: #047857; font-weight: 600; font-size: 12px; margin: 1px 4px 1px 0; }
    .survey-proto .pr-none { color: var(--gray-400, #9ca3af); font-size: 12px; }
    .survey-proto .pr-level { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--gray-500, #6b7280); }
</style>
<div class="form-container" id="records" style="margin-top:22px;">
    <div class="form-body">
        <div class="sv-group" style="margin-bottom:12px;"><i class="fas fa-sitemap"></i><span>Records under this project</span></div>
        <p class="helper-text" style="margin:-4px 0 12px;">
            CO File {{ $project->coFile?->co_file_no ?: '(none yet)' }} &rsaquo; {{ $project->project_code }} &rsaquo; Farmers &rsaquo; Farms &rsaquo; Lands &rsaquo; OPs.
            Open a farmer to see their farms, then a farm to see the lands (plots) it produced and their OPs.
        </p>

        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-label">Farmers</div><div class="kpi-value">{{ number_format((int) $t->farmers) }}</div></div>
            <div class="kpi-card"><div class="kpi-label">Farms</div><div class="kpi-value">{{ number_format((int) $t->farms) }}</div></div>
            <div class="kpi-card"><div class="kpi-label">Lands (plots)</div><div class="kpi-value">{{ number_format((int) $t->lands) }}</div></div>
            <div class="kpi-card"><div class="kpi-label">OPs</div><div class="kpi-value">{{ number_format((int) $t->ops) }}</div></div>
        </div>

        <form method="GET" action="{{ url()->current() }}#records" class="table-toolbar">
            <div class="left" style="flex-wrap:wrap;">
                <input type="text" name="rq" value="{{ $records['term'] }}" placeholder="Farmer name, F/No, plot no. or OP no." style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);width:320px;" />
                <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
                @if ($records['term'] !== '')<a class="btn btn-outline btn-sm" href="{{ url()->current() }}#records">Clear</a>@endif
            </div>
            <div class="right"><span class="pr-level">Area: <b>{{ $fmtHa($t->area) }} ha</b></span></div>
        </form>

        <div class="table-wrapper">
            <div class="table-scroll">
                <table class="pr-tree" data-column-picker="off">
                    <thead>
                        <tr>
                            <th style="width:44px;"></th>
                            <th>S/N</th>
                            <th>Farmer</th>
                            <th class="num">Farms</th>
                            <th class="num">Area (ha)</th>
                            <th class="num">Lands</th>
                            <th class="num">OPs</th>
                        </tr>
                    </thead>
                    @forelse ($farmers as $g)
                        @php
                            $lines = $records['farms']->get($g->k, collect());
                            $gLands = $lines->sum(fn ($l) => $l->lands->count());
                            $gOps = $lines->sum('op_count');
                        @endphp
                        <tbody x-data="{ open: false }">
                            <tr style="cursor:pointer;" @click="open = !open">
                                <td><button type="button" class="btn btn-outline btn-sm pr-toggle" :aria-expanded="open" aria-label="Show farms"><i class="fas" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i></button></td>
                                <td>{{ $farmers->firstItem() + $loop->index }}</td>
                                <td><strong>{{ trim((string) $g->name) !== '' ? $g->name : '(no name)' }}</strong></td>
                                <td class="num">{{ number_format((int) $g->farms) }}</td>
                                <td class="num">{{ $fmtHa($g->area) }}</td>
                                <td class="num">{{ number_format($gLands) }}</td>
                                <td class="num">{{ number_format($gOps) }}</td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td></td>
                                <td colspan="6" class="pr-farms">
                                    <div class="pr-level" style="margin-bottom:4px;"><i class="fas fa-seedling"></i> Farms</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th style="width:34px;"></th>
                                                <th>S/N</th>
                                                <th>F/No</th>
                                                <th title="The farm assessment (portion) the farm was listed on">Assessment</th>
                                                <th class="num">Area (ha)</th>
                                                <th class="num">Creatable</th>
                                                <th class="num">Farmer Share</th>
                                                <th class="num">Lands</th>
                                                <th class="num">OPs</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        @foreach ($lines as $l)
                                            <tbody x-data="{ lands: false }">
                                                <tr style="cursor:pointer;" @click.stop="lands = !lands">
                                                    <td><button type="button" class="btn btn-outline btn-sm pr-toggle" :aria-expanded="lands" aria-label="Show lands"><i class="fas" :class="lands ? 'fa-chevron-down' : 'fa-chevron-right'"></i></button></td>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $l->f_no ?: '—' }}</td>
                                                    <td style="white-space:nowrap;">{{ $l->case_ref ?: '—' }}{{ $l->portion_no ? ' · Portion ' . $l->portion_no : '' }}</td>
                                                    <td class="num">{{ $fmtHa($l->acreage) }}</td>
                                                    <td class="num">{{ $l->creetable !== null && $l->creetable !== '' ? $l->creetable : '—' }}</td>
                                                    <td class="num">{{ $fmtHa($l->farmer_50) }}</td>
                                                    <td class="num">{{ number_format($l->lands->count()) }}</td>
                                                    <td class="num">{{ number_format($l->op_count) }}</td>
                                                    <td>{{ $l->remarks ?: '' }}</td>
                                                </tr>
                                                <tr x-show="lands" x-cloak>
                                                    <td colspan="10" style="border-top:0;padding-top:0;">
                                                        <div class="pr-lands">
                                                            <div class="pr-level" style="margin:4px 2px;"><i class="fas fa-th-large"></i> Lands from F/No {{ $l->f_no ?: '—' }}</div>
                                                            @if ($l->lands->isEmpty())
                                                                <div class="pr-none" style="padding:6px 4px;">
                                                                    No plot numbers on this farm yet.
                                                                    @if ($l->farm_ops->isNotEmpty())
                                                                        OP: @foreach ($l->farm_ops as $op)<span class="pr-op">{{ $op->op_number }}</span>@endforeach
                                                                    @endif
                                                                </div>
                                                            @else
                                                                <table>
                                                                    <thead><tr><th>S/N</th><th>Plot No.</th><th>OP No.</th><th>OP Status</th><th>Verification</th></tr></thead>
                                                                    <tbody>
                                                                        @foreach ($l->lands as $land)
                                                                            <tr>
                                                                                <td>{{ $loop->iteration }}</td>
                                                                                <td><strong>{{ $land->plot_no }}</strong></td>
                                                                                <td>
                                                                                    @forelse ($land->ops as $op)
                                                                                        <span class="pr-op" title="{{ $op->source }}">{{ $op->op_number }}</span>
                                                                                    @empty
                                                                                        <span class="pr-none">No OP yet</span>
                                                                                    @endforelse
                                                                                </td>
                                                                                <td>{{ $land->ops->pluck('status')->filter()->unique()->implode(', ') ?: '—' }}</td>
                                                                                <td>{{ $land->ops->pluck('verification')->filter()->unique()->implode(', ') ?: '—' }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="7" style="text-align:center;color:var(--gray-500);padding:18px;">
                                {{ $records['term'] !== '' ? 'No farmer matches this search.' : 'No records captured under this project yet.' }}
                            </td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>
        @if ($farmers->hasPages())<div style="margin-top:12px;">{{ $farmers->links() }}</div>@endif
    </div>
</div>
