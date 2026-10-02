{{-- Statistics Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
            <i data-lucide="calendar-check" class="h-32 w-32 text-blue-600"></i>
        </div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                <i data-lucide="calendar-check" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Daily Generation</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['today']) }}</h3>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
            <span>New Files Today</span>
            <span class="text-emerald-500 flex items-center gap-1">
                Live Update
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
            </span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
            <i data-lucide="bar-chart-3" class="h-32 w-32 text-indigo-600"></i>
        </div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 shadow-sm">
                <i data-lucide="bar-chart-3" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Monthly Generation</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['month']) }}</h3>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
            <span>Total this month</span>
            <span class="text-indigo-500">{{ date('F Y') }}</span>
        </div>
    </div>

    <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-blue-600 to-blue-800 border-none">
        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i data-lucide="database" class="h-32 w-32 text-white"></i>
        </div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                <i data-lucide="database" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest">Total</p>
                <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($stats['total']) }}</h3>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-blue-100 uppercase tracking-widest">
            <span>All GKN Records</span>
            <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">GKN</span>
        </div>
    </div>
</div>

{{-- Commissioned GKN Records Table --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h3 class="font-bold text-slate-800">Commissioned GKN Records</h3>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-100 text-[10px] font-black rounded-lg uppercase tracking-widest">{{ $records->total() }} total</span>
        </div>
        <span class="flex items-center gap-1.5 px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg text-xs font-black uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>COMMISSIONED
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                    <th class="px-6 py-4 whitespace-nowrap">Title / Name</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">TP No</th>
                    <th class="px-6 py-4 whitespace-nowrap">Land Use</th>
                    <th class="px-6 py-4 whitespace-nowrap">Purpose</th>
                    <th class="px-6 py-4 whitespace-nowrap">Location</th>
                    <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                    <th class="px-6 py-4 whitespace-nowrap">Commissioned By</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Time</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Date</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse($records as $record)
                <tr class="hover:bg-emerald-50/40 transition-colors" style="background-color: #f0fdf4;">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="font-mono font-bold text-blue-600 text-xs">{{ $record->full_file_number }}</span>
                            @if($record->batch_count > 1)
                                <button onclick="window.__gknOpenBatch('{{ $record->batch_no }}')" class="mt-1 flex items-center gap-1.5 text-[9px] font-black text-blue-500 hover:text-blue-700 underline underline-offset-4 decoration-blue-200 uppercase tracking-widest whitespace-nowrap">
                                    <i data-lucide="layers" class="h-2.5 w-2.5"></i>
                                    Batch Group ({{ $record->batch_count }})
                                </button>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 font-medium text-slate-700 whitespace-nowrap">{{ Str::limit($record->file_name, 30) }}</td>
                    <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $record->plot_no ?? '—' }}</td>
                    <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $record->tp_no ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->landUse->landuse ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->purpose->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">{{ Str::limit($record->location, 40) }}</td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->lga ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-lg bg-blue-50 flex items-center justify-center text-[10px] font-black text-blue-600 border border-blue-100">
                                {{ strtoupper(substr($record->user->name ?? 'S', 0, 1)) }}
                            </div>
                            <span class="font-bold text-slate-600">{{ $record->user->name ?? 'System' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center font-black text-slate-500 text-[11px] whitespace-nowrap">
                        <span class="px-2 py-1 bg-slate-100 rounded-md border border-slate-200">{{ $record->formatted_time }}</span>
                    </td>
                    <td class="px-6 py-4 text-center font-black text-blue-600 text-[11px] whitespace-nowrap">
                        <span class="px-2 py-1 bg-blue-50 rounded-md border border-blue-100">{{ $record->formatted_date }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-1">
                            <button onclick="window.__gknEdit({{ $record->id }})" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="Edit">
                                <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                            </button>
                            <button onclick="window.__gknView({{ $record->id }})" class="p-1.5 bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-600 hover:text-white transition-all shadow-sm" title="View">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="px-6 py-10 text-center text-slate-400 italic text-sm">No commissioned records found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">
        {{ $records->links() }}
    </div>
    @endif
</div>

{{-- File Indexing Records Table --}}
@if($indexedFiles->total() > 0)
<div class="bg-white rounded-2xl shadow-sm border border-amber-100 overflow-hidden">
    <div class="bg-amber-50/60 px-6 py-4 border-b border-amber-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h3 class="font-bold text-slate-800">File Indexing Records</h3>
            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-100 text-[10px] font-black rounded-lg uppercase tracking-widest">{{ $indexedFiles->total() }} total</span>
        </div>
        <span class="flex items-center gap-1.5 px-2 py-1 bg-amber-50 text-amber-700 border border-amber-100 rounded-lg text-xs font-black uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>FILE INDEXING
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-amber-50/30 border-b border-amber-100 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                    <th class="px-6 py-4 whitespace-nowrap">Title / Name</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">TP No</th>
                    <th class="px-6 py-4 whitespace-nowrap">Land Use</th>
                    <th class="px-6 py-4 whitespace-nowrap">Location</th>
                    <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                    <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Time</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-amber-50 text-sm text-slate-600">
                @foreach($indexedFiles as $fi)
                <tr class="hover:bg-amber-50 transition-colors" style="background-color: #fffbeb;">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="font-mono font-bold text-blue-600 text-xs">{{ $fi->file_number }}</span>
                    </td>
                    <td class="px-6 py-4 font-medium text-slate-700 whitespace-nowrap">{{ Str::limit($fi->file_title, 30) }}</td>
                    <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $fi->plot_number ?? '—' }}</td>
                    <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $fi->tp_no ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $fi->land_use_type ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">{{ Str::limit($fi->location, 40) }}</td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $fi->lga ?? '—' }}</td>
                    <td class="px-6 py-4 text-xs whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-lg bg-amber-50 flex items-center justify-center text-[10px] font-black text-amber-600 border border-amber-100">
                                {{ strtoupper(substr($fi->created_by ?? 'S', 0, 1)) }}
                            </div>
                            <span class="font-bold text-slate-600">{{ $fi->created_by ?? 'System' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center font-black text-amber-600 text-[11px] whitespace-nowrap">
                        <span class="px-2 py-1 bg-amber-50 rounded-md border border-amber-100">{{ $fi->created_at ? $fi->created_at->format('h:i A') : '—' }}</span>
                    </td>
                    <td class="px-6 py-4 text-center font-black text-amber-700 text-[11px] whitespace-nowrap">
                        <span class="px-2 py-1 bg-amber-50 rounded-md border border-amber-100">{{ $fi->created_at ? $fi->created_at->format('jS M, Y') : '—' }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($indexedFiles->hasPages())
    <div class="px-6 py-4 border-t border-amber-100">
        {{ $indexedFiles->links() }}
    </div>
    @endif
</div>
@endif
