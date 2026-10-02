@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
{{--
  Review an Online Legal Search result before approving it.

  This is what "Preview report" opens. It is not read-only on purpose: an online
  requester has no way to report a bad result (unlike a PHS member, who can send
  an edit request), so if the approver does not notice and fix a problem here, a
  wrong report is emailed out and nobody is left to challenge it. The reviewing
  screen therefore carries the editing tools.

  It reuses the Legal Search timeline's own record-editing endpoints and its Edit
  Record modal rather than reimplementing either, so a correction made here is the
  same operation as one made on that screen — and every change is written to
  audit_logs against the approver's name.

  The finished PDF is one click away, via "Preview report" in the header.
--}}
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full"
     id="ols-correct"
     data-file-number="{{ $fileNumber }}"
     data-request-id="{{ $searchRequest->id }}">

    @include('admin.header', [
        'PageTitle' => $PageTitle,
        'PageDescription' => 'Review the records for ' . ($fileNumber ?: '—') . ', correct anything wrong, then approve. The requester cannot report a bad result, so it is caught here or not at all.',
    ])

    <div class="flex-1 p-6 space-y-5">
        @if (session('error'))
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        {{-- Who is waiting on this --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">
                            {{ $searchRequest->request_no ?: 'Request' }}
                        </span>
                        <span class="text-sm font-bold text-slate-900">{{ $fileNumber ?: '—' }}</span>
                        @if ($searchRequest->purpose)
                            <span class="text-[11px] text-slate-500">{{ $searchRequest->purpose }}</span>
                        @endif
                    </div>
                    <p class="text-[11px] text-slate-500">
                        {{ $searchRequest->requester_email ?: '—' }}
                        @if ($searchRequest->tracking_id) · <span class="font-mono">{{ $searchRequest->tracking_id }}</span> @endif
                        @if ($searchRequest->submitted_at) · submitted {{ $searchRequest->submitted_at->format('d M Y, H:i') }} @endif
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    {{-- Opens the actual report template — what the requester will be
                         emailed. Kept as the primary action here because this page is
                         where the approver decides the result is right. --}}
                    <a href="{{ route('legal-search-online.admin.requests.preview', $searchRequest->id) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.5c-4 0-7 3-8 5.5 1 2.5 4 5.5 8 5.5s7-3 8-5.5c-1-2.5-4-5.5-8-5.5z"/>
                            <circle cx="12" cy="12" r="2.2"/>
                        </svg>
                        Preview report
                    </a>
                    <a href="{{ route('legal-search-online.admin.requests') }}"
                       class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Back to requests
                    </a>
                </div>
            </div>
        </div>

        @if ($reportError)
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $reportError }}</div>
        @endif

        {{-- Editing toolbar --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 mr-1">Correct records</span>

                <button type="button" id="oc-edit" disabled
                    class="oc-tool rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                    Edit selected
                </button>
                <button type="button" id="oc-add"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                    Add missing record
                </button>
                <button type="button" id="oc-remove" disabled
                    class="oc-tool rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-40 disabled:cursor-not-allowed">
                    Remove selected
                </button>
                <button type="button" id="oc-drop" disabled
                    class="oc-tool rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                    Drop from this file
                </button>

                {{-- Drag-to-reorder. Asks whether to save permanently or just for
                     this browser, because a permanent order rewrites what everyone
                     sees, including the report this requester receives. --}}
                <button type="button" id="ls-arrange-btn"
                    class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300">
                    Arrange
                </button>

                <span class="mx-1 h-5 w-px bg-slate-200"></span>

                <a href="{{ route('legal_search.index', ['file' => $fileNumber]) }}" target="_blank"
                   class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300">
                    Open full timeline
                </a>
                <button type="button" id="oc-refresh"
                    class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-300">
                    Preview updated result
                </button>

                <span id="ls-arrange-status" class="ml-auto text-xs text-slate-400"></span>
                <span id="oc-status" class="text-xs text-slate-400"></span>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">
                Select a record to enable the row actions. Every change is written to the audit
                trail against your name and the time it was made.
            </p>
        </div>

        {{-- The live result --}}
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Current result &mdash; {{ $fileNumber ?: '—' }}</h3>
                    <p class="text-[11px] text-slate-400">Live from the report engine. This is what the approved PDF will contain.</p>
                </div>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                    {{ count($records ?? []) }} {{ \Illuminate\Support\Str::plural('record', count($records ?? [])) }}
                    @if (!empty($report['rows']))
                        <span class="font-normal text-slate-400">/ {{ count($report['rows']) }} printed</span>
                    @endif
                </span>
            </div>
            <div class="max-h-[560px] overflow-auto p-4" id="oc-current">
                @include('system-admin.phs.partials.edit_request_rows', [
                    'report'  => $report,
                    'records' => $records ?? [],
                ])
            </div>
        </div>

        {{-- Finish --}}
        @if ($searchRequest->isPending())
            <div class="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 mb-1">Finished correcting?</h3>
                <p class="text-[11px] text-slate-500 mb-3">
                    Approving generates the report from the records as they stand now and emails it
                    to {{ $searchRequest->requester_email ?: 'the requester' }}.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('legal-search-online.admin.requests', ['highlight' => $searchRequest->id]) }}"
                       class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Back to approve this request
                    </a>
                    <a href="{{ route('legal-search-online.admin.requests.preview', $searchRequest->id) }}" target="_blank"
                       class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Preview report first
                    </a>
                </div>
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs text-slate-500">
                    This request is already <strong>{{ ucfirst($searchRequest->status) }}</strong>.
                    Corrections made here change the records, but the requester keeps the copy that
                    was already sent &mdash; use <strong>Resend report</strong> to deliver the updated one.
                </p>
            </div>
        @endif
    </div>
</div>

{{-- The SAME Edit Record modal and Add Property Record card the Legal Search
     screen uses. Included, not reimplemented. --}}
@include('propertycard.css.style')
@include('legal_search.partials.record_edit_modal')
@include('propertycard.partials.add_property_record')

<script>
  // Config for the shared arrangement partial below.
  window.LS_ARRANGE = {
      propId: @json($propId),
      containerId: 'oc-current',
      rowsSelector: '[data-pc-row]',
  };
</script>
@include('legal_search.partials.timeline_arrange_js')

<script>
  // Scope the shared modal script expects from its host page.
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

  window.__lsLastSearchedFileNumber = @json($fileNumber);

  @include('legal_search.partials.record_edit_modal_js')

  // The partial declares these with const, which in a classic script is a
  // script-scoped binding and NOT a window property. Publish them for the page
  // script below, which runs inside its own IIFE.
  window.openEditModal = openEditModal;
  window.closeEditModal = closeEditModal;
</script>

<script>
(function () {
    const root = document.getElementById('ols-correct');
    if (!root) return;

    const fileNumber = root.dataset.fileNumber || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const statusEl = document.getElementById('oc-status');

    const ROUTES = {
        remove: @json(route('legalsearch.remove')),
        drop:   @json(route('legalsearch.drop')),
    };

    let selected = null;

    const setStatus = (text, tone) => {
        statusEl.textContent = text || '';
        statusEl.className = 'ml-auto text-xs ' + (
            tone === 'error' ? 'text-rose-600' : tone === 'ok' ? 'text-emerald-600' : 'text-slate-400'
        );
    };

    const setToolsEnabled = (on) => {
        document.querySelectorAll('.oc-tool').forEach(b => { b.disabled = !on; });
    };

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
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok || body.success === false) throw new Error(body.message || ('Request failed (' + res.status + ')'));
        return body;
    };

    const requireSelection = () => {
        if (!selected || !selected.id || !selected.table) {
            setStatus('Select a record first.', 'error');
            return false;
        }
        return true;
    };

    document.getElementById('oc-remove')?.addEventListener('click', async () => {
        if (!requireSelection()) return;
        if (!confirm('Remove ' + selected.label + ' from this file?\n\nThis is recorded in the audit trail against your name.')) return;
        try {
            setStatus('Removing...');
            await post(ROUTES.remove, { records: [{ id: selected.id, table: selected.table }], file_number: fileNumber });
            setStatus('Removed. Click "Preview updated result" to refresh.', 'ok');
        } catch (err) { setStatus(err.message, 'error'); }
    });

    document.getElementById('oc-drop')?.addEventListener('click', async () => {
        if (!requireSelection()) return;
        if (!confirm('Drop ' + selected.label + ' from this file?')) return;
        try {
            setStatus('Dropping...');
            await post(ROUTES.drop, { records: [{ id: selected.id, table: selected.table }], file_number: fileNumber });
            setStatus('Dropped. Click "Preview updated result" to refresh.', 'ok');
        } catch (err) { setStatus(err.message, 'error'); }
    });

    document.getElementById('oc-edit')?.addEventListener('click', () => {
        if (!requireSelection()) return;
        if (typeof window.openEditModal === 'function') {
            window.openEditModal(selected.table, parseInt(selected.id, 10));
        } else {
            setStatus('Edit modal did not load on this page.', 'error');
        }
    });

    document.getElementById('oc-add')?.addEventListener('click', () => {
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

    document.getElementById('oc-refresh')?.addEventListener('click', () => window.location.reload());
})();
</script>
@endsection
