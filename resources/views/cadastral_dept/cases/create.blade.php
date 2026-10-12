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
          'key' => $k, 'label' => $t['label'], 'workflows' => $t['workflows'], 'asks' => ! empty($t['asks_sub_type']),
          'tenure' => $t['tenure'] ?? null,
      ])->values();
      $cdSubTypes = $def->subTypes('direct');
      $cdTenures = $def->subTypes('verification');
      $cdOld = old();
    @endphp

    <form method="POST" action="{{ route('cadastral-dept.cases.store') }}" class="space-y-5"
          x-data="cdIntake(@js($cdWorkflows), @js($cdFileTypes), @js($cdOld), '{{ route('cadastral-dept.lookup.file') }}')"
          @submit="if (!canSubmit()) { $event.preventDefault(); }"
          data-confirm-title="Register this file?" data-confirm="The file is registered and sent to the first desk.">
      @csrf

      {{-- 1. Application type --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">1 · Application type</h3>
        <div class="grid gap-3 md:grid-cols-3">
          <template x-for="w in workflows" :key="w.key">
            <label class="cursor-pointer rounded-lg border-2 p-3 block"
                   :class="workflow === w.key ? 'border-' + w.colour + '-500 bg-' + w.colour + '-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" name="workflow" class="sr-only" :value="w.key" x-model="workflow" @change="onWorkflow()">
              <div class="font-bold text-gray-800" x-text="w.label"></div>
              <div class="text-xs text-gray-500 mt-1" x-text="w.applies"></div>
            </label>
          </template>
        </div>
      </section>

      {{-- The route the file will take, for the type picked (Direct: by Resettlement / Direct). --}}
      @php
        $cdEngine = app(\App\Services\CadastralDept\WorkflowEngine::class);
        $cdPipes = [];
        foreach ($def->workflows() as $cdK => $cdW) {
            foreach (($cdW['sub_types'] ?? null) && empty($cdW['asks_sub_type']) ? array_keys($cdW['sub_types']) : [null] as $cdSub) {
                $cdPipes[$cdK . '|' . $cdSub] = [$cdK, $cdSub, $cdW, $cdEngine->pipeline($cdK, $cdSub)];
            }
        }
      @endphp
      @foreach ($cdPipes as $cdKey => [$cdK, $cdSub, $cdW, $cdPipe])
        <section class="rounded-xl border border-gray-200 bg-white px-5 py-4" x-cloak
                 x-show="workflow === @js($cdK) @if ($cdSub) && (subType || @js($cdW['default_sub_type'] ?? '')) === @js($cdSub) @endif">
          <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">
            Process · {{ $cdW['label'] }}@if ($cdSub) ({{ $cdW['sub_types'][$cdSub] }})@endif
          </h3>
          @include('cadastral_dept.partials._pipeline', ['pipeline' => $cdPipe, 'c' => $cdW['colour']])
        </section>
      @endforeach

      {{-- 2. File type, and OP's sub-type --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">2 · File type</h3>
        <div class="flex flex-wrap gap-2">
          <template x-for="t in typesFor()" :key="t.key">
            <label class="cursor-pointer px-3 py-2 rounded-lg border text-sm font-semibold"
                   :class="fileType === t.key ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
              <input type="radio" name="file_type" class="sr-only" :value="t.key" x-model="fileType" @change="onFileType()">
              <span x-text="t.label"></span>
            </label>
          </template>
        </div>

        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3" x-show="asksSubType()" x-cloak>
          <div class="text-sm font-semibold text-blue-900 mb-2">OP file: Resettlement or Direct? <span class="text-red-600">*</span></div>
          <div class="flex flex-wrap gap-4 text-sm">
            @foreach ($cdSubTypes as $k => $label)
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="sub_type" value="{{ $k }}" x-model="subType" :disabled="!asksSubType()"> {{ $label }}
              </label>
            @endforeach
          </div>
          <p class="text-xs text-blue-800 mt-2">Resettlement goes to the Compensation Unit; Direct goes straight to Director Land.</p>
        </div>
        <p class="mt-3 text-xs text-gray-500" x-show="workflow === 'direct' && fileType && !asksSubType()" x-cloak>
          Not an OP file, so it takes the Direct route (straight to Director Land after charting).
        </p>

        {{-- Verification: Statutory or Customary, preselected from the file type, always required. --}}
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3" x-show="workflow === 'verification' && fileType" x-cloak>
          <div class="text-sm font-semibold text-green-900 mb-2">Statutory or Customary? <span class="text-red-600">*</span></div>
          <div class="flex flex-wrap gap-4 text-sm">
            @foreach ($cdTenures as $k => $label)
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="sub_type" value="{{ $k }}" x-model="subType" :disabled="workflow !== 'verification'"> {{ $label }}
              </label>
            @endforeach
          </div>
          <p class="text-xs text-green-800 mt-2">Picked from the file type where it is clear; change it if this file is the other kind.</p>
        </div>
      </section>

      {{-- 3. The file: from the global selector only, never typed --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-1"
            x-text="workflow === 'verification' ? '3 · Search the correspondence file' : '3 · File'"></h3>
        <p class="text-xs text-gray-500 mb-3" x-show="workflow === 'verification'">
          Pick the main file. Its correspondence file is found and matched to it; a file without one cannot be verified.
        </p>

        {{-- Two halves (user, 2026-10-12): the picker on the left, the picked file's brief details on the right. --}}
        <div class="grid gap-5 md:grid-cols-2 items-start">
        <div>
        <div class="flex gap-2">
          <input type="text" :value="fileNumber" disabled placeholder="No file selected"
                 class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono bg-gray-100 text-gray-700">
          <button type="button" @click="pickFile()" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold inline-flex items-center gap-2">
            <i data-lucide="folder-search" class="w-4 h-4"></i> Select File Number
          </button>
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
               :class="result && result.correspondence && result.correspondence.exists ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
               x-text="result && result.correspondence && result.correspondence.exists
                   ? 'Match found: correspondence file ' + (result.correspondence.fileno || 'linked')
                   : 'No correspondence file found for this file.'"></div>
        </div>
        </div>
        </div>
      </section>

      {{-- 4. Where it came from --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">4 · Received</h3>
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

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
  function cdIntake(workflows, fileTypes, old, lookupUrl) {
    return {
      workflows, fileTypes, lookupUrl,
      workflow: old.workflow || '', fileType: old.file_type || '', subType: old.sub_type || '',
      fileSource: old.file_source || '', fileSourceOther: old.file_source_other || '',
      fileNumber: old.file_number || '', fileIndexingId: old.file_indexing_id || '',
      fileSystem: old.file_system || '', trackingId: old.tracking_id || '',
      result: null, loading: false,

      init() {
        // After a failed submit, check the same file again.
        if (this.fileNumber) this.lookup({ file_number: this.fileNumber, tab: this.fileSystem, file_indexing_id: this.fileIndexingId });
      },
      typesFor() { return this.fileTypes.filter(t => t.workflows.includes(this.workflow)); },
      asksSubType() {
        const t = this.fileTypes.find(t => t.key === this.fileType);
        return this.workflow === 'direct' && t && t.asks;
      },
      onWorkflow() {
        if (!this.typesFor().some(t => t.key === this.fileType)) this.fileType = '';
        this.subType = '';
        this.onFileType();
      },
      // Verification: preselect Statutory / Customary from the file type (DCIV / LPCC can be either).
      onFileType() {
        if (this.workflow === 'verification') {
          const t = this.fileTypes.find(t => t.key === this.fileType);
          this.subType = (t && t.tenure) || '';
        } else if (!this.asksSubType()) {
          this.subType = '';
        }
      },
      pickFile() {
        GlobalFileNoModal.open({
          callback: (data) => {
            this.fileSystem = data.system || data.tab || '';
            this.trackingId = data.tracking_id || '';
            this.fileIndexingId = '';
            this.lookup({ file_number: data.fileNumber || '', tab: this.fileSystem });
          }
        });
      },
      choose(id) { this.lookup({ file_number: this.fileNumber, tab: this.fileSystem, file_indexing_id: id }); },
      async lookup(q) {
        this.loading = true; this.result = null;
        this.fileNumber = q.file_number || '';
        try {
          const res = await fetch(this.lookupUrl + '?' + new URLSearchParams(q), { headers: { 'Accept': 'application/json' } });
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
        }
      },
      missing() {
        if (!this.workflow) return 'Choose the application type.';
        if (!this.fileType) return 'Choose the file type.';
        if (this.asksSubType() && !this.subType) return 'Choose Resettlement or Direct.';
        if (this.workflow === 'verification' && !this.subType) return 'Choose Statutory or Customary.';
        if (!this.result || this.result.status !== 'ok') return 'Select a file that can be registered.';
        if (this.workflow === 'verification' && !(this.result.correspondence && this.result.correspondence.exists)) return 'Verification needs a correspondence file.';
        if (!this.fileSource) return 'Choose where the file came from.';
        if (this.fileSource === 'other' && !this.fileSourceOther.trim()) return 'Specify where the file came from.';
        return '';
      },
      canSubmit() { return this.missing() === ''; },
    };
  }
</script>
@endpush
