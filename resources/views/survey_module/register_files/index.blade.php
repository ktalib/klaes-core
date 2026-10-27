{{-- Survey Examination/Verification > Register File, laid out like the Cadastral register (cadastral_dept/cases/index). --}}
@extends('layouts.app')

@section('content')
@php
  use App\Models\Survey\SurveyRegisterFile as RF;

  $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $filters = ['q', 'purpose', 'source', 'status'];
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Register File', 'PageDescription' => 'Files received by Examination/Verification: sent by Cadastral for verification or examination, or registered here'])

  <div class="p-6" x-data="{ st: { open: false, url: '', action: '', ref: '', purpose: '' } }">
    @foreach (['success' => 'green', 'error' => 'red'] as $fk => $fc)
      @if (session($fk))
        <div class="mb-4 rounded-lg border border-{{ $fc }}-200 bg-{{ $fc }}-50 px-4 py-2 text-sm text-{{ $fc }}-900">{{ session($fk) }}</div>
      @endif
    @endforeach
    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-900">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif

    @if ($added)
      <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-900">
        {{ $added }} file(s) received from Cadastral since the register was last opened.
      </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 flex-1">
        <a href="{{ route('survey-module.workflow.register-file.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3">
          <div class="text-xs font-semibold uppercase text-gray-500">All files</div>
          <div class="text-2xl font-bold text-gray-800">{{ number_format($stats['total']) }}</div>
        </a>
        @foreach (RF::STATUSES as $k => $label)
          <a href="{{ route('survey-module.workflow.register-file.index', ['status' => $k]) }}"
             class="rounded-xl border bg-white px-4 py-3 {{ request('status') === $k ? 'border-' . RF::STATUS_COLOURS[$k] . '-400' : 'border-gray-200' }}">
            <div class="text-xs font-semibold uppercase text-{{ RF::STATUS_COLOURS[$k] }}-700">{{ $label }}</div>
            <div class="text-2xl font-bold text-gray-800">{{ number_format($stats[$k]) }}</div>
          </a>
        @endforeach
      </div>
      <a href="{{ route('survey-module.workflow.register-file.create') }}" class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-bold inline-flex items-center gap-2">
        <i data-lucide="file-input" class="w-4 h-4"></i> Register File
      </a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Search</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File no., ref or title" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Purpose</label>
        <select name="purpose" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">All</option>
          @foreach (RF::PURPOSES as $k => $label)
            <option value="{{ $k }}" @selected(request('purpose') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Source</label>
        <select name="source" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">All</option>
          <option value="cadastral" @selected(request('source') === 'cadastral')>From Cadastral</option>
          <option value="survey" @selected(request('source') === 'survey')>Registered in Survey</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">All</option>
          @foreach (RF::STATUSES as $k => $label)
            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <button class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Filter</button>
      @if (request()->hasAny($filters))
        <a href="{{ route('survey-module.workflow.register-file.index') }}" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
      @endif
    </form>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-2">S/N</th><th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Plot No.</th><th class="px-4 py-2">District</th><th class="px-4 py-2">LGA</th>
            <th class="px-4 py-2">Purpose</th><th class="px-4 py-2">Source</th><th class="px-4 py-2">Received</th><th class="px-4 py-2">Status</th>
            <th class="px-4 py-2 text-right"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($files as $f)
            @php $back = $f->backInCadastral(); @endphp
            <tr class="hover:bg-gray-50 align-top">
              <td class="px-4 py-2 text-gray-500">{{ $files->firstItem() + $loop->index }}</td>
              <td class="px-4 py-2 font-mono">{{ $f->register_ref }}</td>
              <td class="px-4 py-2 font-mono">{{ $f->file_number }}</td>
              <td class="px-4 py-2">
                {{ $f->file_title ?: '—' }}
                @if ($f->findingLabel())<div class="text-xs text-gray-600 mt-0.5">Finding: <strong>{{ $f->findingLabel() }}</strong></div>@endif
                @if ($f->notes)
                  <details class="text-xs text-gray-500 mt-0.5"><summary class="cursor-pointer">Notes</summary><div class="whitespace-pre-line">{{ $f->notes }}</div></details>
                @endif
              </td>
              <td class="px-4 py-2">{{ $f->plot_no ?: '—' }}</td>
              <td class="px-4 py-2">{{ $f->district ?: '—' }}</td>
              <td class="px-4 py-2">{{ $f->lga ?: '—' }}</td>
              <td class="px-4 py-2">{!! $chip(RF::PURPOSES[$f->purpose] ?? $f->purpose, $f->purpose === RF::PURPOSE_EXAMINATION ? 'purple' : 'red') !!}</td>
              <td class="px-4 py-2">
                @if ($f->source === RF::SOURCE_CADASTRAL)
                  <div>Cadastral</div>
                  <div class="text-xs text-gray-500">{{ $f->cadastral_case_ref }} · {!! $def->wfChip((string) $f->workflow) !!}</div>
                  @if ($back !== null)
                    <div class="text-xs {{ $back ? 'text-green-700' : 'text-amber-700' }}">{{ $back ? 'Back in Cadastral' : 'Still with Survey' }}</div>
                  @endif
                @else
                  <div>Survey</div>
                @endif
              </td>
              <td class="px-4 py-2 text-gray-500">{{ optional($f->received_at)->format('d M Y H:i') }}</td>
              <td class="px-4 py-2">{!! $chip($f->statusLabel(), RF::STATUS_COLOURS[$f->status] ?? 'gray') !!}</td>
              <td class="px-4 py-2 text-right">
                <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                  <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                          @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                  </button>
                  <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                       :style="'top:' + top + 'px; left:' + left + 'px'"
                       class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                    @php $url = route('survey-module.workflow.register-file.status', $f); @endphp
                    @if ($f->status === RF::RECEIVED)
                      <form method="POST" action="{{ $url }}">@csrf<input type="hidden" name="action" value="start">
                        <button type="submit" class="{{ $svItem }}"><i data-lucide="play" class="w-4 h-4 text-gray-500"></i> Start {{ strtolower(RF::PURPOSES[$f->purpose]) }}</button>
                      </form>
                    @endif
                    @if ($f->status === RF::IN_PROGRESS)
                      <button type="button" class="{{ $svItem }}" @click="open = false; st = { open: true, url: @js($url), action: 'clear', ref: @js($f->register_ref), purpose: @js($f->purpose) }">
                        <i data-lucide="check-circle" class="w-4 h-4 text-gray-500"></i> Clear &amp; send back
                      </button>
                    @endif
                    @unless ($f->isFrozen())
                      <button type="button" class="{{ $svItem }}" @click="open = false; st = { open: true, url: @js($url), action: 'return', ref: @js($f->register_ref), purpose: @js($f->purpose) }">
                        <i data-lucide="undo-2" class="w-4 h-4 text-gray-500"></i> Return with query
                      </button>
                    @endunless
                    @if ($f->finding === 'changes_required')
                      <a href="{{ route('survey-module.workflow.change-reports.create', ['line' => $f->id]) }}" class="{{ $svItem }}">
                        <i data-lucide="file-check" class="w-4 h-4 text-gray-500"></i> Surveyors' Report
                      </a>
                    @endif
                    @if ($f->cadastral_case_id && $canOpenCases)
                      <a href="{{ route('cadastral-dept.cases.show', $f->cadastral_case_id) }}" class="{{ $svItem }}">
                        <i data-lucide="external-link" class="w-4 h-4 text-gray-500"></i> Open Cadastral case
                      </a>
                    @endif
                    @if ($f->isFrozen())
                      <div class="px-3 py-2 text-xs text-gray-500">{{ $f->statusLabel() }} {{ optional($f->closed_at)->format('d M Y') }} · locked</div>
                    @endif
                  </div>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="12" class="px-4 py-6 text-center text-gray-400">
              @if (request()->hasAny($filters))
                No files match these filters.
              @else
                No files yet. Files Cadastral sends for verification or examination appear here; or
                <a href="{{ route('survey-module.workflow.register-file.create') }}" class="text-blue-700 underline">register a file</a>.
              @endif
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $files->links() }}</div>

    {{-- Clear (with the finding) or return (with the reason). --}}
    <div x-show="st.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" style="background:rgba(0,0,0,.4);" @keydown.escape.window="st.open = false">
      <form method="POST" :action="st.url" class="bg-white rounded-xl shadow-xl p-5" style="width:min(480px,92vw);" @click.outside="st.open = false">
        @csrf
        <input type="hidden" name="action" :value="st.action">
        <h3 class="text-base font-bold mb-3"><span x-text="st.ref"></span> · <span x-text="st.action === 'clear' ? 'Clear and send back' : 'Return with query'"></span></h3>
        <template x-if="st.action === 'clear'">
          <div class="mb-3">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Finding <span class="text-red-600">*</span></label>
            @foreach (RF::FINDINGS as $purpose => $options)
              <div x-show="st.purpose === @js($purpose)" class="space-y-1">
                @foreach ($options as $k => $label)
                  <label class="flex items-center gap-2 text-sm"><input type="radio" name="finding" value="{{ $k }}" :disabled="st.purpose !== @js($purpose)" required> {{ $label }}</label>
                @endforeach
              </div>
            @endforeach
          </div>
        </template>
        <label class="block text-sm font-semibold text-gray-700 mb-1">
          <span x-text="st.action === 'return' ? 'Why is the file being returned?' : 'Note (optional)'"></span>
          <span class="text-red-600" x-show="st.action === 'return'">*</span>
        </label>
        <textarea name="note" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" :required="st.action === 'return'"></textarea>
        <p class="text-xs text-gray-500 mt-1">The line is locked once cleared or returned.</p>
        <div class="flex justify-end gap-2 mt-4">
          <button type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm" @click="st.open = false">Cancel</button>
          <button type="submit" class="px-3 py-1.5 rounded-lg bg-green-700 text-white text-sm font-semibold">Confirm</button>
        </div>
      </form>
    </div>
  </div>
  @include('admin.footer')
</div>
@endsection
