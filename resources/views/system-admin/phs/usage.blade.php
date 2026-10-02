@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'PHS usage and revenue overview.'])
    <div class="flex-1 p-6">
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Revenue</p><p class="mt-2 text-2xl font-extrabold">NGN {{ number_format($revenue, 2) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Tokens sold</p><p class="mt-2 text-2xl font-extrabold">{{ number_format($tokensSold) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Searches run</p><p class="mt-2 text-2xl font-extrabold">{{ number_format($searchesRun) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Topups</p><p class="mt-2 text-2xl font-extrabold">{{ number_format($topupCount) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Topup revenue</p><p class="mt-2 text-2xl font-extrabold">NGN {{ number_format($topupRevenue, 2) }}</p></div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Last 6 months</h3>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#2563eb"></span>Searches</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#10b981"></span>Revenue (₦)</span>
                    </div>
                </div>
                <canvas id="trendChart" height="110"></canvas>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Top organizations</h3>
                @forelse ($topOrgs as $org)
                    @php $max = max(1, (int) $topOrgs->max('searches')); $pct = round(((int) $org->searches / $max) * 100); @endphp
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700 truncate">{{ \Illuminate\Support\Str::title($org->name) ?: 'Unknown' }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($org->searches) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width: {{ $pct }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No search activity yet.</p>
                @endforelse
            </div>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Organization</th><th class="px-4 py-3">Query</th><th class="px-4 py-3">FileNo</th><th class="px-4 py-3">Results</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentSearches as $row)
                        <tr>
                            <td class="px-4 py-3 font-bold">{{ \Illuminate\Support\Str::title($row->institution_name) }}</td>
                            <td class="px-4 py-3">{{ $row->query }}</td>
                            <td class="px-4 py-3">{{ $row->file_number }}</td>
                            <td class="px-4 py-3">{{ $row->result_count }}</td>
                            <td class="px-4 py-3">{{ $row->reference_no }}</td>
                            <td class="px-4 py-3">{{ $row->created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($row->file_number || $row->query)
                                    <a href="{{ route('system-admin.phs.searches.slip', ['id' => $row->id]) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <i data-lucide="file-search" class="h-3.5 w-3.5"></i> Open Search
                                    </a>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No searches recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('admin.footer')
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    (function () {
        const el = document.getElementById('trendChart');
        if (!el || !window.Chart) return;
        const trend = @json($trend);
        new Chart(el, {
            data: {
                labels: trend.map(t => t.label),
                datasets: [
                    { type: 'bar', label: 'Searches', data: trend.map(t => t.searches), backgroundColor: '#2563eb', borderRadius: 6, yAxisID: 'y' },
                    { type: 'line', label: 'Revenue (₦)', data: trend.map(t => t.revenue), borderColor: '#10b981', backgroundColor: '#10b981', tension: 0.3, yAxisID: 'y1' },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Searches' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '₦' } },
                },
            },
        });
    })();
</script>
@endsection
