@extends('layouts.app')

@section('page-title')
    {{ __('ST Files') }}
@endsection

{{-- File Status > ST Files (user, 2026-11-18): its own screen, view only, for Survey and Cadastral (?url=). --}}
@section('content')
<div class="flex-1 overflow-auto bg-slate-50 text-slate-900">
    @include('admin.header')

    <div class="px-6 py-5 space-y-4">
        {{-- Dashboard cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([['ST files', $stats->total, 'files', '#7e22ce'], ['ST mothers (Primary)', $stats->primaries, 'building-2', '#1d4ed8'], ['Units (PuA / SuA)', $stats->units, 'layout-grid', '#0f766e'], ['Used', $stats->used, 'check-circle', '#475569']] as [$label, $n, $icon, $c])
                <div class="bg-white border border-slate-200 rounded-xl px-4 py-3 flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:{{ $c }}14;color:{{ $c }};"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></span>
                    <div><div class="text-xs text-slate-500">{{ $label }}</div><div class="text-xl font-semibold">{{ number_format((int) $n) }}</div></div>
                </div>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2 bg-white border border-slate-200 rounded-xl px-4 py-3">
            <input type="hidden" name="url" value="{{ $ctx }}">
            <input type="text" name="q" value="{{ $term }}" placeholder="ST file no., land file no., applicant, plot, district…"
                   class="flex-1 min-w-[260px] px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">All statuses</option>
                <option value="ACTIVE" @selected($status === 'ACTIVE')>Active</option>
                <option value="USED" @selected($status === 'USED')>Used</option>
            </select>
            <button class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:#0f2a44;"><i class="fas fa-search"></i> Search</button>
            @if ($term !== '' || $status)
                <a href="{{ route('st-file-status.index', ['url' => $ctx, 'tab' => $tab]) }}" class="px-3 py-2 rounded-lg text-sm border border-slate-300 bg-white">Clear</a>
            @endif
        </form>

        {{-- Primary / SuA / PuA (user, 2026-11-18); each count follows the search. --}}
        <div class="flex flex-wrap gap-2" role="tablist">
            @foreach (\App\Http\Controllers\StFileStatusController::TABS as $k => $label)
                <a role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}"
                   href="{{ route('st-file-status.index', array_filter(['url' => $ctx, 'tab' => $k, 'q' => $term ?: null, 'status' => $status])) }}"
                   class="px-4 py-2 rounded-lg text-sm font-semibold border {{ $tab === $k ? 'text-white border-transparent' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}"
                   style="{{ $tab === $k ? 'background:#7e22ce;' : '' }}">
                    {{ $label }} <span class="ml-1 px-1.5 rounded-full text-xs {{ $tab === $k ? 'bg-white/25' : 'bg-slate-100' }}">{{ number_format((int) ($counts[$k] ?? 0)) }}</span>
                </a>
            @endforeach
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left">S/N</th>
                            {{-- Primary File No. before ST File No.; no Status, no action menu (user, 2026-11-18). --}}
                            <th class="px-3 py-3 text-left">Primary File No.</th>
                            <th class="px-3 py-3 text-left">ST File No.</th>
                            <th class="px-3 py-3 text-left">Type</th>
                            <th class="px-3 py-3 text-left">Applicant</th>
                            <th class="px-3 py-3 text-left">Land Use</th>
                            {{-- Property order: Plot No., District, LGA, Location. --}}
                            <th class="px-3 py-3 text-left">Plot No.</th>
                            <th class="px-3 py-3 text-left">District</th>
                            <th class="px-3 py-3 text-left">LGA</th>
                            <th class="px-3 py-3 text-left">Location</th>
                            <th class="px-3 py-3 text-left">Commissioned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr>
                                <td class="px-3 py-2 text-slate-500">{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $r->mother ?: '—' }}</td>
                                <td class="px-3 py-2 font-semibold whitespace-nowrap">{{ $r->st_no ?: '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $r->type_label }}</td>
                                <td class="px-3 py-2">{{ $r->applicant ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $r->land_use ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $r->plot ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $r->district ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $r->lga ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $r->location ?: '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $r->commissioned ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-3 py-8 text-center text-slate-500">{{ $term !== '' || $status ? 'No ' . \App\Http\Controllers\StFileStatusController::TABS[$tab] . ' ST file matches the search.' : 'No ' . \App\Http\Controllers\StFileStatusController::TABS[$tab] . ' ST files yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
                <span>{{ number_format($rows->total()) }} ST file(s)</span>
                <div>{{ $rows->links() }}</div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection
