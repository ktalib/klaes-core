@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
{{--
  Correction workspace for one edit request.

  The record-editing controls do NOT reimplement anything: they post to the same
  legalsearch.* endpoints the main Legal Search timeline uses, so a correction
  made here and a correction made there are the same operation. Every one of them
  is recorded in audit_logs with the admin's name and a timestamp.

  Left: the report the member complained about, frozen at the moment they saw it.
  Right: the live report, rebuilt from buildPrintReport() on every load.
  Seeing both is the point — the admin needs to know what the member saw.
--}}
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full"
     id="phs-correct"
     data-file-number="{{ $fileNumber }}"
     data-edit-request-id="{{ $editRequest->id }}">

    @include('admin.header', [
        'PageTitle' => $PageTitle,
        'PageDescription' => 'Correct the records for ' . ($fileNumber ?: '—') . ', then return the request so the member can re-run free of charge.',
    ])

    <div class="flex-1 p-6 space-y-5">
        @if (session('error'))
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        {{-- What the member said --}}
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="rounded-full bg-amber-600 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">
                            {{ $editRequest->statusLabel() }}
                        </span>
                        <span class="text-sm font-bold text-amber-900">{{ $editRequest->reasonLabel() }}</span>
                    </div>
                    <p class="text-sm text-amber-900 whitespace-pre-line">{{ $editRequest->reason }}</p>
                    <p class="mt-2 text-[11px] text-amber-700">
                        {{ $editRequest->requester_name ?: 'Member' }}
                        @if (optional($editRequest->institution)->name) · {{ $editRequest->institution->name }} @endif
                        · {{ optional($editRequest->requested_at)->format('d M Y H:i') ?? '—' }}
                        @if ($editRequest->reference_no) · ref <span class="font-mono">{{ $editRequest->reference_no }}</span> @endif
                    </p>
                </div>
                <a href="{{ route('system-admin.phs.edit-requests') }}"
                   class="shrink-0 rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                    Back to queue
                </a>
            </div>
        </div>

        @if ($reportError)
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $reportError }}
            </div>
        @endif

        {{-- Editing toolbar --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 mr-1">Correct records</span>

                <button type="button" id="pc-edit" disabled
                    class="pc-tool rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                    Edit selected
                </button>
                <button type="button" id="pc-add"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                    Add missing record
                </button>
                <button type="button" id="pc-remove" disabled
                    class="pc-tool rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-40 disabled:cursor-not-allowed">
                    Remove selected
                </button>
                <button type="button" id="pc-drop" disabled
                    class="pc-tool rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                    Drop from this file
                </button>

                {{-- Drag-to-reorder. Asks whether to save permanently or just for
                     this browser, because a permanent order rewrites what everyone
                     sees, including the report. --}}
                <button type="button" id="ls-arrange-btn"
                    class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300">
                    Arrange
                </button>

                <span class="mx-1 h-5 w-px bg-slate-200"></span>

                <a href="{{ route('legal_search.index', ['file' => $fileNumber]) }}" target="_blank"
                   class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300">
                    Open full timeline
                </a>
                <button type="button" id="pc-refresh"
                    class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300">
                    Preview updated result
                </button>

                <span id="ls-arrange-status" class="ml-auto text-xs text-slate-400"></span>
                <span id="pc-status" class="text-xs text-slate-400"></span>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">
                Select a row to enable the row actions. Every change is written to the audit trail
                against your name and the time it was made.
            </p>
        </div>

        {{-- Side-by-side: what the member saw vs what is on file now --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            @php
                // Counted here so the header can state it before the list is reached.
                $originalRows = $original['rows'] ?? [];
            @endphp
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">What the member received</h3>
                        <p class="text-[11px] text-slate-400">Frozen at the time of their search &mdash; not affected by your corrections.</p>
                    </div>
                    {{-- Mirrors the count on the live pane, so the two are directly
                         comparable: a difference in these numbers is the quickest read
                         on whether the correction changed anything. --}}
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                        {{ count($originalRows) }} {{ \Illuminate\Support\Str::plural('transaction', count($originalRows)) }}
                    </span>
                </div>
                <div class="max-h-[520px] overflow-auto p-4">
                    @if (empty($originalRows))
                        <p class="py-8 text-center text-xs text-slate-400">No snapshot was captured with this request.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($originalRows as $row)
                                <li class="rounded-md border border-slate-200 px-3 py-2">
                                    <div class="text-xs font-semibold text-slate-800">
                                        {{ $row['instrument_type'] ?? $row['transaction_type'] ?? 'Instrument' }}
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        {{ $row['grantor'] ?? $row['party_1'] ?? '—' }}
                                        &rarr;
                                        {{ $row['grantee'] ?? $row['party_2'] ?? '—' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ $row['transaction_date'] ?? $row['reg_date'] ?? '' }}
                                        @if (!empty($row['source_table'])) · {{ $row['source_table'] }} @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Current result &mdash; {{ $fileNumber ?: '—' }}</h3>
                        <p class="text-[11px] text-slate-400">Live from the report engine. This is what the re-run will produce.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                        {{ count($records ?? []) }} {{ \Illuminate\Support\Str::plural('record', count($records ?? [])) }}
                        @if (!empty($report['rows']))
                            <span class="font-normal text-slate-400">/ {{ count($report['rows']) }} printed</span>
                        @endif
                    </span>
                </div>
                <div class="max-h-[520px] overflow-auto p-4" id="pc-current">
                    @include('system-admin.phs.partials.edit_request_rows', [
                        'report'  => $report,
                        'records' => $records ?? [],
                    ])
                </div>
            </div>
        </div>

        {{-- Return / decline --}}
        @if ($editRequest->status === \App\Models\Phs\PhsEditRequest::STATUS_EDIT_REQUESTED)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <form method="POST" action="{{ route('system-admin.phs.edit-requests.return', $editRequest->id) }}"
                      class="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="text-sm font-bold text-slate-800 mb-1">Return for re-run</h3>
                    <p class="text-[11px] text-slate-500 mb-3">
                        Marks the request <strong>Ready for Re-run</strong> and lets this member re-run the
                        search once <strong>without a token being deducted</strong>.
                    </p>
                    <textarea name="admin_response" rows="3" maxlength="2000"
                        placeholder="What did you correct? The member sees this."
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <button type="submit"
                        class="mt-3 w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Return &amp; authorise free re-run
                    </button>
                </form>

                <form method="POST" action="{{ route('system-admin.phs.edit-requests.decline', $editRequest->id) }}"
                      class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="text-sm font-bold text-slate-800 mb-1">Decline</h3>
                    <p class="text-[11px] text-slate-500 mb-3">
                        Nothing to correct. Closes the request and grants <strong>no</strong> free re-run.
                    </p>
                    <textarea name="admin_response" rows="3" required maxlength="2000"
                        placeholder="Explain why no correction was needed. Required."
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <button type="submit"
                        class="mt-3 w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Decline request
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 mb-1">{{ $editRequest->statusLabel() }}</h3>
                <p class="text-xs text-slate-500">
                    @if ($editRequest->reviewer_name)
                        Handled by {{ $editRequest->reviewer_name }}
                        on {{ optional($editRequest->corrected_at)->format('d M Y H:i') }}.
                    @endif
                    @if ($editRequest->rerun_at)
                        The member re-ran this search on
                        {{ $editRequest->rerun_at->format('d M Y H:i') }} at no charge.
                    @endif
                </p>
                @if ($editRequest->admin_response)
                    <p class="mt-2 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $editRequest->admin_response }}</p>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- The SAME Edit Record modal and Add Property Record card the on-premise Legal
     Search screen uses. Included, not reimplemented: the field definitions name
     real table columns and a second copy of them would rot silently. --}}
@include('propertycard.css.style')
@include('legal_search.partials.record_edit_modal')
@include('propertycard.partials.add_property_record')

<script>
  // Config for the shared arrangement partial below.
  window.LS_ARRANGE = {
      propId: @json($propId),
      containerId: 'pc-current',
      rowsSelector: '[data-pc-row]',
  };
</script>
@include('legal_search.partials.timeline_arrange_js')

<script>
  // Scope the shared modal script expects from its host page. On the Legal Search
  // screen these come from the search page itself; here they are supplied directly.
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const searchResults = [];
  const dbLandUseOptions = @json($landUseOptions ?? []);
  const dbDistrictOptions = @json($districtOptions ?? []);
  const dbInstrumentTypeOptions = @json($instrumentTypeOptions ?? []);

  const cleanupAjax = async (url, body) => {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });
    return res.json();
  };

  // Prefill source used by the Add Property Record card.
  window.__lsLastSearchedFileNumber = @json($fileNumber);

  @include('legal_search.partials.record_edit_modal_js')

  // The partial declares these with const, which in a classic script is a
  // script-scoped binding and NOT a window property. The page script below runs
  // inside its own IIFE, so publish them explicitly rather than relying on
  // cross-script lexical scope.
  window.openEditModal = openEditModal;
  window.closeEditModal = closeEditModal;
</script>

<script>
(function () {
    const root = document.getElementById('phs-correct');
    if (!root) return;

    const fileNumber = root.dataset.fileNumber || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const statusEl = document.getElementById('pc-status');

    // The endpoints are the main Legal Search timeline's own. Nothing here
    // reimplements record editing — that logic has one home and this is a caller.
    const ROUTES = {
        remove: @json(route('legalsearch.remove')),
        drop:   @json(route('legalsearch.drop')),
        update: @json(route('legalsearch.update')),
        create: @json(route('legalsearch.createRecord')),
        get:    @json(route('legalsearch.getRecord')),
    };

    let selected = null;

    const setStatus = (text, tone) => {
        statusEl.textContent = text || '';
        statusEl.className = 'ml-auto text-xs ' + (
            tone === 'error' ? 'text-rose-600' : tone === 'ok' ? 'text-emerald-600' : 'text-slate-400'
        );
    };

    const setToolsEnabled = (on) => {
        document.querySelectorAll('.pc-tool').forEach(b => { b.disabled = !on; });
    };

    // Row selection
    root.addEventListener('click', (e) => {
        const row = e.target.closest('[data-pc-row]');
        if (!row) return;
        root.querySelectorAll('[data-pc-row]').forEach(r => {
            r.classList.remove('ring-2', 'ring-indigo-500', 'border-indigo-400', 'bg-indigo-50');
            r.querySelector('.pc-dot')?.classList.remove('border-indigo-500');
            r.querySelector('.pc-dot-fill')?.classList.remove('bg-indigo-500');
        });
        row.classList.add('ring-2', 'ring-indigo-500', 'border-indigo-400', 'bg-indigo-50');
        row.querySelector('.pc-dot')?.classList.add('border-indigo-500');
        row.querySelector('.pc-dot-fill')?.classList.add('bg-indigo-500');
        selected = {
            id: row.dataset.recordId,
            table: row.dataset.sourceTable,
            label: row.dataset.label || 'this record',
        };
        setToolsEnabled(!!selected.id && !!selected.table);
        setStatus('Selected: ' + selected.label);
    });

    const post = async (url, payload) => {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok || body.success === false) {
            throw new Error(body.message || ('Request failed (' + res.status + ')'));
        }
        return body;
    };

    const requireSelection = () => {
        if (!selected || !selected.id || !selected.table) {
            setStatus('Select a record first.', 'error');
            return false;
        }
        return true;
    };

    document.getElementById('pc-remove')?.addEventListener('click', async () => {
        if (!requireSelection()) return;
        if (!confirm('Remove ' + selected.label + ' from this file?\n\nThis is recorded in the audit trail against your name.')) return;
        try {
            setStatus('Removing...');
            await post(ROUTES.remove, { records: [{ id: selected.id, table: selected.table }], file_number: fileNumber });
            setStatus('Removed. Click "Preview updated result" to refresh.', 'ok');
        } catch (err) { setStatus(err.message, 'error'); }
    });

    document.getElementById('pc-drop')?.addEventListener('click', async () => {
        if (!requireSelection()) return;
        if (!confirm('Drop ' + selected.label + ' from this file?')) return;
        try {
            setStatus('Dropping...');
            await post(ROUTES.drop, { records: [{ id: selected.id, table: selected.table }], file_number: fileNumber });
            setStatus('Dropped. Click "Preview updated result" to refresh.', 'ok');
        } catch (err) { setStatus(err.message, 'error'); }
    });

    // Edit opens the SHARED Edit Record modal (legal_search.partials.record_edit_modal_js),
    // the same one the on-premise screen uses, so the fields and the save endpoint
    // are identical on both pages.
    document.getElementById('pc-edit')?.addEventListener('click', () => {
        if (!requireSelection()) return;
        if (typeof window.openEditModal === 'function') {
            window.openEditModal(selected.table, parseInt(selected.id, 10));
        } else {
            setStatus('Edit modal did not load on this page.', 'error');
        }
    });

    // Add opens the Add Property Record card, prefilled for this file. Which table
    // it targets follows the row the admin has selected, defaulting to pra.
    document.getElementById('pc-add')?.addEventListener('click', () => {
        const modal = document.getElementById('property-form-dialog');
        const form = document.getElementById('property-record-form');
        if (!modal || !form) {
            setStatus('Add Record card did not load on this page.', 'error');
            return;
        }

        const target = (selected?.table === 'CofO_staging') ? 'CofO_staging' : 'pra';
        let targetInput = form.querySelector('input[name="target_table"]');
        if (!targetInput) {
            targetInput = document.createElement('input');
            targetInput.type = 'hidden';
            targetInput.name = 'target_table';
            form.appendChild(targetInput);
        }
        targetInput.value = target;

        const modeInput = form.querySelector('[data-model="formMode"]') || form.querySelector('[name="record_mode"]');
        if (modeInput) modeInput.value = 'property';

        form.querySelectorAll('[name="fileno"], [name="mlsFNo"], [name="file_number"]').forEach(el => {
            if (!el.value) el.value = fileNumber;
        });

        modal.classList.add('show');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        setStatus('Adding a record to ' + fileNumber + ' (' + target + ').');
    });

    document.getElementById('pc-refresh')?.addEventListener('click', () => window.location.reload());
})();
</script>
@endsection
