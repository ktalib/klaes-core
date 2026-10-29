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

    {{-- The last batch's results (Batch mode), one line per file. --}}
    @if (!empty($batchResults))
      <section class="rounded-xl border border-gray-200 bg-white p-5 mb-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Last batch</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">File No.</th><th class="px-3 py-2">Result</th><th class="px-3 py-2">Note</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ($batchResults as $r)
                <tr>
                  <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                  <td class="px-3 py-2 font-mono">{{ $r['file_number'] }}</td>
                  <td class="px-3 py-2">
                    @if ($r['ok'])
                      <a href="{{ $r['url'] }}" class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800 hover:underline">{{ $r['ref'] }} · {{ $r['desk'] }}</a>
                    @else
                      <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800">Not registered</span>
                    @endif
                  </td>
                  <td class="px-3 py-2 text-gray-600">{{ $r['message'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    <form method="POST" action="{{ route('cadastral-dept.cases.store') }}" :action="batchMode ? batchUrl : singleUrl" class="space-y-5"
          x-data="cdIntake(@js($cdWorkflows), @js($cdFileTypes), @js($cdOld), '{{ route('cadastral-dept.lookup.file') }}', '{{ route('cadastral-dept.lookup.files') }}')"
          x-ref="intakeForm"
          @submit="if (!canSubmit()) { $event.preventDefault(); } else if (batchMode) { $event.preventDefault(); confirmBatch(); }"
          data-confirm-title="Register this file?" data-confirm="The file is registered and sent to the first desk."
          :data-confirm-title="batchMode ? 'Register this batch?' : 'Register this file?'"
          :data-confirm="batchMode ? 'Every file in the batch is registered and sent to the first desk.' : 'The file is registered and sent to the first desk.'">
      @csrf

      {{-- Batch mode (user, 2026-10-27): many files of ONE application type and file type. A scanned
           file is added to the batch at once; a file picked from the list has "Add to batch". Apply,
           fill the Received section once, and register them all (CaseController::batchStore). --}}
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
           :class="batchMode ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'">
        <div class="text-sm text-gray-700 flex items-center gap-2">
          <i data-lucide="layers" class="w-4 h-4 text-green-700"></i>
          <strong>Batch mode</strong>
          <span x-text="batchMode ? 'On: scanned files go straight into the batch; a file picked from the list has Add to batch.' : 'Off: one file at a time. Switch on to register many files of one type together.'"></span>
        </div>
        <button type="button" @click="toggleBatch()" role="switch" :aria-checked="batchMode ? 'true' : 'false'" aria-label="Batch mode"
                class="relative inline-flex flex-shrink-0 h-6 w-11 rounded-full transition-colors" :class="batchMode ? 'bg-green-600' : 'bg-gray-300'">
          <span class="inline-block h-5 w-5 mt-0.5 bg-white rounded-full shadow transform transition" :class="batchMode ? 'translate-x-5' : 'translate-x-1'"></span>
        </button>
      </div>

      {{-- Scan to register (backlog, 2026-10-27): works from any tab. The scan finds the file, says
           if it is already in the department, and asks Conversion / Direct / Verification with the
           one its number suggests marked; the choice fills the form below. --}}
      <section class="rounded-xl border border-indigo-200 bg-indigo-50 p-5">
        {{-- Two halves (user, 2026-10-27): the scanner on the left, the scanned file's details on the right. --}}
        <div class="grid gap-5 md:grid-cols-2 items-start">
        <div>
          <h3 class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-indigo-800 mb-3">
            <i data-lucide="scan-line" class="w-4 h-4"></i> Scan QR code
          </h3>
          <input type="text" x-ref="scanBox" x-model="scanCode" autocomplete="off"
                 @keydown.enter.prevent="runScan()"
                 placeholder="Scan the tracking sheet or label QR, or type the tracking ID / file number, then Enter"
                 class="w-full border border-indigo-300 rounded-lg px-3 py-2 text-sm bg-white">
          <div class="flex flex-wrap gap-2 mt-2">
            <button type="button" @click="runScan()" :disabled="scanBusy || !scanCode.trim()"
                    class="px-4 py-2 rounded-lg bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50 inline-flex items-center gap-2">
              <i data-lucide="search" class="w-4 h-4"></i> Find
            </button>
            <button type="button" @click="openCamera()"
                    class="px-4 py-2 rounded-lg border border-indigo-300 bg-white text-indigo-800 text-sm font-semibold inline-flex items-center gap-2">
              <i data-lucide="camera" class="w-4 h-4"></i> Camera
            </button>
          </div>
          <div class="mt-3 text-sm text-indigo-800" x-show="scanBusy" x-cloak>Looking the file up…</div>
          <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-show="scan && !scan.resolved" x-cloak x-text="scan && scan.message"></div>
          <div class="mt-3 text-sm text-indigo-900" x-show="scan && scan.resolved && scan.matches.length > 1" x-cloak x-text="scan && scan.message"></div>
          <p class="text-xs text-indigo-800 mt-3">A USB scanner types straight into the box. No QR? Choose the application type below and pick the file from the list.</p>
        </div>

        <div>
          <div class="rounded-lg border border-dashed border-indigo-300 bg-white px-4 py-6 text-center text-sm text-gray-400" x-show="!(scan && scan.resolved)">
            The scanned file's details show here.
          </div>
          <div class="space-y-2" x-show="scan && scan.resolved" x-cloak>
            <template x-for="m in (scan ? scan.matches : [])" :key="m.file_number">
              <div class="rounded-lg border bg-white p-4" :class="m.open_case ? 'border-yellow-300' : 'border-indigo-200'">
                <div class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                  <div><span class="text-gray-500">File No.</span> <span class="font-mono font-semibold" x-text="m.file_number"></span></div>
                  <div class="sm:col-span-2"><span class="text-gray-500">Title</span> <span class="uppercase" x-text="m.title || '—'"></span></div>
                  <div><span class="text-gray-500">Plot No.</span> <span class="uppercase" x-text="(m.details && m.details.plot) || '—'"></span></div>
                  <div><span class="text-gray-500">District</span> <span class="uppercase" x-text="(m.details && m.details.district) || '—'"></span></div>
                  <div><span class="text-gray-500">LGA</span> <span class="uppercase" x-text="(m.details && m.details.lga) || '—'"></span></div>
                  <div><span class="text-gray-500">Location</span> <span class="uppercase" x-text="(m.details && m.details.location) || '—'"></span></div>
                </div>
                <div class="flex flex-wrap gap-2 mt-2">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold" x-show="m.details"
                        :class="m.details && m.details.csf && m.details.csf.source === 'indexing' ? 'bg-red-600 text-white' : (m.details && m.details.correspondence ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800')"
                        x-text="m.details && m.details.csf && m.details.csf.source === 'indexing' ? 'KLAES Matched' : (m.details && m.details.correspondence ? 'Correspondence file: ' + m.details.correspondence : 'No correspondence file yet: made at registration')"></span>
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-900" x-show="!m.indexed">Not indexed</span>
                </div>
                {{-- Already has a correspondence file (user, 2026-10-27): show it, and tell the officer to verify. --}}
                <template x-if="m.details && m.details.csf">
                  <div class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide text-green-800 mb-1.5">
                      <span class="inline-flex items-center gap-1.5"><i data-lucide="folder-check" class="w-4 h-4"></i> Correspondence file</span>
                      <span x-show="m.details.csf.source === 'indexing'" class="px-2 py-0.5 rounded-full bg-red-600 text-white normal-case tracking-normal" title="Matched through file indexing; no correspondence (CSF) record">KLAES Matched</span>
                    </div>
                    <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                      <div x-show="m.details.csf.source !== 'indexing'"><span class="text-gray-500">CSF Ref.</span> <span class="font-mono font-semibold" x-text="m.details.csf.ref || '—'"></span></div>
                      <div><span class="text-gray-500">File No.</span> <span class="font-mono" x-text="m.details.csf.file_number || m.file_number"></span></div>
                      <div class="sm:col-span-2"><span class="text-gray-500">Name</span> <span class="uppercase" x-text="m.details.csf.name || m.title || '—'"></span></div>
                      <div><span class="text-gray-500">Plot No.</span> <span class="uppercase" x-text="m.details.csf.plot || m.details.plot || '—'"></span></div>
                      <div><span class="text-gray-500">LGA</span> <span class="uppercase" x-text="m.details.csf.lga || m.details.lga || '—'"></span></div>
                      <div><span class="text-gray-500">Location</span> <span class="uppercase" x-text="m.details.csf.location || m.details.location || '—'"></span></div>
                      <div><span class="text-gray-500">Matched</span> <span x-text="m.details.csf.matched || '—'"></span></div>
                    </div>
                  </div>
                </template>
                <div class="mt-2 flex flex-wrap items-center gap-3 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-900" x-show="m.proceed_verification && !m.open_case">
                  <i data-lucide="alert-triangle" class="w-4 h-4 flex-shrink-0"></i>
                  <span class="flex-1"><strong>This file already has a correspondence file.</strong> Proceed with Verification.</span>
                  <button type="button" @click="applyScan(m, 'verification')" class="px-3 py-1.5 rounded-lg bg-red-700 text-white font-semibold">Proceed with Verification</button>
                </div>
                {{-- Already in the department: open its case instead of registering it again. --}}
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-yellow-900" x-show="m.open_case">
                  <span x-text="m.open_case ? 'Already registered as ' + m.open_case.ref + ', now with ' + m.open_case.desk + ' (' + m.open_case.step + ').' : ''"></span>
                  <a :href="m.open_case ? m.open_case.url : '#'" class="px-3 py-1.5 rounded-lg bg-yellow-600 text-white font-semibold inline-flex items-center gap-1.5">
                    <i data-lucide="folder-open" class="w-4 h-4"></i> Open the case
                  </a>
                </div>
                <div class="mt-3" x-show="!m.open_case">
                  <div class="text-xs text-gray-600 mb-1.5" x-text="batchMode ? 'Start the batch as (every file in it will be this type):' : 'Register it as:'"></div>
                  <div class="flex flex-wrap gap-2">
                    <template x-for="w in workflows" :key="w.key">
                      <button type="button" @click="applyScan(m, w.key)" :disabled="!m.workflows.includes(w.key)"
                              class="px-3 py-1.5 rounded-lg border text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                              :class="m.suggested === w.key ? 'bg-indigo-700 text-white border-indigo-700' : 'bg-white text-gray-800 border-gray-300 hover:bg-gray-50'"
                              :title="m.workflows.includes(w.key) ? '' : m.file_number + ' cannot be registered as ' + w.label">
                        <span x-text="w.label"></span><span class="text-xs font-normal" x-show="m.suggested === w.key"> · detected</span>
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
        </div>
      </section>

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
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow" x-cloak data-cd-file-section>
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
                  <span class="block text-xs text-gray-500 truncate uppercase" x-text="[r.title, r.plot ? 'Plot ' + r.plot : '', r.district, r.lga].filter(Boolean).join(' · ')"></span>
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
            <div><span class="text-gray-500">Record</span> <span class="uppercase" x-text="result && result.file && result.file.registry_label"></span></div>
            <div class="sm:col-span-2"><span class="text-gray-500">Title</span> <span class="uppercase" x-text="(result && result.file && result.file.title) || '—'"></span></div>
            <div><span class="text-gray-500">Plot No.</span> <span class="uppercase" x-text="(result && result.file && result.file.plot) || '—'"></span></div>
            <div><span class="text-gray-500">District</span> <span class="uppercase" x-text="(result && result.file && result.file.district) || '—'"></span></div>
            <div><span class="text-gray-500">LGA</span> <span class="uppercase" x-text="(result && result.file && result.file.lga) || '—'"></span></div>
            <div><span class="text-gray-500">Location</span> <span class="uppercase" x-text="(result && result.file && result.file.location) || '—'"></span></div>
            <div class="sm:col-span-2" x-show="result && result.file && result.file.other_numbers.length">
              <span class="text-gray-500">Also numbered</span> <span class="font-mono" x-text="result && result.file && result.file.other_numbers.join(', ')"></span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <template x-for="f in (result ? result.flags : [])">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="{ok: 'bg-green-100 text-green-800', info: 'bg-blue-100 text-blue-800', warn: 'bg-yellow-100 text-yellow-900', danger: 'bg-red-600 text-white'}[f[0]]"
                    x-text="f[1]"></span>
            </template>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-900"
               x-show="!batchMode && workflow !== 'verification' && result && result.status === 'ok' && result.correspondence && result.correspondence.exists" x-cloak>
            <span class="flex-1"><strong>This file already has a correspondence file</strong> <span class="font-mono" x-text="result && result.correspondence ? '(' + (result.correspondence.shadow || result.correspondence.fileno || '') + ')' : ''"></span>. Proceed with Verification.</span>
            <button type="button" @click="proceedVerification()" class="px-3 py-1.5 rounded-lg bg-red-700 text-white font-semibold">Proceed with Verification</button>
          </div>
          <div class="mt-3" x-show="batchMode && result && result.status === 'ok'" x-cloak>
            <button type="button" @click="addPickedToBatch()" class="px-3 py-1.5 rounded-lg bg-green-700 text-white text-sm font-semibold inline-flex items-center gap-1.5">
              <i data-lucide="plus" class="w-4 h-4"></i> Add to batch
            </button>
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

      {{-- Batch mode: the files gathered so far, each checked as it was added. --}}
      <section class="rounded-xl border border-green-200 bg-white p-5" x-show="batchMode" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h3 class="text-xs font-bold uppercase tracking-wide text-green-800">
            Batch <span class="normal-case font-normal" x-text="'(' + batchOkCount() + ' ready of ' + batch.length + '; at most ' + batchMax + ')'"></span>
          </h3>
          <div class="text-xs text-gray-600" x-show="batch.length" x-text="'Type: ' + batchTypeLabel()"></div>
        </div>
        {{-- Registering: one file at a time, "3 of 12" (user, 2026-10-27). --}}
        <div class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3" x-show="batchRunning || batchFinished" x-cloak>
          <div class="flex flex-wrap items-center justify-between gap-2 text-sm font-semibold text-green-900">
            <span x-show="batchRunning" x-text="'Registering ' + Math.min(batchDone + 1, batchTotal) + ' of ' + batchTotal + (batchCurrent ? ': ' + batchCurrent : '') + '…'"></span>
            <span x-show="!batchRunning && batchFinished" x-text="'Done: ' + batchRegistered() + ' of ' + batchTotal + ' registered' + (batchTotal - batchRegistered() ? ', ' + (batchTotal - batchRegistered()) + ' not registered (see the list).' : '.')"></span>
            <span class="text-xs font-normal" x-show="batchRunning">Keep this page open until it finishes.</span>
          </div>
          <div class="mt-2 h-2 w-full rounded-full bg-green-100 overflow-hidden">
            <div class="h-2 rounded-full bg-green-600 transition-all" :style="'width:' + (batchTotal ? Math.round(batchDone * 100 / batchTotal) : 0) + '%'"></div>
          </div>
          <div class="mt-3 flex flex-wrap gap-3" x-show="!batchRunning && batchFinished">
            <button type="button" @click="newBatch()" class="px-3 py-1.5 rounded-lg bg-green-700 text-white text-sm font-semibold inline-flex items-center gap-1.5"><i data-lucide="plus" class="w-4 h-4"></i> Start a new batch</button>
            <a href="{{ route('cadastral-dept.desks.show', 'registry') }}" class="px-3 py-1.5 rounded-lg border border-green-300 bg-white text-green-800 text-sm font-semibold">Go to Registry</a>
          </div>
        </div>
        <div class="text-sm text-green-800" x-show="batchBusy" x-cloak>Checking the file…</div>
        <div class="mb-3 rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-900" x-show="batchNotice" x-cloak x-text="batchNotice"></div>
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-400" x-show="!batch.length">
          Scan files, or pick a file above and click Add to batch.
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-200" x-show="batch.length" x-cloak>
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">File No.</th><th class="px-3 py-2">Title</th><th class="px-3 py-2">Plot No.</th><th class="px-3 py-2">District</th><th class="px-3 py-2">LGA</th><th class="px-3 py-2">Check</th><th class="px-3 py-2 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template x-for="(r, i) in batch" :key="r.file_number">
                <tr :class="r.ok ? '' : 'bg-red-50'">
                  <td class="px-3 py-2 text-gray-500" x-text="i + 1"></td>
                  <td class="px-3 py-2 font-mono font-semibold" x-text="r.file_number"></td>
                  <td class="px-3 py-2 uppercase" x-text="r.title || '—'"></td>
                  <td class="px-3 py-2 uppercase" x-text="r.plot || '—'"></td>
                  <td class="px-3 py-2 uppercase" x-text="r.district || '—'"></td>
                  <td class="px-3 py-2 uppercase" x-text="r.lga || '—'"></td>
                  <td class="px-3 py-2">
                    <template x-if="!r.result">
                      <div>
                        <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold" :class="r.ok ? (batchRunning && batchCurrent === r.file_number ? 'bg-yellow-100 text-yellow-900' : 'bg-green-100 text-green-800') : 'bg-red-100 text-red-800'"
                              x-text="r.ok ? (batchRunning && batchCurrent === r.file_number ? 'Registering…' : 'Ready') : 'Will be skipped'"></span>
                        <div class="text-xs mt-0.5" :class="r.ok ? 'text-gray-500' : 'text-red-700'" x-text="r.note"></div>
                      </div>
                    </template>
                    <template x-if="r.result">
                      <div>
                        <a x-show="r.result.ok" :href="r.result.url || '#'" class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-green-600 text-white hover:underline" x-text="'Registered ' + (r.result.ref || '')"></a>
                        <span x-show="!r.result.ok" class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800">Not registered</span>
                        <div class="text-xs mt-0.5" :class="r.result.ok ? 'text-gray-500' : 'text-red-700'" x-text="r.result.message || ''"></div>
                      </div>
                    </template>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false" x-show="!batchApplied && !batchRunning && !batchFinished">
                      <button type="button" @click="open = !open" aria-label="Actions" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>
                      <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
                        <button type="button" @click="removeFromBatch(i); open = false" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-700 hover:bg-red-50"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove from batch</button>
                      </div>
                    </div>
                    <template x-if="batchApplied && r.ok">
                      <span>
                        <input type="hidden" :name="'files[' + i + '][file_number]'" :value="r.file_number">
                        <input type="hidden" :name="'files[' + i + '][file_indexing_id]'" :value="r.file_indexing_id || ''">
                        <input type="hidden" :name="'files[' + i + '][tracking_id]'" :value="r.tracking_id || ''">
                      </span>
                    </template>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-end gap-3" x-show="batch.length" x-cloak>
          <button type="button" @click="clearBatch()" x-show="!batchApplied" class="text-sm text-gray-600 hover:underline">Clear the batch</button>
          <button type="button" @click="batchApplied = false" x-show="batchApplied && !batchRunning && !batchFinished" class="text-sm text-gray-600 hover:underline">Edit the batch</button>
          <button type="button" @click="applyBatch()" x-show="!batchApplied" :disabled="!batchOkCount()"
                  class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold disabled:opacity-50 inline-flex items-center gap-1.5">
            <i data-lucide="check" class="w-4 h-4"></i> Apply
          </button>
        </div>
      </section>

      {{-- 4. Where it came from --}}
      <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="workflow && (!batchMode || (batchApplied && !batchFinished))" x-cloak>
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3" x-text="(workflow === 'conversion' ? '3 · Received' : '4 · Received') + (batchMode ? ' (applies to every file in the batch)' : '')">4 · Received</h3>
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

      <div class="flex items-center justify-end gap-3" x-show="workflow && (!batchMode || (batchApplied && !batchFinished))" x-cloak>
        <span class="text-xs text-gray-500" x-show="!canSubmit()" x-text="missing()"></span>
        <button type="submit" :disabled="!canSubmit() || batchRunning || {{ $installed ? 'false' : 'true' }}"
                class="px-5 py-2 rounded-lg bg-green-700 text-white text-sm font-bold disabled:opacity-50 inline-flex items-center gap-2">
          <i data-lucide="send" class="w-4 h-4"></i> <span x-text="batchMode ? 'Register ' + batchOkCount() + ' file(s) and send to D Cad' : 'Register and send to D Cad'">Register and send to D Cad</span>
        </button>
      </div>
    </form>
  </div>
  @include('admin.footer')
</div>
@include('cadastral_dept.partials._qr_scan')

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
      scanUrl: @js(route('cadastral-dept.lookup.qr')), scanCode: '', scanBusy: false, scan: null,
      // Batch mode (2026-10-27): one application type and file type, many files.
      singleUrl: @js(route('cadastral-dept.cases.store')), batchUrl: @js(route('cadastral-dept.cases.batch.store')),
      batchMode: false, batch: [], batchApplied: false, batchBusy: false, batchNotice: '', batchSnap: null,
      batchRunning: false, batchFinished: false, batchTotal: 0, batchDone: 0, batchCurrent: '',
      batchMax: {{ \App\Http\Controllers\CadastralDept\CaseController::batchMax() }},

      toggleBatch() {
        if (this.batchMode && this.batch.length && !confirm('Switch batch mode off? The ' + this.batch.length + ' file(s) in the batch are cleared.')) return;
        this.batchMode = !this.batchMode;
        this.batch = []; this.batchApplied = false; this.batchSnap = null; this.batchNotice = '';
        this.scan = null; this.clearFile();
      },
      batchLocked() { return this.batchMode && this.batch.length > 0; },
      typeNow() { return { workflow: this.workflow, vGroup: this.vGroup, dGroup: this.dGroup, fileType: this.fileType, subType: this.subType, allocationRoute: this.allocationRoute }; },
      // Every file in a batch is one type: a change while files are listed is put back.
      guardType() {
        if (!this.batchLocked() || !this.batchSnap) return false;
        Object.assign(this, this.batchSnap);
        this.batchNotice = 'Every file in a batch has the same application type and file type. Clear the batch to change them.';
        return true;
      },
      batchTypeLabel() {
        const w = this.workflows.find(x => x.key === this.workflow);
        return [w ? w.label : '', this.workflow === 'verification' && this.vGroup ? (this.vGroup === 'conversion' ? 'Conversion' : 'Direct Allocation') : '', this.ft() ? this.ft().label : ''].filter(Boolean).join(' › ');
      },
      // Does a file number fit the batch's file type (and Verification's group)?
      fitsType(number) {
        const n = String(number || '').trim().toUpperCase();
        const own = (this.ft() && this.ft().prefixes) || [];
        if (own.length) return own.some(p => n.startsWith(p.toUpperCase()));
        if (this.workflow === 'verification' && this.vGroup) {
          const g = { conversion: ['CON-'], direct: ['RES-', 'COM-', 'IND-', 'AG-'] }[this.vGroup] || [];
          return g.some(p => n.startsWith(p));
        }
        return true;
      },
      batchOkCount() { return this.batch.filter(r => r.ok).length; },
      // Check one file the way Register File does, then list it (Ready, or Will be skipped and why).
      async addToBatch(number, indexingId, trackingId) {
        const n = String(number || '').trim();
        this.batchNotice = '';
        if (this.batchFinished || this.batchRunning) { this.batchNotice = 'This batch is registered. Click Start a new batch to add more files.'; return; }
        if (this.batchApplied) { this.batchNotice = 'The batch is applied. Click Edit the batch to add more files.'; return; }
        if (this.batch.some(r => r.file_number.toUpperCase() === n.toUpperCase())) { this.batchNotice = n + ' is already in the batch.'; return; }
        if (this.batch.length >= this.batchMax) { this.batchNotice = 'A batch takes at most ' + this.batchMax + ' files. Register these, then start another batch.'; return; }
        if (!this.fitsType(n)) {
          this.batchNotice = n + ' is not a ' + (this.ft() ? this.ft().label : 'file of this type') + ' for this batch' +
            ((this.ft() && (this.ft().prefixes || []).length) ? ': its number must start with ' + this.ft().prefixes.join(' / ') + '.' : '.');
          return;
        }
        this.batchBusy = true;
        let res = null;
        try {
          const q = { file_number: n, workflow: this.workflow };
          if (indexingId) q.file_indexing_id = indexingId;
          res = await (await fetch(this.lookupUrl + '?' + new URLSearchParams(q), { headers: { 'Accept': 'application/json' } })).json();
        } catch (e) { this.batchBusy = false; this.batchNotice = 'Could not check ' + n + '. Try again.'; return; }
        this.batchBusy = false;
        this.pushBatch(res, n, indexingId, trackingId);
      },
      pushBatch(res, n, indexingId, trackingId) {
        const f = (res && res.file) || {};
        const ok = res && res.status === 'ok';
        const notes = [];
        if (ok && res.correspondence && !res.correspondence.exists) notes.push('Correspondence file will be created');
        if (ok && res.duplicate && res.duplicate.flag) notes.push('Possible duplicate: ' + res.duplicate.note);
        if (!this.batch.length) this.batchSnap = this.typeNow();
        this.batch.push({
          file_number: f.file_number || n, file_indexing_id: f.file_indexing_id || indexingId || '', tracking_id: trackingId || '',
          title: f.title || '', plot: f.plot || '', district: f.district || '', lga: f.lga || '',
          ok, note: ok ? notes.join('. ') : ((res && res.message) || 'Cannot be registered.'),
        });
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      },
      // A file picked from the list and checked: add it as it is.
      addPickedToBatch() {
        if (!this.result || this.result.status !== 'ok') return;
        const n = this.fileNumber;
        if (this.batchApplied) { this.batchNotice = 'The batch is applied. Click Edit the batch to add more files.'; return; }
        if (this.batch.some(r => r.file_number.toUpperCase() === n.toUpperCase())) { this.batchNotice = n + ' is already in the batch.'; return; }
        if (this.batch.length >= this.batchMax) { this.batchNotice = 'A batch takes at most ' + this.batchMax + ' files.'; return; }
        if (!this.prefixOk() || !this.groupOk()) { this.batchNotice = n + ' is not a ' + (this.ft() ? this.ft().label : 'file of this type') + ' for this batch.'; return; }
        this.pushBatch(this.result, n, this.fileIndexingId, this.trackingId);
        this.clearFile();
      },
      removeFromBatch(i) {
        this.batch.splice(i, 1);
        if (!this.batch.length) { this.batchSnap = null; this.batchApplied = false; }
      },
      clearBatch() {
        if (this.batch.length && !confirm('Clear the ' + this.batch.length + ' file(s) from the batch?')) return;
        this.batch = []; this.batchSnap = null; this.batchApplied = false; this.batchNotice = '';
      },
      // The picked file already has a correspondence file: register it under Verification instead.
      proceedVerification() {
        const n = (this.fileNumber || '').toUpperCase();
        const own = this.ft() && (this.ft().prefixes || []).length ? this.fileType : '';
        this.applyScan({ file_number: this.fileNumber, tracking_id: this.trackingId, file_type: own,
          group: n.startsWith('CON-') ? 'conversion' : (['RES-', 'COM-', 'IND-', 'AG-'].some(p => n.startsWith(p)) ? 'direct' : '') }, 'verification');
      },
      batchRegistered() { return this.batch.filter(r => r.result && r.result.ok).length; },
      confirmBatch() {
        const n = this.batchOkCount();
        const text = n + ' file(s) are registered one by one and sent to the first desk.';
        if (typeof Swal === 'undefined') { if (window.confirm('Register this batch?\n\n' + text)) this.registerBatch(); return; }
        Swal.fire({ title: 'Register this batch?', text, icon: 'question', showCancelButton: true, reverseButtons: true,
          confirmButtonText: 'Yes, register', cancelButtonText: 'Cancel', confirmButtonColor: '#15803d' })
          .then(r => { if (r.isConfirmed) this.registerBatch(); });
      },
      // One request per file, so the page shows "3 of 12" and each row's result as it lands.
      async registerBatch() {
        const todo = this.batch.filter(r => r.ok && !r.result);
        if (!todo.length || this.batchRunning) return;
        this.batchRunning = true; this.batchFinished = false; this.batchTotal = todo.length; this.batchDone = 0;
        const leave = e => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', leave);
        const base = new FormData(this.$refs.intakeForm);
        for (const k of Array.from(base.keys())) { if (k.startsWith('files[')) base.delete(k); }
        let stop = '';
        for (const r of todo) {
          if (stop) { r.result = { ok: false, message: 'Not sent: ' + stop }; this.batchDone++; continue; }
          this.batchCurrent = r.file_number;
          const fd = new FormData();
          base.forEach((v, k) => fd.append(k, v));
          fd.append('files[0][file_number]', r.file_number);
          fd.append('files[0][file_indexing_id]', r.file_indexing_id || '');
          fd.append('files[0][tracking_id]', r.tracking_id || '');
          try {
            const res = await fetch(this.batchUrl, { method: 'POST', body: fd, credentials: 'same-origin',
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await res.json().catch(() => ({}));
            if (!res.ok) {
              // A refused request (validation, access, session) fails every file the same way: stop.
              const msg = (j.errors ? Object.values(j.errors).flat().join(' ') : '') || j.message || ('The server refused the request (' + res.status + ').');
              r.result = { ok: false, message: msg };
              stop = msg;
            } else {
              r.result = (j.results && j.results[0]) || { ok: false, message: 'No answer for this file.' };
            }
          } catch (e) {
            r.result = { ok: false, message: 'Could not reach the server. Check the connection; this file was not registered.' };
          }
          this.batchDone++;
        }
        this.batchCurrent = ''; this.batchRunning = false; this.batchFinished = true;
        window.removeEventListener('beforeunload', leave);
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      },
      newBatch() {
        this.batch = []; this.batchSnap = null; this.batchApplied = false; this.batchNotice = '';
        this.batchRunning = false; this.batchFinished = false; this.batchTotal = 0; this.batchDone = 0;
        this.$nextTick(() => { this.$refs.scanBox && this.$refs.scanBox.focus(); });
      },
      applyBatch() {
        if (!this.batchOkCount()) { this.batchNotice = 'No file in the batch can be registered.'; return; }
        this.batchApplied = true; this.batchNotice = '';
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      },

      // Scan to register: find the file, then the officer picks the application type.
      async runScan(code) {
        code = String(code !== undefined ? code : this.scanCode).trim();
        if (!code || this.scanBusy) return;
        this.scanCode = code; this.scanBusy = true; this.scan = null;
        try {
          const res = await fetch(this.scanUrl + '?' + new URLSearchParams({ code }), { headers: { 'Accept': 'application/json' } });
          if (!res.ok) throw new Error(res.status);
          this.scan = await res.json();
        } catch (e) {
          this.scan = { resolved: false, message: 'Could not look the code up. Try again.', matches: [] };
        }
        this.scanBusy = false;
        if (this.batchMode && this.scan.resolved && this.typeMissing() === '') {
          const m = this.scan.matches.find(x => this.fitsType(x.file_number)) || this.scan.matches[0];
          if (m.open_case) {
            this.batchNotice = m.file_number + ' is already registered as ' + m.open_case.ref + ' (with ' + m.open_case.desk + ').';
          } else {
            await this.addToBatch(m.file_number, null, m.tracking_id);
          }
          this.scan = null; this.scanCode = '';
          this.$nextTick(() => { this.$refs.scanBox && this.$refs.scanBox.focus(); });
          return;
        }
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      },
      openCamera() {
        if (!window.CdQrScan) { this.scan = { resolved: false, message: 'The camera scanner did not load. Type the code instead.', matches: [] }; return; }
        window.CdQrScan.open(text => this.runScan(text));
      },
      // Fill the form as if the officer had clicked through the tabs and picked the file.
      applyScan(m, workflow) {
        this.workflow = workflow;
        this.onWorkflow();
        if (workflow === 'verification') {
          this.vGroup = m.group || (m.file_type && this.fileTypes.find(t => t.key === m.file_type && t.workflows.includes('conversion')) ? 'conversion' : 'direct');
          this.onGroup();
        } else if (workflow === 'direct') {
          this.dGroup = 'statutory';
          this.onGroup();
        }
        if (m.file_type && this.typesFor().some(t => t.key === m.file_type)) {
          this.fileType = m.file_type;
          this.onFileType();
        }
        if (this.batchMode) {
          this.scan = null; this.scanCode = '';
          if (this.typeMissing() !== '') {
            this.batchNotice = this.typeMissing() + ' Then scan the file again.';
            this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            return;
          }
          this.addToBatch(m.file_number, null, m.tracking_id);
          this.$nextTick(() => { this.$refs.scanBox && this.$refs.scanBox.focus(); if (window.lucide) lucide.createIcons(); });
          return;
        }
        this.trackingId = m.tracking_id || '';
        this.lookup({ file_number: m.file_number });
        this.scan = null; this.scanCode = '';
        this.$nextTick(() => {
          if (window.lucide) lucide.createIcons();
          const el = this.$root.querySelector('[data-cd-file-section]');
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      },

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
        if (this.guardType()) return;
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
        if (this.guardType()) return;
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
        if (this.guardType()) return;
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
      // The application type / file type questions (shared by one file and a batch).
      typeMissing() {
        if (!this.workflow) return 'Choose the application type.';
        if (this.workflow === 'verification' && !this.vGroup) return 'Choose Conversion or Direct Allocation.';
        if (this.workflow === 'direct' && !this.dGroup) return 'Choose Direct (Statutory).';
        if (!this.fileType) return 'Choose the file type.';
        if (this.asksSubType() && !this.subType) return 'Choose Resettlement or Direct.';
        if (this.tenureAsks() && !this.subType) return 'Choose Statutory or Customary.';
        if (this.routeAsks() && !this.allocationRoute) return 'Choose Resettlement or Direct.';
        return '';
      },
      missing() {
        if (this.batchMode) {
          const t = this.typeMissing();
          if (t) return t;
          if (!this.batchOkCount()) return 'Add at least one file that can be registered to the batch.';
          if (!this.batchApplied) return 'Apply the batch.';
          if (!this.fileSource) return 'Choose where the files came from.';
          if (this.fileSource === 'other' && !this.fileSourceOther.trim()) return 'Specify where the files came from.';
          return '';
        }
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
