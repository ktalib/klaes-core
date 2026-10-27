@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Register File', 'PageDescription' => 'Cadastral Registry: register a main file. It goes straight to D Cad.'])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    @php
      $cdWorkflows = collect($def->workflows())->map(fn ($w, $k) => [
          'key' => $k, 'label' => $w['label'], 'short' => $w['short'], 'colour' => $w['colour'], 'applies' => $w['applies_to'],
      ])->values();
      $cdFileTypes = collect($def->fileTypes())->map(fn ($t, $k) => [
          // tenure / route: fixed answer, null, or 'ask' (route only), per file type (2026-10-20).
          'key' => $k, 'label' => $t['label'], 'workflows' => $t['workflows'],
          'tenure' => $t['tenure'] ?? null, 'route' => $t['route'] ?? null, 'prefixes' => $t['prefixes'] ?? [],
      ])->values();
      $cdSubTypes = $def->subTypes('direct');
      $cdTenures = $def->subTypes('verification');
      $cdOld = old();
    @endphp

    <form method="POST" action="{{ route('cadastral-dept.cases.store') }}" class="space-y-5"
          x-data="cdIntake(@js($cdWorkflows), @js($cdFileTypes), @js($cdOld), '{{ route('cadastral-dept.lookup.file') }}', '{{ route('cadastral-dept.lookup.files') }}')"
          @submit="if (!canSubmit()) { $event.preventDefault(); }"
          data-confirm-title="Register this file?" data-confirm="The file is registered and sent to the first desk.">
      @csrf

      {{-- 1. Application type --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">1 · Application type</h3>
        {{-- Drawn here rather than in an Alpine loop so the lucide icons render; one colour and icon per workflow (config). --}}
        <div class="grid gap-3 md:grid-cols-3">
          @foreach ($def->workflows() as $cdWk => $cdWf)
            @php $cdWc = $cdWf['colour']; @endphp
            <label class="cursor-pointer rounded-lg border-2 p-3 flex items-start gap-3"
                   :class="workflow === @js($cdWk) ? 'border-{{ $cdWc }}-500 bg-{{ $cdWc }}-50' : 'border-gray-200 hover:border-{{ $cdWc }}-300'">
              <input type="radio" name="workflow" class="sr-only" value="{{ $cdWk }}" x-model="workflow" @change="onWorkflow()">
              <span class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full bg-{{ $cdWc }}-100 text-{{ $cdWc }}-700">
                <i data-lucide="{{ $cdWf['icon'] ?? 'folder' }}" class="w-5 h-5"></i>
              </span>
              <span class="min-w-0">
                <span class="block font-bold text-{{ $cdWc }}-800">{{ $cdWf['label'] }}</span>
                <span class="block text-xs text-gray-500 mt-1">{{ $cdWf['applies_to'] }}</span>
              </span>
            </label>
          @endforeach
        </div>
      </section>

      {{-- The route the file will take, for the type picked (Direct: by Resettlement / Direct). --}}
      @php
        $cdEngine = app(\App\Services\CadastralDept\WorkflowEngine::class);
        $cdPipes = [];
        foreach ($def->workflows() as $cdK => $cdW) {
            // One preview per sub-type where the sub-type changes the route (Direct: Resettlement / Direct).
            $cdRoutes = collect($cdW['steps'])->contains(fn ($s) => ($s['route_by'] ?? null) === 'sub_type');
            foreach (($cdW['sub_types'] ?? null) && $cdRoutes ? array_keys($cdW['sub_types']) : [null] as $cdSub) {
                $cdPipes[$cdK . '|' . $cdSub] = [$cdK, $cdSub, $cdW, $cdEngine->pipeline($cdK, $cdSub)];
            }
        }
      @endphp
      @foreach ($cdPipes as $cdKey => [$cdK, $cdSub, $cdW, $cdPipe])
        {{-- Tinted in the workflow's colour (user, 2026-10-15). --}}
        <section class="rounded-xl border border-{{ $cdW['colour'] }}-200 bg-{{ $cdW['colour'] }}-50 px-5 py-4" x-cloak
                 x-show="workflow === @js($cdK) @if ($cdSub) && (subType || @js($cdW['default_sub_type'] ?? '')) === @js($cdSub) @endif">
          <h3 class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-{{ $cdW['colour'] }}-800 mb-3">
            <i data-lucide="{{ $cdW['icon'] ?? 'folder' }}" class="w-4 h-4"></i>Process · {{ $cdW['label'] }}@if ($cdSub) ({{ $cdW['sub_types'][$cdSub] }})@endif
          </h3>
          @include('cadastral_dept.partials._pipeline', ['pipeline' => $cdPipe, 'c' => $cdW['colour']])
        </section>
      @endforeach

      {{-- 2. File type, and Direct Allocation's sub-type. Conversion has one file type, so it is
           picked for the user and the form goes straight to the file (user, 2026-10-20). --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow && (workflow === 'verification' || workflow === 'direct' || typesFor().length > 1)" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">2 · File type</h3>
        {{-- Verification: Conversion or Direct Allocation first, then that group's file types (2026-10-20). --}}
        <div class="mb-4" x-show="workflow === 'verification'" x-cloak>
          <div class="text-sm font-semibold text-gray-800 mb-2">Conversion or Direct Allocation? <span class="text-red-600">*</span></div>
          <div class="flex flex-wrap gap-2">
            @foreach (['conversion', 'direct'] as $cdG)
              <label class="cursor-pointer px-3 py-2 rounded-lg border text-sm font-semibold"
                     :class="vGroup === @js($cdG) ? 'bg-red-700 text-white border-red-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
                <input type="radio" name="verification_group" class="sr-only" value="{{ $cdG }}" x-model="vGroup" @change="onGroup()" :disabled="workflow !== 'verification'">
                {{ $def->workflow($cdG)['label'] }}
              </label>
            @endforeach
          </div>
        </div>
        {{-- Direct Allocation: one Direct (Statutory) tab for DCIV/LPCC, GKN, Misc KN and OP; OP then
             chooses Resettlement or Direct (user, 2026-10-22; OP had its own tab before). --}}
        <div class="mb-4" x-show="workflow === 'direct'" x-cloak>
          <div class="flex flex-wrap gap-2">
            @foreach (['statutory' => 'Direct (Statutory)'] as $cdD => $cdDLabel)
              <label class="cursor-pointer px-3 py-2 rounded-lg border text-sm font-semibold"
                     :class="dGroup === @js($cdD) ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
                <input type="radio" name="direct_group" class="sr-only" value="{{ $cdD }}" x-model="dGroup" @change="onGroup()" :disabled="workflow !== 'direct'">
                {{ $cdDLabel }}
              </label>
            @endforeach
          </div>
        </div>
        <div class="flex flex-wrap gap-2" x-show="(workflow !== 'verification' || vGroup) && (workflow !== 'direct' || dGroup === 'statutory')">
          <template x-for="t in typesFor()" :key="t.key">
            <label class="cursor-pointer px-3 py-2 rounded-lg border text-sm font-semibold"
                   :class="fileType === t.key ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
              <input type="radio" name="file_type" class="sr-only" :value="t.key" x-model="fileType" @change="onFileType()">
              <span x-text="t.label"></span>
            </label>
          </template>
        </div>

        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3" x-show="asksSubType()" x-cloak>
          <div class="text-sm font-semibold text-blue-900 mb-2">Resettlement or Direct? <span class="text-red-600">*</span></div>
          <div class="flex flex-wrap gap-4 text-sm">
            @foreach ($cdSubTypes as $k => $label)
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="sub_type" value="{{ $k }}" x-model="subType" :disabled="!asksSubType()"> {{ $label }}
              </label>
            @endforeach
          </div>
          <p class="text-xs text-blue-800 mt-2">Resettlement goes to the Compensation Unit; Direct goes straight to Director Land.</p>
        </div>

        {{-- Direct Allocation: GKN, Misc KN and DCIV/LPCC are Direct; only OP chooses (2026-10-20). --}}
        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900" x-show="workflow === 'direct' && fileType && !asksSubType()" x-cloak>
          Resettlement or Direct: <strong>Direct</strong> <span class="text-xs text-blue-800">(every <span x-text="ft() ? ft().label : ''"></span> is Direct; only OP files choose)</span>
        </div>

        {{-- Verification: Statutory or Customary and Resettlement or Direct follow the file type; only
             the open ones are asked (DCIV/LPCC: Statutory or Customary; OP: Resettlement or Direct). --}}
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 space-y-3" x-show="workflow === 'verification' && fileType" x-cloak>
          <div x-show="ft() && ft().tenure !== false">
            <div class="text-sm font-semibold text-green-900 mb-1">Statutory or Customary? <span class="text-red-600" x-show="tenureAsks()">*</span></div>
            <div class="flex flex-wrap gap-4 text-sm" x-show="tenureAsks()">
              @foreach ($cdTenures as $k => $label)
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="sub_type" value="{{ $k }}" x-model="subType" :disabled="!tenureAsks()"> {{ $label }}
                </label>
              @endforeach
            </div>
            <div class="text-sm text-green-900" x-show="!tenureAsks()">
              <strong x-text="tenures[ft() ? ft().tenure : ''] || ''"></strong>
              <span class="text-xs text-green-800">(every <span x-text="ft() ? ft().label : ''"></span> is)</span>
            </div>
          </div>
          <div x-show="ft() && ft().route">
            <div class="text-sm font-semibold text-green-900 mb-1">Resettlement or Direct? <span class="text-red-600" x-show="routeAsks()">*</span></div>
            <div class="flex flex-wrap gap-4 text-sm" x-show="routeAsks()">
              @foreach ($cdSubTypes as $k => $label)
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="allocation_route" value="{{ $k }}" x-model="allocationRoute" :disabled="!routeAsks()"> {{ $label }}
                </label>
              @endforeach
            </div>
            <div class="text-sm text-green-900" x-show="!routeAsks()">
              <strong>Direct</strong> <span class="text-xs text-green-800">(every <span x-text="ft() ? ft().label : ''"></span> is)</span>
            </div>
          </div>
        </div>
      </section>

      {{-- 3. The file: picked from the indexed files for the application type, never typed (user, 2026-10-12) --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-1"
            x-text="workflow === 'verification' ? '3 · Search the file' : (workflow === 'conversion' ? '2 · File' : '3 · File')"></h3>
        <p class="text-xs text-gray-500 mb-3" x-show="workflow === 'verification'">
          Pick any indexed file, decommissioned ones included. Its correspondence file is matched when there is one, and the File status checks show below.
        </p>

        {{-- Two halves (user, 2026-10-12): the picker on the left, the picked file's brief details on the right. --}}
        <div class="grid gap-5 md:grid-cols-2 items-start">
        <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1" x-text="listLabel()"></label>
        <div class="relative" @click.outside="listOpen = false" @keydown.escape="listOpen = false">
          <button type="button" @click="toggleList()"
                  class="w-full flex items-center justify-between gap-2 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white text-left">
            <span class="font-mono truncate" :class="fileNumber ? 'text-gray-800' : 'text-gray-400'" x-text="fileNumber || 'Choose a file'"></span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 flex-shrink-0"></i>
          </button>
          <div x-show="listOpen" x-cloak class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg">
            <div class="p-2 border-b border-gray-100">
              <input type="text" x-ref="listSearch" x-model="listTerm" @input.debounce.300ms="loadList()"
                     placeholder="Type the file number or title" class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm">
            </div>
            <div class="max-h-72 overflow-auto">
              <div class="px-3 py-2 text-sm text-gray-500" x-show="listLoading">Loading…</div>
              <div class="px-3 py-2 text-sm text-gray-500" x-show="!listLoading && !listRows.length">No indexed file matches.</div>
              <template x-for="r in listRows" :key="r.id">
                <button type="button" @click="pickRow(r)" class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 border-b border-gray-50">
                  <span class="font-mono font-semibold" x-text="r.file_number"></span>
                  <span class="block text-xs text-gray-500 truncate" x-text="[r.title, r.plot ? 'Plot ' + r.plot : '', r.district, r.lga].filter(Boolean).join(' · ')"></span>
                </button>
              </template>
            </div>
            <div class="px-3 py-1.5 text-xs text-gray-400 border-t border-gray-100" x-show="listRows.length >= 30">Showing the first 30; type to narrow.</div>
          </div>
        </div>
        <input type="hidden" name="file_number" :value="fileNumber">
        <input type="hidden" name="file_indexing_id" :value="fileIndexingId">
        <input type="hidden" name="file_system" :value="fileSystem">
        <input type="hidden" name="tracking_id" :value="trackingId">

        <div class="mt-3 text-sm text-gray-500" x-show="loading">Checking the file…</div>

        <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
             x-show="result && result.status !== 'ok' && result.status !== 'ambiguous'" x-text="result && result.message"></div>

        <div class="mt-3" x-show="result && result.status === 'ambiguous'">
          <div class="text-sm text-gray-700 mb-2" x-text="result && result.message"></div>
          <div class="space-y-2">
            <template x-for="c in (result ? result.candidates : [])" :key="c.id">
              <button type="button" @click="choose(c.id)" class="w-full text-left rounded-lg border border-gray-200 hover:border-blue-400 px-3 py-2 text-sm">
                <span class="font-mono font-semibold" x-text="c.file_number"></span>
                <span class="text-gray-600" x-text="' · ' + (c.owner || 'No owner') + ' · ' + (c.registry || '')"></span>
                <span class="block text-xs text-gray-500" x-text="(c.location || '') + (c.matched_on.length ? ' · matched on ' + c.matched_on.join(', ') : '')"></span>
              </button>
            </template>
          </div>
        </div>
        </div>

        <div>
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-400" x-show="!(result && result.file)">
          The selected file's details show here.
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="result && result.file">
          <div class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <div><span class="text-gray-500">File No.</span> <span class="font-mono font-semibold" x-text="result && result.file && result.file.file_number"></span></div>
            <div><span class="text-gray-500">Record</span> <span x-text="result && result.file && result.file.registry_label"></span></div>
            <div class="sm:col-span-2"><span class="text-gray-500">Title</span> <span x-text="(result && result.file && result.file.title) || '—'"></span></div>
            <div><span class="text-gray-500">Plot No.</span> <span x-text="(result && result.file && result.file.plot) || '—'"></span></div>
            <div><span class="text-gray-500">District</span> <span x-text="(result && result.file && result.file.district) || '—'"></span></div>
            <div><span class="text-gray-500">LGA</span> <span x-text="(result && result.file && result.file.lga) || '—'"></span></div>
            <div><span class="text-gray-500">Location</span> <span x-text="(result && result.file && result.file.location) || '—'"></span></div>
            <div class="sm:col-span-2" x-show="result && result.file && result.file.other_numbers.length">
              <span class="text-gray-500">Also numbered</span> <span class="font-mono" x-text="result && result.file && result.file.other_numbers.join(', ')"></span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <template x-for="f in (result ? result.flags : [])">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="{ok: 'bg-green-100 text-green-800', info: 'bg-blue-100 text-blue-800', warn: 'bg-yellow-100 text-yellow-900', danger: 'bg-red-100 text-red-800'}[f[0]]"
                    x-text="f[1]"></span>
            </template>
          </div>
          <div class="mt-3 rounded-md px-3 py-2 text-sm font-semibold"
               x-show="workflow === 'verification' && result && result.correspondence"
               :class="result && result.correspondence && result.correspondence.exists ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
               x-text="result && result.correspondence && result.correspondence.exists
                   ? 'Match found: correspondence file ' + (result.correspondence.fileno || 'linked')
                   : 'No correspondence file for this file. It can still be verified.'"></div>
        </div>
        </div>
        </div>

        {{-- Verification: which File Status lists the picked file is on (RofO, CofO, ... Mortgage). --}}
        <div class="mt-4 rounded-lg border border-gray-200 px-4 py-3" x-show="workflow === 'verification' && (statusLoading || statusHtml)" x-cloak>
          <div class="text-sm text-gray-400" x-show="statusLoading">Checking RofO, CofO, decommissioning, duplicates, Re-grant, title status, DCIV, caveat and mortgage…</div>
          <div x-show="!statusLoading" x-html="statusHtml" x-ref="statusBox"></div>
        </div>
      </section>

      {{-- 4. Where it came from --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3" x-text="workflow === 'conversion' ? '3 · Received' : '4 · Received'">4 · Received</h3>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">File source <span class="text-red-600">*</span></label>
            <select name="file_source" x-model="fileSource" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="">Where did the file come from?</option>
              @foreach ($def->fileSources() as $k => $src)
                <option value="{{ $k }}">{{ $src['label'] }}</option>
              @endforeach
            </select>
            <div class="mt-2" x-show="fileSource === 'other'" x-cloak>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Specify <span class="text-red-600">*</span></label>
              <input type="text" name="file_source_other" x-model="fileSourceOther" maxlength="100" :disabled="fileSource !== 'other'" :required="fileSource === 'other'"
                     placeholder="Where did the file come from?" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <p class="text-xs text-gray-500 mt-1">"Not recommended" sends the file back here.</p>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Date &amp; time received</label>
            <input type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          </div>
        </div>
        <div class="mt-4">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Remarks</label>
          <textarea name="remarks" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
        </div>
      </section>

      <div class="flex items-center justify-end gap-3" x-show="workflow" x-cloak>
        <span class="text-xs text-gray-500" x-show="!canSubmit()" x-text="missing()"></span>
        <button type="submit" :disabled="!canSubmit() || {{ $installed ? 'false' : 'true' }}"
                class="px-5 py-2 rounded-lg bg-green-700 text-white text-sm font-bold disabled:opacity-50 inline-flex items-center gap-2">
          <i data-lucide="send" class="w-4 h-4"></i> Register and send to D Cad
        </button>
      </div>
    </form>
  </div>
  @include('admin.footer')
</div>

@endsection

@push('scripts')
<script>
  function cdIntake(workflows, fileTypes, old, lookupUrl, listUrl) {
    old = old || {};
    return {
      workflows, fileTypes, lookupUrl, listUrl,
      listOpen: false, listTerm: '', listRows: [], listLoading: false, listFor: null,
      workflow: old.workflow || '', fileType: old.file_type || '', subType: old.sub_type || '',
      allocationRoute: old.allocation_route || '', vGroup: old.verification_group || '', dGroup: old.direct_group ? 'statutory' : '', tenures: @js($cdTenures),
      fileSource: old.file_source || '', fileSourceOther: old.file_source_other || '',
      fileNumber: old.file_number || '', fileIndexingId: old.file_indexing_id || '',
      fileSystem: old.file_system || '', trackingId: old.tracking_id || '',
      result: null, loading: false,
      statusUrl: @js(route('cadastral-dept.lookup.file-status')), statusHtml: '', statusLoading: false, statusFor: '',

      init() {
        // After a failed submit, check the same file again.
        if (this.fileNumber) this.lookup({ file_number: this.fileNumber, tab: this.fileSystem, file_indexing_id: this.fileIndexingId });
      },
      // Verification lists the chosen group's file types (Conversion or Direct Allocation).
      typesFor() {
        if (this.workflow === 'verification') {
          return this.vGroup ? this.fileTypes.filter(t => t.workflows.includes('verification') && t.workflows.includes(this.vGroup)) : [];
        }
        if (this.workflow === 'direct') {
          if (!this.dGroup) return [];
          // Direct (Statutory): every Direct type, OP included.
          return this.fileTypes.filter(t => t.workflows.includes('direct'));
        }
        return this.fileTypes.filter(t => t.workflows.includes(this.workflow));
      },
      ft() { return this.fileTypes.find(t => t.key === this.fileType) || null; },
      // Direct Allocation: only a file type whose route is open (OP) chooses Resettlement or Direct.
      asksSubType() { return this.workflow === 'direct' && !!this.ft() && this.ft().route === 'ask'; },
      // Verification: asked only where the file type leaves it open (2026-10-20).
      tenureAsks() { return this.workflow === 'verification' && !!this.ft() && this.ft().tenure === null; },
      routeAsks() { return this.workflow === 'verification' && !!this.ft() && this.ft().route === 'ask'; },
      // The list depends on the type and, for Verification, its group.
      // The file type is part of the key: ST / SLTR / Conversion File list their own numbers (2026-10-27).
      listKey() { return this.workflow + '|' + (this.workflow === 'verification' ? this.vGroup : '') + '|' + (this.fileType || ''); },
      onGroup() {
        if (!this.typesFor().some(t => t.key === this.fileType)) this.fileType = '';
        // Verification: a file picked under the other group goes (user, 2026-10-26).
        if (this.workflow === 'verification' && this.fileNumber && !this.groupOk()) this.clearFile();
        this.pickOnlyType();
        this.onFileType();
      },
      // One file type on offer (Conversion): pick it.
      pickOnlyType() {
        const types = this.typesFor();
        if (types.length === 1) this.fileType = types[0].key;
      },
      onWorkflow() {
        if (!this.typesFor().some(t => t.key === this.fileType)) this.fileType = '';
        this.subType = ''; this.allocationRoute = '';
        if (this.workflow !== 'verification') this.vGroup = '';
        if (this.workflow !== 'direct') this.dGroup = '';
        this.pickOnlyType();
        this.onFileType();
        // Each type lists different files, so the previous pick goes.
        this.clearFile();
      },
      clearFile() {
        this.fileNumber = ''; this.fileIndexingId = ''; this.trackingId = ''; this.fileSystem = '';
        this.result = null; this.listRows = []; this.listTerm = ''; this.listFor = null; this.listOpen = false;
        this.statusHtml = ''; this.statusFor = '';
      },
      listLabel() {
        const own = (this.ft() && this.ft().prefixes) || [];
        if (own.length && this.workflow !== 'verification') return 'Indexed ' + this.ft().label + ' (' + own.join(' / ') + ')';
        const labels = { conversion: 'Indexed Conversion file (CON-)', direct: 'Indexed Direct Allocation file (RES- / COM- / IND- / AG-)' };
        // Verification lists the chosen group's files (user, 2026-10-26).
        if (this.workflow === 'verification') return this.vGroup ? labels[this.vGroup] : 'Choose Conversion or Direct Allocation first';
        return labels[this.workflow] || 'File';
      },
      toggleList() {
        this.listOpen = !this.listOpen;
        if (!this.listOpen) return;
        if (this.listFor !== this.listKey()) this.loadList();
        this.$nextTick(() => this.$refs.listSearch && this.$refs.listSearch.focus());
      },
      async loadList() {
        this.listLoading = true; this.listFor = this.listKey();
        try {
          const res = await fetch(this.listUrl + '?' + new URLSearchParams({ workflow: this.workflow, group: this.workflow === 'verification' ? this.vGroup : '', file_type: this.fileType || '', q: this.listTerm }), { headers: { 'Accept': 'application/json' } });
          this.listRows = await res.json();
        } catch (e) {
          this.listRows = [];
        }
        this.listLoading = false;
      },
      pickRow(r) {
        this.listOpen = false;
        this.fileSystem = '';
        this.trackingId = r.tracking_id || '';
        this.fileIndexingId = r.id;
        this.lookup({ file_number: r.file_number, file_indexing_id: r.id });
      },
      // The fixed answers come from the file type; open ones start blank (2026-10-20).
      onFileType() {
        const t = this.ft();
        if (this.workflow === 'verification') {
          this.subType = (t && t.tenure) || '';
          this.allocationRoute = t && t.route && t.route !== 'ask' ? t.route : '';
        } else if (this.workflow === 'direct') {
          this.subType = t && t.route === 'ask' ? '' : 'direct_allocation';
        } else {
          this.subType = '';
        }
      },
      choose(id) { this.lookup({ file_number: this.fileNumber, tab: this.fileSystem, file_indexing_id: id }); },
      async lookup(q) {
        this.loading = true; this.result = null; this.statusHtml = ''; this.statusFor = '';
        this.fileNumber = q.file_number || '';
        try {
          const res = await fetch(this.lookupUrl + '?' + new URLSearchParams({ ...q, workflow: this.workflow }), { headers: { 'Accept': 'application/json' } });
          this.result = await res.json();
        } catch (e) {
          this.result = { status: 'error', message: 'Could not check the file. Try again.' };
        }
        this.loading = false;
        const f = this.result && this.result.file;
        if (this.result.status === 'ok' && f) {
          this.fileNumber = f.file_number;
          this.fileIndexingId = f.file_indexing_id || '';
          if (!this.fileSource && f.file_source) this.fileSource = f.file_source;
          if (this.workflow === 'verification') this.loadStatus();
        }
      },
      // The File Status checks for the picked file (read only; the panel is the case page's).
      async loadStatus() {
        const key = this.fileNumber + '|' + this.fileIndexingId;
        this.statusFor = key; this.statusLoading = true; this.statusHtml = '';
        let html;
        try {
          const res = await fetch(this.statusUrl + '?' + new URLSearchParams({ file_number: this.fileNumber, file_indexing_id: this.fileIndexingId || '' }),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
          if (!res.ok) throw new Error(res.status);
          html = await res.text();
        } catch (e) {
          html = '<p class="text-sm text-yellow-800">Could not load the file status checks. Pick the file again to retry.</p>';
        }
        if (this.statusFor !== key) return; // another file was picked meanwhile
        this.statusHtml = html; this.statusLoading = false;
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      },
      missing() {
        if (!this.workflow) return 'Choose the application type.';
        if (this.workflow === 'verification' && !this.vGroup) return 'Choose Conversion or Direct Allocation.';
        if (this.workflow === 'direct' && !this.dGroup) return 'Choose Direct (Statutory).';
        if (!this.fileType) return 'Choose the file type.';
        if (this.asksSubType() && !this.subType) return 'Choose Resettlement or Direct.';
        if (this.tenureAsks() && !this.subType) return 'Choose Statutory or Customary.';
        if (this.routeAsks() && !this.allocationRoute) return 'Choose Resettlement or Direct.';
        if (!this.result || this.result.status !== 'ok') return 'Select a file that can be registered.';
        if (!this.groupOk()) return this.fileNumber + ' is not a ' + (this.vGroup === 'conversion' ? 'Conversion' : 'Direct Allocation') + ' file.';
        if (!this.prefixOk()) return this.fileNumber + ' is not a ' + this.ft().label + ': its number must start with ' + this.ft().prefixes.join(' or ') + '.';
        if (!this.fileSource) return 'Choose where the file came from.';
        if (this.fileSource === 'other' && !this.fileSourceOther.trim()) return 'Specify where the file came from.';
        return '';
      },
      // A file type with fixed prefixes (Conversion File: CON-) takes only those numbers.
      prefixOk() {
        const p = (this.ft() && this.ft().prefixes) || [];
        const n = (this.fileNumber || '').trim().toUpperCase();
        return !p.length || !n || p.some(x => n.startsWith(x.toUpperCase()));
      },
      // Verification > Conversion takes CON- files; > Direct Allocation RES- / COM- / IND- / AG-.
      groupOk() {
        if (this.workflow !== 'verification' || !this.vGroup) return true;
        if (this.ft() && (this.ft().prefixes || []).length) return true;   // ST / SLTR: in both groups, checked by prefixOk()
        const p = { conversion: ['CON-'], direct: ['RES-', 'COM-', 'IND-', 'AG-'] }[this.vGroup] || [];
        const n = (this.fileNumber || '').trim().toUpperCase();
        return !n || p.some(x => n.startsWith(x));
      },
      canSubmit() { return this.missing() === ''; },
    };
  }
</script>
@endpush
