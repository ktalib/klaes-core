@extends('layouts.app')

@php
    use App\Models\MasterJsiReport;
    use App\Support\MasterJsiPortionTemplates;

    /**
     * The Master JSI register.
     *
     * One page for both categories and all five sheets — the officer filters rather
     * than choosing between screens, because a site visit is the same job whichever
     * parcel update sent it.
     */
    $statusTones = [
        MasterJsiReport::STATUS_DRAFT     => 'bg-slate-100 text-slate-600',
        MasterJsiReport::STATUS_GENERATED => 'bg-blue-100 text-blue-700',
        MasterJsiReport::STATUS_SUBMITTED => 'bg-amber-100 text-amber-700',
        MasterJsiReport::STATUS_APPROVED  => 'bg-emerald-100 text-emerald-700',
        MasterJsiReport::STATUS_REJECTED  => 'bg-red-100 text-red-700',
    ];
@endphp

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }

    .menu-item { display:block; width:100%; text-align:left; padding:.55rem 1rem; font-size:.8rem; color:#334155; }
    .menu-item:hover:not(:disabled):not(.is-disabled) { background:#f8fafc; }

    /* A step not yet reachable, or already taken, greys out rather than
       disappearing — the officer can still see where they are in the sequence. */
    .menu-item:disabled,
    .menu-item.is-disabled { color:#cbd5e1; cursor:not-allowed; pointer-events:none; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Master JSI',
        'PageDescription' => 'Physical Planning site observations for SPU and APU parcel updates.',
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Header --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-7 py-6 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                        <i data-lucide="ruler" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-slate-800 leading-tight">Master JSI</h1>
                        <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                            The Physical Planning site observation behind a parcel update — one form for
                            <strong class="text-slate-600">SPU</strong> (Subdivision, Separation, Merger, Extension,
                            Change of Purpose) and <strong class="text-slate-600">APU</strong> (Duplex). An approved
                            sheet is the clearance the recommendation and the approval rest on.
                        </p>
                    </div>
                </div>
                <a href="{{ route('master-jsi.create') }}"
                    class="shrink-0 inline-flex items-center gap-2 px-6 py-3 bg-red-600 text-white rounded-xl text-sm font-bold shadow-sm shadow-red-600/20 hover:bg-red-700 transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Create Master JSI
                </a>
            </div>

            {{-- The pipeline, spelled out. Skipping a step is the usual support call. --}}
            <div class="px-7 py-3.5 border-t border-slate-100 bg-slate-50/70 flex flex-wrap items-center gap-x-2 gap-y-2 text-[11px] font-bold text-slate-400">
                @foreach (['Capture observation', 'Generate sheet', 'Submit', 'Approve', 'Send to Deeds'] as $i => $step)
                    @if ($i)<i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-300"></i>@endif
                    <span class="{{ $loop->last ? 'text-red-600' : '' }}">{{ $step }}</span>
                @endforeach
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach ([
                ['Total', $stats['total'], 'clipboard-list', 'text-slate-500'],
                ['Draft', $stats['draft'], 'pencil', 'text-amber-500'],
                ['Generated', $stats['generated'], 'file-text', 'text-blue-500'],
                ['Submitted', $stats['submitted'], 'send', 'text-orange-500'],
                ['Approved', $stats['approved'], 'check-circle', 'text-emerald-500'],
            ] as [$label, $value, $icon, $tone])
            <div class="bg-white rounded-2xl border border-slate-100 p-4 hover:border-slate-200 transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400 font-black">{{ $label }}</p>
                    <i data-lucide="{{ $icon }}" class="w-4 h-4 {{ $tone }}"></i>
                </div>
                <p class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ $value }}</p>
            </div>
            @endforeach
        </div>

        {{-- Register --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-baseline gap-2.5">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Inspection Register</h2>
                    <span class="text-xs font-bold text-slate-400">{{ $records->total() }} record{{ $records->total() === 1 ? '' : 's' }}</span>
                </div>

                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <select name="category" class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition">
                        <option value="">All categories</option>
                        @foreach (MasterJsiReport::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $key }}</option>
                        @endforeach
                    </select>

                    <select name="type" class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition">
                        <option value="">All updates</option>
                        @foreach (MasterJsiPortionTemplates::types() as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                                {{ ucwords(str_replace('_', ' ', $type)) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status" class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition">
                        <option value="">Any status</option>
                        @foreach (array_keys($statusTones) as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>

                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ref, file no, applicant"
                            class="pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-sm w-60 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition">
                    </div>

                    <button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-900 transition">Filter</button>
                    @if (array_filter($filters ?? []))
                        <a href="{{ route('master-jsi.index') }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-2">Clear</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">JSI Ref</th>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Parcel Update</th>
                            <th class="px-4 py-3 text-left">File No</th>
                            <th class="px-4 py-3 text-left">Applicant</th>
                            <th class="px-4 py-3 text-left">Inspected</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $record)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-black text-slate-700 whitespace-nowrap">
                                <a href="{{ route('master-jsi.show', $record->id) }}" class="hover:text-red-600 transition">{{ $record->jsi_ref }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $record->category === 'APU' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $record->category }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 font-bold whitespace-nowrap">
                                {{ ucwords(str_replace('_', ' ', $record->parcel_update_type)) }}
                                @if ($record->isLegacyLayout() === false && $record->purposes->isNotEmpty())
                                    <span class="block mt-1 flex flex-wrap gap-1">
                                        @foreach ($record->purposeSlugs() as $slug)
                                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded {{ in_array($slug, ['merger', 'extension'], true) ? 'bg-slate-100 text-slate-600' : ($slug === 'subdivision' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-700') }}">
                                                {{ \App\Models\MasterJsiPurpose::PURPOSES[$slug] ?? ucfirst($slug) }}
                                            </span>
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $record->file_number ?: '—' }}</td>
                            {{-- Display case only: names arrive as the officer typed them, so a
                                 register listing "IBRAHIM DANLADI" beside "Musa Bello" reads as
                                 though the difference means something. The stored value is what
                                 every lookup runs against. --}}
                            <td class="px-4 py-3 text-slate-700">{{ \App\Support\PersonName::display($record->applicant_name) ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                                {{ $record->inspection_date ? $record->inspection_date->format('d M Y') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $statusTones[$record->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ strtoupper($record->status) }}
                                    </span>
                                    @if ($record->isReturned())
                                        <span class="text-[10px] font-black px-2 py-1 rounded-md bg-rose-100 text-rose-700"
                                            title="Returned on {{ $record->returned_at?->format('d M Y') }}">RETURNED</span>
                                    @endif
                                    @if ($record->sent_to_deeds_at)
                                        <span class="text-[10px] font-black px-2 py-1 rounded-md bg-indigo-50 text-indigo-600"
                                            title="Sent to Deeds on {{ $record->sent_to_deeds_at->format('d M Y') }}">DEEDS</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-block text-left">
                                    <button onclick="toggleRowMenu({{ $record->id }}, this)" title="Actions"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                    @php
                                        /**
                                         * The menu walks the sheet through its pipeline: each action
                                         * unlocks the next and closes behind itself, so the officer is
                                         * never offered a step the server would refuse.
                                         */
                                        $isApproved = $record->status === MasterJsiReport::STATUS_APPROVED;
                                        $isRejected = $record->status === MasterJsiReport::STATUS_REJECTED;
                                        $sentToDeeds = (bool) $record->sent_to_deeds_at;

                                        $canEdit     = !$isApproved;
                                        $canGenerate = !$isApproved && !$isRejected && !$record->generated_at;
                                        $canPrint    = (bool) $record->generated_at;
                                        $canSubmit   = (bool) $record->generated_at && !$record->submitted_at && !$isRejected;
                                        $canDecide   = (bool) $record->submitted_at && !$isApproved && !$isRejected;
                                        $canReturn   = $canDecide;
                                        $canSend     = $isApproved && !$sentToDeeds;
                                        $canDelete   = !$isApproved;

                                        // Why an action is unavailable, so a greyed row is not a mystery.
                                        $why = function ($enabled, $reason) use ($isRejected) {
                                            if ($enabled) return '';
                                            if ($isRejected) return 'This Master JSI was rejected';
                                            return $reason;
                                        };
                                    @endphp

                                    <div id="row-menu-{{ $record->id }}"
                                        class="hidden fixed z-[999] w-60 rounded-xl bg-white shadow-xl ring-1 ring-black/5 divide-y divide-slate-100 text-left">
                                        {{-- Reading is always allowed, whatever state the sheet is in. --}}
                                        <div class="py-1">
                                            <a href="{{ route('master-jsi.show', $record->id) }}" class="menu-item block">View sheet</a>
                                            <a href="{{ route('master-jsi.edit', $record->id) }}"
                                                title="{{ $why($canEdit, 'An approved sheet cannot be edited') }}"
                                                class="menu-item block {{ $canEdit ? '' : 'is-disabled' }}">Edit observation</a>
                                        </div>

                                        <div class="py-1">
                                            <button onclick="jsiAction({{ $record->id }}, 'generate')" @disabled(!$canGenerate)
                                                title="{{ $why($canGenerate, $record->generated_at ? 'Already generated' : 'Nothing left to generate') }}"
                                                class="menu-item">Generate sheet</button>
                                            <a href="{{ route('master-jsi.print', $record->id) }}" target="_blank"
                                                title="{{ $canPrint ? '' : 'Generate the sheet first' }}"
                                                class="menu-item block {{ $canPrint ? '' : 'is-disabled' }}">Print sheet</a>
                                        </div>

                                        <div class="py-1">
                                            <button onclick="jsiAction({{ $record->id }}, 'submit')" @disabled(!$canSubmit)
                                                title="{{ $why($canSubmit, $record->submitted_at ? 'Already submitted' : 'Generate the sheet first') }}"
                                                class="menu-item">Submit for approval</button>
                                            <button onclick="jsiDecision({{ $record->id }}, @js($record->jsi_ref))" @disabled(!$canDecide)
                                                title="{{ $why($canDecide, $isApproved ? 'Already approved' : 'Submit the sheet first') }}"
                                                class="menu-item {{ $canDecide ? 'text-emerald-700 font-bold' : '' }}">Approve / Reject</button>
                                            <button onclick="jsiReturn({{ $record->id }}, @js($record->jsi_ref))" @disabled(!$canReturn)
                                                title="{{ $why($canReturn, 'Submit the sheet first') }}"
                                                class="menu-item">Return for correction</button>
                                        </div>

                                        {{-- The handover. It is a stamp and a notification: Deeds is told
                                             there is an inspection to read, and nothing is written to the
                                             registry — same rule the duplex follows sending to Land. --}}
                                        <div class="py-1">
                                            <button onclick="jsiSendToDeeds({{ $record->id }}, @js($record->jsi_ref))" @disabled(!$canSend)
                                                title="{{ $sentToDeeds ? 'Already sent on ' . $record->sent_to_deeds_at->format('d M Y') : $why($canSend, 'Approve the Master JSI first') }}"
                                                class="menu-item {{ $canSend ? 'text-indigo-700 font-bold' : '' }}">Send JSI to Deeds</button>
                                        </div>

                                        <div class="py-1">
                                            <button onclick="jsiDelete({{ $record->id }}, @js($record->jsi_ref))" @disabled(!$canDelete)
                                                title="{{ $canDelete ? '' : 'An approved Master JSI cannot be deleted' }}"
                                                class="menu-item text-red-600">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <i data-lucide="clipboard-list" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                <p class="text-sm font-bold text-slate-500 mt-3">No Master JSI records yet</p>
                                <p class="text-xs text-slate-400 mt-1">Capture a site observation to clear a parcel update for recommendation.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">{{ $records->links() }}</div>
            @endif
        </div>
    </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script>
/**
 * The row menu is positioned FIXED and moved to <body> on open: the table scrolls
 * horizontally, so an absolutely-positioned menu is clipped by the scroll container
 * and drops inside the table. Lifted from the duplex register, which solved this.
 */
window.toggleRowMenu = function (id, trigger) {
    const menu = document.getElementById('row-menu-' + id);
    if (!menu) return;

    const wasOpen = !menu.classList.contains('hidden');
    closeRowMenus();
    if (wasOpen) return;

    if (menu.parentElement !== document.body) document.body.appendChild(menu);

    menu.classList.remove('hidden');

    const rect = trigger.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;

    // Flip above the trigger when the menu would run off the bottom.
    const top = spaceBelow < menuRect.height && rect.top > menuRect.height
        ? rect.top - menuRect.height - 4
        : rect.bottom + 4;

    menu.style.top = Math.max(8, top) + 'px';
    menu.style.left = Math.max(8, Math.min(rect.right - menuRect.width, window.innerWidth - menuRect.width - 8)) + 'px';
};

function closeRowMenus() {
    document.querySelectorAll('[id^="row-menu-"]').forEach(m => m.classList.add('hidden'));
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('[id^="row-menu-"]') && !e.target.closest('button[onclick^="toggleRowMenu"]')) {
        closeRowMenus();
    }
});
window.addEventListener('scroll', closeRowMenus, true);
window.addEventListener('resize', closeRowMenus);

const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body || {}),
    });
    return { ok: res.ok, data: await res.json().catch(() => ({})) };
}

function toast(icon, title, text) {
    if (window.Swal) return Swal.fire({ icon, title, text, confirmButtonColor: '#dc2626' });
    alert(title + (text ? '\n\n' + text : ''));
}

window.jsiAction = async function (id, action) {
    closeRowMenus();
    const { ok, data } = await post(`/master-jsi/${id}/${action}`);
    if (!ok) return toast('error', 'Not done', data.message || 'The action was refused.');
    toast('success', 'Done', data.message).then(() => location.reload());
};

window.jsiDecision = async function (id, ref) {
    closeRowMenus();
    if (!window.Swal) return;

    const { value } = await Swal.fire({
        title: `Master JSI ${ref}`,
        text: 'Approving clears the parcel update for its recommendation.',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Approve',
        denyButtonText: 'Reject',
        confirmButtonColor: '#059669',
        denyButtonColor: '#dc2626',
    }).then(r => ({ value: r.isConfirmed ? 'approve' : (r.isDenied ? 'reject' : null) }));

    if (!value) return;

    let body = { supervisor_remarks: '' };
    if (value === 'reject') {
        const { value: reason } = await Swal.fire({
            title: 'Reason for rejection',
            input: 'textarea',
            inputPlaceholder: 'What was wrong with the observation?',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
        });
        if (reason === undefined) return;
        body = { reason, supervisor_remarks: reason };
    } else {
        const { value: remarks } = await Swal.fire({
            title: 'Supervisor remarks (optional)',
            input: 'textarea',
            inputPlaceholder: 'Conditions, corrections or notes that go on the sheet.',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            confirmButtonText: 'Approve',
        });
        if (remarks === undefined) return;
        body = { supervisor_remarks: remarks || '' };
    }

    const { ok, data } = await post(`/master-jsi/${id}/${value}`, body);
    if (!ok) return toast('error', 'Not done', data.message || 'The decision was refused.');
    toast('success', 'Done', data.message).then(() => location.reload());
};

window.jsiReturn = async function (id, ref) {
    closeRowMenus();
    if (!window.Swal) return;

    const { value: remarks, isConfirmed } = await Swal.fire({
        title: `Return ${ref} to the officer?`,
        text: 'The sheet goes back to the generated state so it can be edited and resubmitted.',
        input: 'textarea',
        inputPlaceholder: 'What the officer should correct (optional)',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Return',
        confirmButtonColor: '#b45309',
    });
    if (!isConfirmed) return;

    const { ok, data } = await post(`/master-jsi/${id}/return`, { supervisor_remarks: remarks || '' });
    if (!ok) return toast('error', 'Not returned', data.message || 'The return was refused.');
    toast('success', 'Returned', data.message).then(() => location.reload());
};

window.jsiSendToDeeds = async function (id, ref) {
    closeRowMenus();

    if (window.Swal) {
        const r = await Swal.fire({
            title: `Send ${ref} to Deeds?`,
            text: 'Deeds will be notified that the inspection is ready to read. Nothing is written to the registry.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Send to Deeds',
            confirmButtonColor: '#4f46e5',
        });
        if (!r.isConfirmed) return;
    }

    const { ok, data } = await post(`/master-jsi/${id}/send-to-deeds`);
    if (!ok) return toast('error', 'Not sent', data.message || 'The handover was refused.');
    toast('success', 'Sent', data.message).then(() => location.reload());
};

window.jsiDelete = async function (id, ref) {
    closeRowMenus();

    if (window.Swal) {
        const r = await Swal.fire({
            title: `Delete ${ref}?`,
            text: 'The sheet is removed from the register.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc2626',
        });
        if (!r.isConfirmed) return;
    }

    const res = await fetch(`/master-jsi/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) return toast('error', 'Not deleted', data.message || 'The delete was refused.');
    toast('success', 'Deleted', data.message).then(() => location.reload());
};

if (window.lucide) lucide.createIcons();
</script>
@endsection
