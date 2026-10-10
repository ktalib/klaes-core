@extends('layouts.app')

@php
    use App\Models\MasterJsiReport;
    use App\Http\Controllers\PhysicalPlanning\MasterJsiController;

    /**
     * Master JSI Approval — the PP Director's desk (and, with ?stage=ps, the PS's).
     *
     * Lists the sheets captured in the Master JSI register. The decision is made
     * from the row: View, Approve, Reject. Approve and Reject only light up for a
     * sheet waiting at the stage this user may decide.
     */
    $statusTones = [
        MasterJsiReport::STATUS_DRAFT             => 'bg-slate-100 text-slate-600',
        MasterJsiReport::STATUS_GENERATED         => 'bg-blue-100 text-blue-700',
        MasterJsiReport::STATUS_SUBMITTED         => 'bg-amber-100 text-amber-700',
        MasterJsiReport::STATUS_DIRECTOR_APPROVED => 'bg-purple-100 text-purple-700',
        MasterJsiReport::STATUS_APPROVED          => 'bg-emerald-100 text-emerald-700',
        MasterJsiReport::STATUS_REJECTED          => 'bg-red-100 text-red-700',
    ];
    $statusLabels = [
        MasterJsiReport::STATUS_SUBMITTED         => 'AWAITING PP DIRECTOR',
        MasterJsiReport::STATUS_DIRECTOR_APPROVED => 'AWAITING PS',
    ];
    $deskLabel = $stage === 'ps' ? 'PS' : 'PP Director';

    $cards = [
        ['Total Master JSI', $stats['total'], 'clipboard-list', 'text-slate-500', 'all'],
        ['Awaiting PP Director', $stats['awaiting_director'], 'user-check', 'text-amber-500', $stage === 'director' ? 'queue' : null],
    ];
    if ($twoStage) {
        $cards[] = ['Awaiting PS', $stats['awaiting_ps'], 'stamp', 'text-purple-500', $stage === 'ps' ? 'queue' : MasterJsiReport::STATUS_DIRECTOR_APPROVED];
    }
    $cards[] = ['Approved', $stats['approved'], 'check-circle', 'text-emerald-500', MasterJsiReport::STATUS_APPROVED];
    $cards[] = ['Rejected', $stats['rejected'], 'x-circle', 'text-red-500', MasterJsiReport::STATUS_REJECTED];

    $tabs = [
        'queue' => 'Awaiting ' . $deskLabel,
        'all' => 'All Master JSI',
        MasterJsiReport::STATUS_APPROVED => 'Approved',
        MasterJsiReport::STATUS_REJECTED => 'Rejected',
    ];
@endphp

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }
    .act-btn { width:2rem; height:2rem; border-radius:.5rem; display:inline-flex; align-items:center; justify-content:center; transition:background .15s, color .15s; }
    .act-btn:disabled { opacity:.35; cursor:not-allowed; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => $PageDescription])

    <div class="py-8 bg-slate-50 min-h-screen">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Header --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-7 py-6 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                    <i data-lucide="{{ $stage === 'ps' ? 'stamp' : 'user-check' }}" class="w-6 h-6 text-red-600"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 leading-tight">Master JSI Approval — {{ $deskLabel }}</h1>
                    <p class="text-xs text-slate-500 mt-1 max-w-2xl leading-relaxed">
                        Sheets captured in the Master JSI register.
                        @if ($twoStage)
                            The PP Director approves first, then the PS; only the PS approval clears the parcel update.
                        @else
                            The PP Director's approval clears the parcel update for its recommendation.
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('master-jsi.index') }}"
                class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">
                <i data-lucide="list" class="w-4 h-4"></i> Inspection Register
            </a>
        </div>

        {{-- Dashboard cards --}}
        <div class="grid grid-cols-2 md:grid-cols-{{ count($cards) }} gap-3">
            @foreach ($cards as [$label, $value, $icon, $tone, $target])
                @php $href = $target ? route('master-jsi.approvals', array_filter(['stage' => $stage === 'ps' ? 'ps' : null, 'show' => $target])) : null; @endphp
                <a @if($href) href="{{ $href }}" @endif
                   class="bg-white rounded-2xl border {{ $target && $show === $target ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-100' }} p-4 hover:border-slate-200 transition block">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 font-black">{{ $label }}</p>
                        <i data-lucide="{{ $icon }}" class="w-4 h-4 {{ $tone }}"></i>
                    </div>
                    <p class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ $value }}</p>
                </a>
            @endforeach
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('master-jsi.approvals', array_filter(['stage' => $stage === 'ps' ? 'ps' : null, 'show' => $key, 'search' => $search])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $show === $key ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" class="flex items-center gap-2">
                    @if ($stage === 'ps')<input type="hidden" name="stage" value="ps">@endif
                    <input type="hidden" name="show" value="{{ $show }}">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Ref, file no, applicant"
                            class="pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-sm w-60 focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>
                    <button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-900">Search</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">JSI Ref</th>
                            <th class="px-4 py-3 text-left">Parcel Update</th>
                            <th class="px-4 py-3 text-left">File No</th>
                            <th class="px-4 py-3 text-left">Applicant</th>
                            <th class="px-4 py-3 text-left">Inspected</th>
                            <th class="px-4 py-3 text-left">Submitted</th>
                            <th class="px-4 py-3 text-left">Created By</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $record)
                            @php
                                $waiting   = $record->awaitingStage();
                                // Only the stage this desk decides. A record waiting for
                                // the other desk shows greyed buttons with the reason.
                                $deskStage = $twoStage ? $stage : 'director';
                                $canDecide = $waiting !== null && $waiting === $deskStage && MasterJsiController::canActAtStage($waiting);
                                $why = $canDecide ? '' : ($waiting === null
                                    ? 'Not awaiting approval'
                                    : 'Waiting for the ' . ($waiting === 'ps' ? 'PS' : 'PP Director'));
                            @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-black text-slate-700 whitespace-nowrap">
                                    <a href="{{ route('master-jsi.show', $record->id) }}" class="hover:text-red-600">{{ $record->jsi_ref }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-bold whitespace-nowrap">
                                    <span class="text-[10px] font-black px-1.5 py-0.5 rounded {{ $record->category === 'APU' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $record->category }}</span>
                                    {{ ucwords(str_replace('_', ' ', $record->parcel_update_type)) }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $record->file_number ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ \App\Support\PersonName::display($record->applicant_name) ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{{ $record->inspection_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{{ $record->submitted_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs whitespace-nowrap">{{ $record->creator?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[10px] font-black px-2 py-1 rounded-md whitespace-nowrap {{ $statusTones[$record->status] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $statusLabels[$record->status] ?? strtoupper($record->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <a href="{{ route('master-jsi.show', $record->id) }}" title="View sheet"
                                       class="act-btn text-slate-500 hover:text-slate-800 hover:bg-slate-100">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>
                                    <button type="button" title="{{ $canDecide ? 'Approve' : $why }}" @disabled(!$canDecide)
                                        onclick="jsiDecide({{ $record->id }}, @js($record->jsi_ref), 'approve')"
                                        class="act-btn text-emerald-600 hover:bg-emerald-50">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                                    </button>
                                    <button type="button" title="{{ $canDecide ? 'Reject' : $why }}" @disabled(!$canDecide)
                                        onclick="jsiDecide({{ $record->id }}, @js($record->jsi_ref), 'reject')"
                                        class="act-btn text-red-600 hover:bg-red-50">
                                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-16 text-center">
                                    <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                    <p class="text-sm font-bold text-slate-500 mt-3">Nothing here</p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ $show === 'queue' ? 'No Master JSI is waiting for the ' . $deskLabel . '.' : 'No Master JSI matches this view.' }}
                                    </p>
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
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

async function jsiPost(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body || {}),
    });
    return { ok: res.ok, data: await res.json().catch(() => ({})) };
}

window.jsiDecide = async function (id, ref, action) {
    if (!window.Swal) return;

    const approving = action === 'approve';
    const { value, isConfirmed } = await Swal.fire({
        title: (approving ? 'Approve ' : 'Reject ') + ref + '?',
        input: 'textarea',
        inputPlaceholder: approving ? 'Remarks (optional)' : 'Reason for rejection',
        inputValidator: v => (!approving && !String(v || '').trim()) ? 'Give the reason for rejecting it.' : undefined,
        showCancelButton: true,
        confirmButtonText: approving ? 'Approve' : 'Reject',
        confirmButtonColor: approving ? '#059669' : '#dc2626',
    });
    if (!isConfirmed) return;

    const remarks = String(value || '').trim();
    const body = approving ? { supervisor_remarks: remarks } : { reason: remarks, supervisor_remarks: remarks };
    const { ok, data } = await jsiPost(`/master-jsi/${id}/${action}`, body);

    if (!ok) {
        return Swal.fire({ icon: 'error', title: 'Not done', text: data.message || 'The decision was refused.', confirmButtonColor: '#dc2626' });
    }
    Swal.fire({ icon: 'success', title: 'Done', text: data.message, confirmButtonColor: '#059669' }).then(() => location.reload());
};

if (window.lucide) lucide.createIcons();
</script>
@endsection
