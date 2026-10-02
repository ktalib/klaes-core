@extends('layouts.app')

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }
    .holding-no { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .rank-1 { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
    .rank-2 { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
    .rank-3 { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
    .rank-4 { background:#fef3c7; color:#b45309; border-color:#fde68a; }
    .rank-5 { background:#e2e8f0; color:#334155; border-color:#cbd5e1; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Duplex Commissioning',
        'PageDescription' => 'Confirm the whole duplex — every file number is generated in one pass.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center justify-between gap-4">
                <div>
                    <a href="{{ route('duplex-parcel-update.index') }}"
                       class="text-xs font-bold text-slate-400 hover:text-slate-600 inline-flex items-center gap-1">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Duplex Register
                    </a>
                    <h1 class="text-2xl font-black text-slate-800 mt-1 holding-no">{{ $duplex->duplex_id }}</h1>
                    <p class="text-sm text-slate-500">{{ $duplex->applicant_name }} · {{ $duplex->file_title }}</p>
                </div>
                <span class="px-3 py-1 rounded-lg border text-[11px] font-bold uppercase bg-slate-50 text-slate-600 border-slate-200">
                    {{ str_replace('_', ' ', $duplex->status) }}
                </span>
            </div>

            @if ($duplex->status !== \App\Models\DuplexParcelUpdate::STATUS_IN_LAND)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                @if ($duplex->status === \App\Models\DuplexParcelUpdate::STATUS_COMMITTED)
                    This duplex has already been commissioned. The file numbers below are live.
                @else
                    This duplex is not with Land yet. It must be approved, have its memo and conveyance
                    generated, and be sent to Land before it can be commissioned.
                @endif
            </div>
            @endif

            {{-- The chain: holding numbers on the left, what they become on the right. --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">
                        Stages in execution order
                    </h2>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($duplex->stageRows as $stage)
                    <div class="px-6 py-5">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="rank-{{ min($stage->rank, 5) }} text-center px-2 py-0.5 rounded-lg border text-xs font-black">
                                {{ $stage->rank }}
                            </span>
                            <p class="text-sm font-black text-slate-700">{{ $stage->label() }}</p>
                            @if ($stage->type === 'change_of_purpose' && $stage->newLandUseLabel())
                            <span class="text-[11px] font-bold text-slate-400">
                                → {{ $stage->newLandUseLabel() }}
                            </span>
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            @forelse ($stage->files as $file)
                            <div class="flex items-center gap-3 text-sm">
                                <span class="holding-no text-slate-500 w-56">{{ $file->holding_no ?: $file->source_file_no }}</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-300"></i>
                                @if ($file->final_file_no)
                                <span class="holding-no font-bold text-emerald-700">{{ $file->final_file_no }}</span>
                                @else
                                <span class="px-3 py-1 rounded-lg border border-dashed border-slate-300 text-xs text-slate-400">
                                    file number assigned on commit
                                </span>
                                @endif
                                @if ($file->holder_name)
                                <span class="text-xs text-slate-400">{{ $file->holder_name }}</span>
                                @endif
                            </div>
                            @empty
                            <p class="text-xs text-slate-400">No holding numbers recorded for this stage.</p>
                            @endforelse
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Everything that gets retired, stated up front and in execution order. --}}
            <div class="bg-white rounded-3xl border border-red-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-red-100 bg-red-50/50">
                    <h2 class="text-sm font-black uppercase tracking-wider text-red-600">Will be decommissioned</h2>
                </div>
                <div class="px-6 py-5 text-sm text-slate-600 space-y-2">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Source files</p>
                        <p class="holding-no font-bold text-slate-700">
                            {{ implode(', ', (array) ($duplex->source_file_nos ?? [])) ?: '—' }}
                        </p>
                    </div>
                    @if ($duplex->stageRows->count() > 1)
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Intermediate files</p>
                        <p class="text-xs text-slate-500">
                            Each stage retires the file it consumes, so the files produced by stages 1 to
                            {{ $duplex->stageRows->count() - 1 }} are retired by the stage that follows them.
                            Only the files from the last stage stay live.
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Commit --}}
            @if ($duplex->status === \App\Models\DuplexParcelUpdate::STATUS_IN_LAND)
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
                <div class="grid md:grid-cols-4 gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Commissioned by</label>
                        <input type="text" id="cm-by" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm"
                               value="{{ auth()->user()->name ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Date</label>
                        <input type="date" id="cm-date" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm"
                               value="{{ now()->toDateString() }}">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Customer type</label>
                        <select id="cm-customer" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                            {{-- Individual is the default across the commissioning interfaces. --}}
                            <option value="Individual" selected>Individual</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Multiple">Multiple</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Gender</label>
                        <select id="cm-gender" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Joint">Joint</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button onclick="rejectFromLand()"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        Reject
                    </button>
                    <button onclick="commitDuplex()" id="cm-btn"
                        class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-black hover:bg-emerald-700">
                        Perfect — commission all
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 text-right mt-2">
                    One confirmation generates every file number for this duplex in a single pass.
                </p>
            </div>
            @endif

            <div class="flex justify-end">
                <button type="button" onclick="showDuplexSummary('{{ url('duplex-parcel-update') }}', {{ $duplex->id }})"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    <i data-lucide="receipt-text" class="w-3.5 h-3.5"></i>
                    Summary sheet
                </button>
            </div>

            <div id="cm-summary" class="hidden bg-white rounded-3xl border border-emerald-100 shadow-sm p-6"></div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
<script src="{{ asset('js/shared/record-summary-card.js') }}?v={{ @filemtime(public_path('js/shared/record-summary-card.js')) }}"></script>
<script src="{{ asset('js/duplex-summary-card.js') }}?v={{ @filemtime(public_path('js/duplex-summary-card.js')) }}"></script>
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const ID = {{ $duplex->id }};

    window.commitDuplex = async function () {
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'warning',
                title: 'Commission this duplex?',
                html: 'Every file number will be generated and the source files retired. '
                    + 'This cannot be undone from here.',
                showCancelButton: true,
                confirmButtonText: 'Yes, commission',
                confirmButtonColor: '#059669',
            });
            if (!r.isConfirmed) return;
        }

        const btn = document.getElementById('cm-btn');
        btn.disabled = true;
        btn.textContent = 'Commissioning…';

        const res = await fetch('{{ route('duplex-parcel-update.commit', $duplex->id) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                commissioned_by: document.getElementById('cm-by').value,
                commission_date: document.getElementById('cm-date').value,
                customer_type: document.getElementById('cm-customer').value,
                gender: document.getElementById('cm-gender').value,
            }),
        }).then(r => r.json());

        btn.disabled = false;
        btn.textContent = 'Perfect — commission all';

        if (!res.success) {
            return Swal ? Swal.fire({ icon: 'error', title: 'Commissioning failed', text: res.message })
                        : alert(res.message);
        }

        const box = document.getElementById('cm-summary');
        box.classList.remove('hidden');
        box.innerHTML = '<h3 class="text-sm font-black uppercase tracking-wider text-emerald-700 mb-4">Commissioned</h3>'
            + (res.summary || []).map(s => `
                <div class="mb-4">
                    <p class="text-sm font-bold text-slate-700">(${s.rank}) ${s.label}</p>
                    <p class="text-xs text-slate-500 mt-1">New: <span class="holding-no font-bold text-emerald-700">${(s.files || []).join(', ')}</span></p>
                    <p class="text-xs text-slate-400">Retired: <span class="holding-no">${(s.decommissioned || []).join(', ')}</span></p>
                </div>`).join('');

        // The card is the point of the commissioning, not a footnote to it: it is the
        // only place the officer sees the whole chain - what was issued, what was
        // retired - in one view. Reload only once it has been dismissed.
        if (typeof window.showDuplexSummary === 'function') {
            await window.showDuplexSummary('{{ url('duplex-parcel-update') }}', ID);
        } else if (window.Swal) {
            await Swal.fire({ icon: 'success', title: 'Duplex commissioned', text: res.message });
        }

        window.location.reload();
    };

    window.rejectFromLand = async function () {
        let reason = '';
        if (window.Swal) {
            const r = await Swal.fire({
                title: 'Reject duplex', input: 'text', inputLabel: 'Reason',
                showCancelButton: true, confirmButtonText: 'Reject',
            });
            if (!r.isConfirmed) return;
            reason = r.value || '';
        }

        const res = await fetch('{{ route('duplex-parcel-update.reject', $duplex->id) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ reason }),
        }).then(r => r.json());

        if (res.success) window.location.href = '{{ route('duplex-parcel-update.index') }}';
    };

    document.addEventListener('DOMContentLoaded', () => { if (window.lucide) window.lucide.createIcons(); });
})();
</script>
@endsection
