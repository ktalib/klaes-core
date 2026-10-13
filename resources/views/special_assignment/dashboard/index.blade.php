@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Dashboard' }}@endsection

@section('content')
@php
    $ngn = fn ($v) => '₦' . number_format((float) $v, 2);
    $stageMeta = [
        'record'        => ['Record',        '#64748b'],
        'field'         => ['Field Data',    '#0891b2'],
        'contravention' => ['Contravention', '#dc2626'],
        'second'        => ['2nd Serve',     '#ea580c'],
        'response'      => ['Response',      '#2563eb'],
        'payment'       => ['Payment',       '#16a34a'],
        'newfile'       => ['New File No.',  '#0d9488'],
        'memo'          => ['Memo',          '#7c3aed'],
        'decision'      => ['Decision',      '#b8860b'],
        'certificate'   => ['Certificate',   '#db2777'],
        'complete'      => ['Complete',      '#059669'],
        'cleared'       => ['No contravention', '#94a3b8'],
    ];
    $collectedPct = $stats['billed'] > 0 ? min(100, round($stats['collected'] / $stats['billed'] * 100)) : 0;
    $maxLga = max(1, (int) collect($byLga)->max('total'));
@endphp
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">

        {{-- Banner --}}
        <div class="spas-hero relative overflow-hidden rounded-2xl mb-5 px-6 py-6 sm:px-8">
            <div class="relative z-10 max-w-xl">
                <div class="text-xs font-semibold uppercase tracking-widest" style="color:#eef08f">Special Assignment</div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">SPAS Dashboard</h1>
                <p class="text-sm mt-1.5" style="color:#e5e7eb">
                    {{ number_format($stats['records']) }} land records · {{ number_format($stats['contraventions']) }} in contravention ·
                    {{ $ngn($stats['collected']) }} collected. {{ now()->format('l, d F Y') }}
                </p>
                <div class="flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('special-assignment.field-data') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold" style="background:#babf0c;color:#1f2937">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> Add land record</a>
                    <a href="{{ route('special-assignment.notice') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold text-white" style="background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25)">Notices</a>
                    <a href="{{ route('programmes.bills') }}?url=spa" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold text-white" style="background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25)">Bills &amp; payments</a>
                </div>
            </div>
            {{-- Illustration: a parcel map, a pin and the issued sheet. --}}
            <svg class="spas-hero__art hidden md:block" viewBox="0 0 360 200" aria-hidden="true">
                <g opacity=".9">
                    <path d="M40 150 L120 110 L210 140 L130 185 Z" fill="#babf0c" opacity=".35"/>
                    <path d="M120 110 L200 70 L290 100 L210 140 Z" fill="#eef08f" opacity=".28"/>
                    <path d="M40 150 L120 110 L200 70 M120 110 L210 140 L290 100" stroke="#fff" stroke-opacity=".5" stroke-width="2" fill="none"/>
                    <path d="M130 185 L210 140 L290 100 L320 115 L240 160 L160 200" fill="#fff" opacity=".08"/>
                </g>
                <g transform="translate(150 40)">
                    <path d="M20 0C9 0 0 9 0 20c0 15 20 38 20 38s20-23 20-38C40 9 31 0 20 0z" fill="#dc2626"/>
                    <circle cx="20" cy="20" r="7" fill="#fff"/>
                </g>
                <g transform="translate(250 20)">
                    <rect width="78" height="98" rx="8" fill="#fff"/>
                    <rect x="12" y="14" width="40" height="6" rx="3" fill="#babf0c"/>
                    <rect x="12" y="28" width="54" height="4" rx="2" fill="#e5e7eb"/>
                    <rect x="12" y="38" width="48" height="4" rx="2" fill="#e5e7eb"/>
                    <rect x="12" y="48" width="52" height="4" rx="2" fill="#e5e7eb"/>
                    <circle cx="54" cy="76" r="13" fill="#db2777"/>
                    <path d="M48 76l4 4 8-8" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <g transform="translate(60 40)" opacity=".85">
                    <circle cx="16" cy="16" r="16" fill="#0891b2"/>
                    <path d="M16 8v8l5 3" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                </g>
            </svg>
        </div>

        {{-- The workflow, with how many records wait at / have done each step. --}}
        @include('special_assignment.partials.workflow', ['current' => []])

        {{-- Headline figures --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
            @foreach([
                ['Land records', number_format($stats['records']), $stats['not_inspected'].' not inspected yet', '#0891b2',
                    '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/>', route('special-assignment.land-records')],
                ['Contraventions', number_format($stats['contraventions']), $stats['cleared'].' with the approved use', '#dc2626',
                    '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>', route('special-assignment.field-data')],
                ['Notices served', number_format($stats['first_served']), $stats['second_issued'].' 2nd Serve · '.$stats['responses'].' responses', '#ea580c',
                    '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>', route('special-assignment.notice')],
                ['Collected', $ngn($stats['collected']), $ngn($stats['outstanding']).' outstanding', '#16a34a',
                    '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>', route('programmes.payments').'?url=spa'],
            ] as [$label, $value, $sub, $hue, $icon, $href])
            <a href="{{ $href }}" class="spas-kpi bg-white rounded-2xl border border-gray-200 shadow-sm p-4 flex items-start gap-3" style="--hue: {{ $hue }}">
                <span class="spas-kpi__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg></span>
                <span class="min-w-0">
                    <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</span>
                    <span class="block text-2xl font-bold text-gray-800 mt-0.5 truncate">{{ $value }}</span>
                    <span class="block text-xs text-gray-500 mt-0.5">{{ $sub }}</span>
                </span>
            </a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
            {{-- Money --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="text-sm font-bold text-gray-800">Revenue</div>
                <div class="text-xs text-gray-500">Contravention bills against payments received</div>
                <div class="mt-4 flex items-end justify-between">
                    <div><div class="text-xs text-gray-500">Billed</div><div class="text-lg font-bold text-gray-800">{{ $ngn($stats['billed']) }}</div></div>
                    <div class="text-right"><div class="text-xs text-gray-500">Collected</div><div class="text-lg font-bold" style="color:#16a34a">{{ $ngn($stats['collected']) }}</div></div>
                </div>
                <div class="mt-3 h-3 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-3 rounded-full" style="width:{{ $collectedPct }}%;background:linear-gradient(90deg,#4ade80,#16a34a)"></div>
                </div>
                <div class="text-xs text-gray-500 mt-1">{{ $collectedPct }}% collected</div>
                <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                    <div class="rounded-xl py-2" style="background:#fef2f2"><div class="text-lg font-bold" style="color:#dc2626">{{ $stats['bills_unpaid'] }}</div><div class="text-xs text-gray-600">Unpaid</div></div>
                    <div class="rounded-xl py-2" style="background:#fffbeb"><div class="text-lg font-bold" style="color:#d97706">{{ $stats['bills_partial'] }}</div><div class="text-xs text-gray-600">Part paid</div></div>
                    <div class="rounded-xl py-2" style="background:#f0fdf4"><div class="text-lg font-bold" style="color:#16a34a">{{ $stats['bills_paid'] }}</div><div class="text-xs text-gray-600">Paid</div></div>
                </div>
            </div>

            {{-- Notices & decisions --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="text-sm font-bold text-gray-800">Notices &amp; decisions</div>
                <div class="text-xs text-gray-500">Where the serves and memos stand</div>
                <div class="mt-4 space-y-2.5">
                    @foreach([
                        ['1st Serve SMS not sent', $stats['sms_not_sent'], '#dc2626'],
                        ['2nd Serve due now', $stats['second_due_now'], '#ea580c'],
                        ['2nd Serve issued', $stats['second_issued'], '#f59e0b'],
                        ['Responses received', $stats['responses'], '#2563eb'],
                        ['Memos awaiting Commissioner', $stats['memo_pending'], '#7c3aed'],
                        ['Memos approved / rejected', $stats['memo_approved'].' / '.$stats['memo_rejected'], '#b8860b'],
                        ['Contravention sheets issued', $stats['certificates'], '#db2777'],
                    ] as [$label, $n, $hue])
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 text-gray-600"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $hue }}"></span>{{ $label }}</span>
                        <b class="text-gray-800">{{ $n }}</b>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- By LGA --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="text-sm font-bold text-gray-800">Records by LGA</div>
                <div class="text-xs text-gray-500">Red part: in contravention</div>
                <div class="mt-4 space-y-3">
                    @forelse($byLga as $lga => $n)
                    <div>
                        <div class="flex justify-between text-xs"><span class="font-semibold text-gray-700">{{ $lga }}</span><span class="text-gray-500">{{ $n['total'] }} · {{ $n['contra'] }} contravening</span></div>
                        <div class="mt-1 h-2.5 rounded-full bg-gray-100 overflow-hidden flex" style="width:{{ max(6, round($n['total'] / $maxLga * 100)) }}%">
                            <div style="width:{{ $n['total'] ? round($n['contra'] / $n['total'] * 100) : 0 }}%;background:#ef4444"></div>
                            <div class="flex-1" style="background:#22d3ee"></div>
                        </div>
                    </div>
                    @empty
                    <div class="text-sm text-gray-400">No records yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Needs attention --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg" style="background:#fef3c7;color:#b45309">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div><div class="text-sm font-bold text-gray-800">Needs attention</div><div class="text-xs text-gray-500">The oldest items first</div></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                @php
                    $cols = [
                        ['2nd Serve due', '#ea580c', route('special-assignment.notice'), $attention['second']->map(fn ($n) => [
                            optional($n->application)->spa_id ?: $n->file_number, $n->recipient_name,
                            $n->due ? ($n->due->isFuture() ? 'Due '.$n->due->format('d/m') : 'Due now') : '—'])],
                        ['Awaiting Commissioner', '#7c3aed', route('special-assignment.memo'), $attention['memos']->map(fn ($m) => [
                            optional($m->application)->spa_id ?: $m->memo_no, $m->memo_no, optional($m->forwarded_at)->format('d/m') ?: '—'])],
                        ['Bills outstanding', '#16a34a', route('programmes.bills').'?url=spa', $attention['bills']->map(fn ($b) => [
                            optional($b->application)->spa_id ?: $b->reference_id, optional($b->application)->owner_name, $ngn($b->amount)])],
                        ['Not inspected yet', '#0891b2', route('special-assignment.field-data'), $attention['inspect']->map(fn ($a) => [
                            $a->spa_id ?: $a->file_number, $a->owner_name, optional($a->created_at)->format('d/m')])],
                    ];
                @endphp
                @foreach($cols as [$title, $hue, $href, $items])
                <div class="rounded-xl border border-gray-100 p-3" style="background:linear-gradient(180deg, {{ $hue }}0d, #fff 60%)">
                    <a href="{{ $href }}" class="flex items-center justify-between text-xs font-bold uppercase tracking-wide" style="color: {{ $hue }}">
                        <span>{{ $title }}</span><span class="px-2 py-0.5 rounded-full text-white" style="background: {{ $hue }}">{{ $items->count() }}</span>
                    </a>
                    <div class="mt-2 divide-y divide-gray-100">
                        @forelse($items as [$ref, $who, $when])
                        <div class="py-1.5 flex items-center justify-between gap-2 text-sm">
                            <span class="min-w-0"><span class="block font-semibold text-gray-800 truncate">{{ $ref }}</span><span class="block text-xs text-gray-500 truncate">{{ $who ?: '—' }}</span></span>
                            <span class="text-xs text-gray-600 whitespace-nowrap">{{ $when }}</span>
                        </div>
                        @empty
                        <div class="py-3 text-xs text-gray-400">Nothing waiting.</div>
                        @endforelse
                    </div>
                </div>
                @endforeach

                {{-- Settled bills handed on for a new file number. --}}
                <div class="rounded-xl border border-gray-100 p-3" style="background:linear-gradient(180deg, #0d94880d, #fff 60%)">
                    <a href="{{ route('change-of-purpose.index', ['view' => 'initiate']) }}" class="flex items-center justify-between text-xs font-bold uppercase tracking-wide" style="color:#0d9488">
                        <span>New file no.</span><span class="px-2 py-0.5 rounded-full text-white" style="background:#0d9488">{{ $attention['newfile']->count() }}</span>
                    </a>
                    <div class="mt-2 divide-y divide-gray-100">
                        @forelse($attention['newfile'] as $a)
                        <div class="py-1.5 flex items-center justify-between gap-2 text-sm">
                            <span class="min-w-0"><span class="block font-semibold text-gray-800 truncate">{{ $a->spa_id ?: $a->file_number }}</span>
                                <span class="block text-xs text-gray-500 truncate">{{ $a->new_file_route === 'change_of_purpose' ? 'Change of Purpose' : 'New Conversion' }} · {{ $a->owner_name ?: '—' }}</span></span>
                            @if($a->new_file_route === 'change_of_purpose')
                                <a href="{{ route('mls-fileno.index') }}" class="text-xs font-semibold whitespace-nowrap" style="color:#0d9488" title="Pick the Change of Purpose on the MLS screen to commission the new number">Commission →</a>
                            @else
                                <button type="button" class="btn-record-newfile text-xs font-semibold whitespace-nowrap" style="color:#0d9488" data-id="{{ $a->id }}" data-ref="{{ $a->spa_id ?: $a->file_number }}">Record no.</button>
                            @endif
                        </div>
                        @empty
                        <div class="py-3 text-xs text-gray-400">Nothing waiting.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent records --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
            <div class="flex items-center justify-between mb-3">
                <div><div class="text-sm font-bold text-gray-800">Recent records</div><div class="text-xs text-gray-500">The step each one is at now</div></div>
                <a href="{{ route('special-assignment.field-data') }}" class="text-xs font-semibold" style="color:#8c9008">All field records →</a>
            </div>
            <div class="overflow-x-auto" data-column-picker="off">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-100">
                        <th class="px-3 py-2">SPAS ID</th><th class="px-3 py-2">Owner</th><th class="px-3 py-2">Location</th>
                        <th class="px-3 py-2">Captured</th><th class="px-3 py-2">Now at</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($recent as $a)
                            @php [$posLabel, $posHue] = $stageMeta[$positions[$a->id] ?? 'record'] ?? ['—', '#94a3b8']; @endphp
                            <tr>
                                <td class="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">{{ $a->spa_id ?: $a->file_number }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $a->owner_name ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $a->location ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ optional($a->created_at)->format('d/m/Y') }}{{ $a->created_by ? ' · '.$a->created_by : '' }}</td>
                                <td class="px-3 py-2"><span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap" style="background: {{ $posHue }}1a; color: {{ $posHue }}"><span class="w-1.5 h-1.5 rounded-full" style="background: {{ $posHue }}"></span>{{ $posLabel }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">No records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    @include('admin.footer')
</div>

<script>
    // New Conversion: record the file number the officer issued on the MLS screen.
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-record-newfile');
        if (!btn) return;
        const res = await Swal.fire({
            title: 'New file number for ' + btn.dataset.ref,
            html: '<p class="text-sm text-gray-600">Issue it under Conversion on the MLS file number screen, then enter it here.</p>',
            input: 'text', inputPlaceholder: 'e.g. CON-COM-2026-0001', showCancelButton: true,
            confirmButtonText: 'Record', confirmButtonColor: '#0d9488',
            preConfirm: (v) => (v || '').trim() || (Swal.showValidationMessage('Enter the file number'), false),
        });
        if (!res.isConfirmed) return;
        const r = await fetch('{{ url('special-assignment/land-records') }}/' + btn.dataset.id + '/new-file-number', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ new_file_number: res.value }),
        });
        const d = await r.json().catch(() => ({}));
        if (r.ok && d.success) { await Swal.fire({ icon: 'success', title: 'Recorded', text: d.message }); location.reload(); }
        else Swal.fire({ icon: 'error', title: 'Not recorded', text: d.message || (d.errors ? Object.values(d.errors)[0][0] : 'Failed') });
    });
</script>

<style>
    .spas-hero { background: radial-gradient(120% 140% at 0% 0%, #4d5106 0%, #2f3204 45%, #1f2937 100%); }
    .spas-hero::after { content: ''; position: absolute; inset: 0; background: radial-gradient(60% 80% at 85% 30%, rgba(186,191,12,.35), transparent 70%); }
    .spas-hero__art { position: absolute; right: 18px; bottom: -6px; width: 360px; height: 200px; z-index: 5; }
    .spas-kpi { transition: transform .15s, box-shadow .15s; }
    .spas-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15,23,42,.08); }
    .spas-kpi__icon {
        flex: none; display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 14px;
        color: #fff; background: linear-gradient(145deg, color-mix(in srgb, var(--hue) 70%, #fff), var(--hue));
        box-shadow: 0 6px 14px color-mix(in srgb, var(--hue) 28%, transparent);
    }
</style>
@endsection
