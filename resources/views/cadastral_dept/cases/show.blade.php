@extends('layouts.app')

@section('content')
@php
  $wf = $def->workflow($case->workflow);
  $c = $wf['colour'];
  $tasks = (array) ($step['tasks'] ?? []);
  $allDone = count($tasks) === $done->count();
  $needsSubType = $case->isOpen() && isset($step['route_by']) && ! $case->sub_type;
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => $case->case_ref, 'PageDescription' => $wf['label'] . ' · ' . $case->file_number])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    {{-- Header --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">{!! $def->wfChip($case->workflow) !!} {!! $def->statusChip($case->status) !!}</div>
        <div class="text-2xl font-black text-gray-800 font-mono mt-2">{{ $case->file_number }}</div>
        <div class="text-sm text-gray-600">{{ $case->file_title }}</div>
        @php $cdDel = $case->masterDeleteJs(route('cadastral-dept.cases.index')); @endphp
        @if ($cdDel)
          <button type="button" onclick="{{ $cdDel }}"
                  class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-red-300 text-red-700 text-xs font-semibold hover:bg-red-50">
            <i data-lucide="shield-alert" class="w-4 h-4"></i> Master Delete
          </button>
        @endif
      </div>
      <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
        <dt class="text-gray-500">File type</dt><dd>{{ $case->fileTypeLabel() }}</dd>
        @if ($wf['sub_types'] ?? null)
          <dt class="text-gray-500">{{ $wf['sub_type_label'] ?? 'Route' }}</dt><dd>{{ $wf['sub_types'][$case->sub_type] ?? 'Not set' }}</dd>
        @endif
        @if ($wf['asks_allocation_route'] ?? null)
          <dt class="text-gray-500">Resettlement / Direct</dt><dd>{{ $def->subTypes($wf['asks_allocation_route'])[$case->allocation_route ?? ''] ?? 'Not set' }}</dd>
        @endif
        <dt class="text-gray-500">Came from</dt><dd>{{ $case->fileSourceLabel() }}</dd>
        <dt class="text-gray-500">Plot No.</dt><dd>{{ $case->plot_no ?? null ?: '—' }}</dd>
        <dt class="text-gray-500">District</dt><dd>{{ $case->district ?? null ?: '—' }}</dd>
        <dt class="text-gray-500">LGA</dt><dd>{{ $case->lga ?? null ?: '—' }}</dd>
        <dt class="text-gray-500">Location</dt><dd>{{ $case->location ?: '—' }}</dd>
        @if ($fmc)
          <dt class="text-gray-500">FMC</dt>
          <dd><a href="{{ route('cadastral-dept.cases.fmc.show', $case->id) }}" class="font-mono text-blue-700 hover:underline">{{ $fmc->fmc_no }}</a></dd>
        @endif
        @if ($case->correspondence_fileno)
          <dt class="text-gray-500">Correspondence</dt>
          <dd class="font-mono">{{ $case->correspondence_fileno }}@if ($case->shadow_fileno ?? null) · {{ $case->shadow_fileno }}@endif</dd>
        @endif
        <dt class="text-gray-500">Registered</dt><dd>{{ optional($case->received_at)->format('d M Y H:i') }} · {{ optional($case->receiver)->name }}</dd>
      </dl>
    </div>

    @if ($case->duplicate_flag)
      <div class="mb-6 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        <strong>Possible duplicate.</strong> {{ $case->duplicate_note }}
      </div>
    @endif

    @if ($case->workflow === 'verification')
      {{-- Which File Status lists the file is on; fetched after the page (the Re-grant term check can be slow). --}}
      <div id="cd-file-status" class="rounded-xl border border-gray-200 bg-white px-5 py-4 mb-6"
           data-url="{{ route('cadastral-dept.cases.file-status', $case->id) }}">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">File status checks</h3>
        <p class="mt-2 text-sm text-gray-400">Checking RofO, CofO, decommissioning, duplicates, Re-grant, title status, DCIV, caveat and mortgage…</p>
      </div>
      <script>
        (function () {
          var box = document.getElementById('cd-file-status');
          fetch(box.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) { box.innerHTML = html; if (window.lucide) lucide.createIcons(); })
            .catch(function () { box.querySelector('p').textContent = 'Could not load the file status checks. Reload the page to try again.'; });
        })();
      </script>

      {{-- The Cadastral Verification Report Sheet (client DOCX, 2026-10-15). --}}
      @php $cdVr = app(\App\Services\CadastralDept\VerificationReports::class)->forCase($case); @endphp
      <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Cadastral Verification Report Sheet</h3>
          <p class="mt-1 text-sm {{ $cdVr && $cdVr->isComplete() ? 'text-green-800' : 'text-gray-600' }}">
            @if (! $cdVr)
              Not started.{{ $case->isOpen() ? ' Complete it during Verification.' : '' }}
            @elseif ($cdVr->isComplete())
              {{ \App\Models\CadastralDept\CadastralVerificationReport::VERDICTS[$cdVr->verdict][0] }} · saved {{ $cdVr->updated_at->format('d M Y H:i') }}
            @else
              Started, no verdict yet · saved {{ $cdVr->updated_at->format('d M Y H:i') }}
            @endif
          </p>
        </div>
        <div class="flex items-center gap-2">
          <a href="{{ route('cadastral-dept.cases.verification-report.show', $case->id) }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg {{ $case->isOpen() ? 'bg-green-700 text-white' : 'border border-gray-300 text-gray-700' }} text-sm font-semibold">
            <i data-lucide="clipboard-check" class="w-4 h-4"></i> {{ $case->isOpen() ? ($cdVr ? 'Edit sheet' : 'Fill in sheet') : 'View sheet' }}
          </a>
          @cadCan('print')
            <a href="{{ route('cadastral-dept.cases.verification-report.print', $case->id) }}" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">
              <i data-lucide="printer" class="w-4 h-4"></i> Print
            </a>
          @endcadCan
        </div>
      </div>
    @endif

    {{-- Phase tracker --}}
    <div class="flex flex-wrap gap-2 mb-6">
      @foreach ($progress as $phase => $state)
        @php $cls = ['done' => 'bg-green-100 text-green-800 border-green-200', 'current' => 'bg-yellow-100 text-yellow-900 border-yellow-300', 'waiting' => 'bg-gray-100 text-gray-500 border-gray-200'][$state]; @endphp
        <div class="flex-1 min-w-0 rounded-lg border px-3 py-2 text-xs font-semibold {{ $cls }}" style="min-width: 150px;">
          Phase {{ $phase }} · {{ $wf['phases'][$phase] }}
        </div>
      @endforeach
    </div>

    {{-- The process, step by step: done, where it is now, and the way on. --}}
    {{-- Tinted in the workflow's colour (user, 2026-10-15). --}}
    <div class="rounded-xl border border-{{ $c }}-200 bg-{{ $c }}-50 px-5 py-4 mb-6">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h3 class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-{{ $c }}-800"><i data-lucide="{{ $wf['icon'] ?? 'folder' }}" class="w-4 h-4"></i>Process · {{ $wf['label'] }}@if ($case->sub_type && isset($wf['sub_types'][$case->sub_type])) ({{ $wf['sub_types'][$case->sub_type] }})@endif</h3>
        @if ($case->isOpen())
          <span class="text-xs font-semibold text-{{ $c }}-800">Now: {{ $step['label'] }}</span>
        @endif
      </div>
      @include('cadastral_dept.partials._pipeline', ['pipeline' => $pipeline, 'c' => $c])
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      {{-- Current step --}}
      <div class="lg:col-span-2">
        @if ($case->isOpen())
          <div class="rounded-xl border-2 border-{{ $c }}-300 bg-white p-5">
            <div class="text-xs font-bold uppercase text-{{ $c }}-700">Now at: {{ $def->deskLabel($step['desk']) }}</div>
            <div class="text-lg font-bold text-gray-800">{{ $step['label'] }}</div>
            @php $cdBack = $case->sentBack(); @endphp
            @if ($cdBack)
              <div class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                Sent back by {{ $cdBack['desk'] }} {{ optional($cdBack['at'])->diffForHumans() }}@if ($cdBack['reason']): “{{ $cdBack['reason'] }}”@endif
              </div>
            @endif
            @php $cdComp = $case->compensationAnswer(); @endphp
            @if ($cdComp)
              {{-- Compensation's answer and comment, for DSG and then D Cad (transcript, 2026-10-16). --}}
              <div class="mt-2 rounded-lg border {{ $cdComp['yes'] ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800' }} px-3 py-2 text-sm">
                <div><strong>Compensation:</strong> {{ $cdComp['answer'] }}@if ($cdComp['comment']): “{{ $cdComp['comment'] }}”@endif</div>
                @if ($cdComp['dsg_minute'])
                  <div class="mt-1"><strong>DSG minute:</strong> “{{ $cdComp['dsg_minute'] }}”</div>
                @endif
              </div>
            @endif
            @if (! empty($step['external']))
              <p class="text-sm text-gray-500 mt-1">The file is with another department. {{ $def->deskLabel($actingDesk) }} records its return.</p>
            @endif

            @if ($tasks)
              <ul class="mt-4 space-y-2">
                @foreach ($tasks as $key => $label)
                  <li class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 {{ isset($done[$key]) ? 'border-green-200 bg-green-50' : 'border-gray-200' }}">
                    <span class="text-sm {{ isset($done[$key]) ? 'text-green-800' : 'text-gray-700' }}">
                      <i data-lucide="{{ isset($done[$key]) ? 'check-circle-2' : 'circle' }}" class="w-4 h-4 inline -mt-0.5"></i> {{ $label }}
                    </span>
                    @if (isset(\App\Services\CadastralDept\ChartingCards::TASK_STAGE[$key]) && $chartingInstalled)
                      <span class="flex items-center gap-2">
                        @if (isset($done[$key]))
                          <span class="text-xs text-gray-500">{{ optional($done[$key]->doer)->name }} · {{ $done[$key]->done_at->format('d M H:i') }}</span>
                        @endif
                        <a href="{{ route('cadastral-dept.cases.charting.create', $case->id) }}"
                           class="px-3 py-1 rounded-md {{ isset($done[$key]) ? 'border border-gray-300 text-gray-700' : 'bg-green-700 text-white' }} text-xs font-semibold">
                          {{ isset($done[$key]) ? 'Correct card' : 'Fill in charting card' }}
                        </a>
                      </span>
                    @elseif (in_array($key, ['correspondence_detached', 'correspondence_attached'], true) && ! isset($done[$key]) && ! trim((string) $case->correspondence_fileno))
                      <span class="text-xs text-gray-500">Not needed: no correspondence file</span>
                    @elseif (in_array($key, ['correspondence_detached', 'correspondence_attached'], true) && $custodyInstalled && ! isset($done[$key]))
                      @php $cdCustodyAction = $key === 'correspondence_detached' ? 'detach' : 'attach'; @endphp
                      {{-- Opens the side-by-side preview (partials/_custody_preview); the note is asked there. --}}
                      <button type="button" class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold"
                              data-custody-open="{{ $cdCustodyAction }}" data-url="{{ route('cadastral-dept.cases.correspondence.custody.save', $case->id) }}"
                              data-ref="{{ $case->case_ref }}" data-file="{{ $case->file_number }}" data-title="{{ $case->file_title }}"
                              data-corr="{{ $case->correspondence_fileno }}" data-csf="{{ $case->shadow_fileno }}">
                        {{ $cdCustodyAction === 'detach' ? 'Detach correspondence file' : 'Attach correspondence file' }}
                      </button>
                    @elseif ($key === 'bill_generated')
                      <span class="flex items-center gap-2">
                        @if (isset($done[$key]))
                          <span class="text-xs text-gray-500">{{ $done[$key]->note }} · {{ optional($done[$key]->doer)->name }} · {{ $done[$key]->done_at->format('d M H:i') }}</span>
                        @endif
                        <a href="{{ route('cadastral-dept.cases.bill.show', $case->id) }}"
                           class="px-3 py-1 rounded-md {{ isset($done[$key]) ? 'border border-gray-300 text-gray-700' : 'bg-green-700 text-white' }} text-xs font-semibold">
                          {{ isset($done[$key]) ? 'Open bill' : 'Generate Fees P&D bill' }}
                        </a>
                      </span>
                    @elseif (in_array($key, ['field_jacket', 'its', 'job_attached', 'job_its'], true) && $jacketsInstalled)
                      {{-- P&D: plain field jacket, plain I to S. AD Cad Info: job and surveyor, then the
                           job's Instruction to Surveyor (transcript, 2026-10-16). --}}
                      <span class="flex items-center gap-2">
                        @if (isset($done[$key]))
                          <span class="text-xs text-gray-500">{{ $done[$key]->note && $key !== 'its' ? $done[$key]->note . ' · ' : '' }}{{ optional($done[$key]->doer)->name }} · {{ $done[$key]->done_at->format('d M H:i') }}</span>
                          @if ($fieldJacket)
                            <a href="{{ route('cadastral-dept.field-jackets.show', $fieldJacket->id) }}" class="px-3 py-1 rounded-md border border-gray-300 text-gray-700 text-xs font-semibold">Open jacket</a>
                          @endif
                        @elseif ($key === 'field_jacket')
                          <form method="POST" action="{{ route('cadastral-dept.cases.field-jacket.store', $case->id) }}"
                                data-confirm-title="Commission the field jacket?" data-confirm="A field jacket is numbered for {{ $case->file_number }}. AD Cad Info attaches the job and the surveyor later.">
                            @csrf
                            <button class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold">Commission Field Jacket</button>
                          </form>
                        @elseif ($key === 'its' && $fieldJacket)
                          <button type="button" onclick="document.getElementById('cd-pnd-its-modal').classList.remove('hidden')"
                                  class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold">Issue I to S</button>
                        @elseif ($key === 'job_attached' && $fieldJacket)
                          <button type="button" class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold"
                                  data-attach-job data-url="{{ route('cadastral-dept.cases.job.store', $case->id) }}"
                                  data-ref="{{ $case->case_ref }}" data-file="{{ $case->file_number }}" data-plot="{{ $case->plot_no }}"
                                  data-district="{{ $case->district }}" data-lga="{{ $case->lga }}" data-jacket="{{ $fieldJacket->field_jacket_no }}">Attach job and surveyor</button>
                        @elseif ($key === 'job_its' && $fieldJacket?->job)
                          <button type="button" onclick="document.getElementById('cd-its-modal').classList.remove('hidden')"
                                  class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold">Issue Instruction to Surveyor</button>
                        @else
                          <span class="text-xs text-gray-500">{{ $key === 'job_its' ? 'Attach the job and surveyor first' : 'Commission the field jacket first' }}</span>
                        @endif
                      </span>
                    @elseif ($key === 'land12' && $yesNoInstalled)
                      {{-- Land 12 has no screen here: the tick stays manual, once the Yes/No form is complete. --}}
                      <span class="flex items-center gap-2">
                        @if (isset($done[$key]))
                          <span class="text-xs text-gray-500">{{ optional($done[$key]->doer)->name }} · {{ $done[$key]->done_at->format('d M H:i') }}</span>
                        @elseif ($yesNoForm)
                          <span class="text-xs {{ $yesNoForm->isComplete() ? 'text-green-700' : 'text-yellow-700' }}">Yes/No form {{ $yesNoForm->isComplete() ? 'complete' : 'incomplete' }}</span>
                        @endif
                        <a href="{{ route('cadastral-dept.cases.yes-no.show', $case->id) }}"
                           class="px-3 py-1 rounded-md {{ $yesNoForm?->isComplete() ? 'border border-gray-300 text-gray-700' : 'bg-green-700 text-white' }} text-xs font-semibold">
                          {{ $yesNoForm ? 'Open Yes/No form' : 'Fill in Yes/No form' }}
                        </a>
                        @if (! isset($done[$key]) && $yesNoForm?->isComplete())
                          <form method="POST" action="{{ route('cadastral-dept.cases.tasks.mark-done', $case->id) }}"
                                data-confirm-title="Mark Land 12 done?" data-confirm="The Yes/No form is then locked and cannot be changed.">
                            @csrf
                            <input type="hidden" name="task" value="{{ $key }}">
                            <button class="px-3 py-1 rounded-md bg-gray-800 text-white text-xs font-semibold">Mark done</button>
                          </form>
                        @endif
                      </span>
                    @elseif (isset($done[$key]))
                      <span class="text-xs text-gray-500">{{ optional($done[$key]->doer)->name }} · {{ $done[$key]->done_at->format('d M H:i') }}</span>
                    @elseif ($key === 'correspondence_created')
                      <form method="POST" action="{{ route('cadastral-dept.cases.correspondence.store', $case->id) }}"
                            data-confirm-title="Create the correspondence file?" data-confirm="For {{ $case->file_number }}. If it already has one, it is matched instead.">
                        @csrf
                        <button class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold">Create Correspondence File</button>
                      </form>
                    @elseif ($key === 'fmc_commissioned')
                      @if ($fmcBackfill !== null)
                        <button type="button" onclick="document.getElementById('cd-fmc-modal').classList.remove('hidden')"
                                class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold">Commission FMC</button>
                      @else
                        <span class="text-xs text-gray-500">FMC tables not installed</span>
                      @endif
                    @else
                      <form method="POST" action="{{ route('cadastral-dept.cases.tasks.mark-done', $case->id) }}"
                            data-confirm-title="Mark done?" data-confirm="{{ $label }}: ticked as done for {{ $case->case_ref }}.">
                        @csrf
                        <input type="hidden" name="task" value="{{ $key }}">
                        <button class="px-3 py-1 rounded-md bg-gray-800 text-white text-xs font-semibold">Mark done</button>
                      </form>
                    @endif
                  </li>
                @endforeach
              </ul>
            @endif

            @if ($checks)
              <div class="mt-4 rounded-lg border {{ collect($checks)->every('ok') ? 'border-green-200 bg-green-50' : 'border-yellow-300 bg-yellow-50' }} p-3">
                <div class="text-sm font-semibold text-gray-800 mb-2">Before {{ $actions[0]['label'] ?? 'the file goes on' }}</div>
                <ul class="space-y-1 text-sm">
                  @foreach ($checks as $chk)
                    <li class="flex items-center justify-between gap-3">
                      <span class="{{ $chk['ok'] ? 'text-green-800' : 'text-red-700' }}"><i data-lucide="{{ $chk['ok'] ? 'check-circle-2' : 'x-circle' }}" class="w-4 h-4 inline -mt-0.5"></i> {{ $chk['label'] }}</span>
                      <span class="text-xs text-gray-600">{{ $chk['detail'] }}</span>
                    </li>
                  @endforeach
                </ul>
              </div>
            @endif

            @if ($needsSubType)
              <form method="POST" action="{{ route('cadastral-dept.cases.sub-type.update', $case->id) }}" data-confirm-title="Set the sub-type?" data-confirm="It decides where {{ $case->case_ref }} goes next, and cannot be changed once the file is routed on it." class="mt-4 rounded-lg border border-yellow-300 bg-yellow-50 p-3 flex flex-wrap items-center gap-2">
                @csrf
                <span class="text-sm text-yellow-900 font-semibold">Set the sub-type: it decides where the file goes next.</span>
                <select name="sub_type" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
                  @foreach ($wf['sub_types'] as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
                <button class="px-3 py-1 rounded-md bg-yellow-600 text-white text-xs font-semibold">Save</button>
              </form>
            @endif

            @if (! empty($headInfo['required']))
              {{-- Head-of-unit approval (HeadApproval): staff send to the head, who sends it across. --}}
              @if ($headInfo['awaiting'])
                <div class="mt-4 rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-sm text-purple-900">
                  <i data-lucide="user-check" class="w-4 h-4 inline -mt-0.5"></i>
                  Sent to the {{ $headInfo['head'] }} for approval.
                  {{ $headInfo['is_head'] ? 'Send it on below, or return it to the staff with a reason.' : 'Waiting for the ' . $headInfo['head'] . ' to send it on.' }}
                </div>
              @elseif (! $headInfo['is_head'])
                <p class="mt-4 text-xs text-gray-500">When the work is done, send the file to the {{ $headInfo['head'] }}. Only the {{ $headInfo['head'] }} sends it to the next unit.</p>
              @endif
              @if ($headInfo['locked'])
                <div class="mt-2 flex flex-wrap gap-2">
                  @foreach ($headInfo['locked'] as $cdLocked)
                    <span class="px-3 py-1.5 rounded-lg bg-gray-200 text-gray-500 text-xs font-semibold cursor-not-allowed" title="Only the {{ $headInfo['head'] }} sends the file on">
                      <i data-lucide="lock" class="w-3 h-3 inline -mt-0.5"></i> {{ $cdLocked['label'] }}
                    </span>
                  @endforeach
                </div>
              @endif
            @endif

            @if ($actions)
              @php $needsReason = collect($actions)->contains('reason', true); @endphp
              <form id="act" method="POST" action="{{ route('cadastral-dept.cases.move.save', $case->id) }}" class="mt-5 pt-4 border-t border-gray-100"
                    x-data="{ remarks: @js(old('remarks', '')) }">
                @csrf
                @if (! empty($step['question']) && ! (! empty($headInfo['required']) && ! $headInfo['is_head']))
                  <div class="text-base font-bold text-gray-800 mb-2">{{ $step['question'] }}</div>
                @endif
                <textarea name="remarks" rows="2" x-model="remarks"
                          placeholder="{{ $needsReason ? 'Reason (required to send the file back)' : 'Remarks (optional)' }}"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3"></textarea>
                @unless ($allDone)
                  <p class="text-xs text-gray-500 mb-2">Finish every task above before sending the file on.</p>
                @endunless
                <div class="flex flex-wrap gap-2">
                  @foreach ($actions as $a)
                    @php
                      $btn = $a['ends'] === 'returned' || $a['reason'] ? 'bg-red-600' : ($a['ends'] ? 'bg-green-700' : 'bg-blue-600');
                    @endphp
                    <button name="outcome" value="{{ $a['outcome'] }}" @disabled(! $allDone)
                            @if ($a['reason']) :disabled="{{ $allDone ? 'false' : 'true' }} || remarks.trim() === ''" title="Write the reason first" @endif
                            data-confirm="{{ $a['label'] }}: {{ $case->case_ref }} ({{ $case->file_number }}) goes to {{ $a['to'] }}."
                            data-confirm-title="{{ $a['label'] }}?" @if ($btn === 'bg-red-600') data-confirm-danger @endif
                            class="px-4 py-2 rounded-lg {{ $btn }} text-white text-sm font-semibold disabled:opacity-50 text-left">
                      {{ $a['label'] }}
                      @unless (str_contains($a['label'], $a['to']))
                        <span class="block text-xs font-normal opacity-80">to {{ $a['to'] }}</span>
                      @endunless
                    </button>
                  @endforeach
                </div>
              </form>
            @endif
          </div>
        @else
          <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600">
            Closed {{ optional($case->closed_at)->format('d M Y H:i') }}:
            {{ $case->status === 'returned' ? 'sent back to ' . $case->fileSourceLabel() . '.' : 'completed.' }}
          </div>
        @endif

        @if ($chartings->isNotEmpty())
          <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mt-6 mb-3">Charting cards</h3>
          <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Charting No.</th><th class="px-3 py-2">Stage</th><th class="px-3 py-2">Flags</th><th class="px-3 py-2">By</th><th class="px-3 py-2 text-right">Action</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                @foreach ($chartings as $ch)
                  <tr>
                    <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2 font-mono">{{ $ch->charting_no }}@if ($ch->version > 1)<span class="text-xs text-gray-500"> v{{ $ch->version }}</span>@endif</td>
                    <td class="px-3 py-2">{{ $ch->stageLabel() }}</td>
                    <td class="px-3 py-2">
                      @forelse ($ch->flags() as $flag)
                        <div class="text-xs text-red-700">{{ $flag }}</div>
                      @empty
                        <span class="text-xs text-green-700">None</span>
                      @endforelse
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ optional($ch->officer)->name }}, {{ $ch->charted_at->format('d M Y') }}</td>
                    <td class="px-3 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => [
                        ['Open card', 'id-card', route('cadastral-dept.charting.show', $ch->id)],
                        ['Print card', 'printer', route('cadastral-dept.charting.print', $ch->id), true],
                    ]])</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        @if ($custodyHistory->isNotEmpty())
          <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mt-6 mb-3">Correspondence file custody</h3>
          <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Action</th><th class="px-3 py-2">Correspondence File</th><th class="px-3 py-2">At step</th><th class="px-3 py-2">Note</th><th class="px-3 py-2">By</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                @foreach ($custodyHistory as $ev)
                  <tr>
                    <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2">{{ $ev->label() }}</td>
                    <td class="px-3 py-2 font-mono">{{ $ev->shadow_fileno ?: $ev->correspondence_fileno }}</td>
                    <td class="px-3 py-2">{{ $def->hasStep($case->workflow, $ev->step) ? $def->step($case->workflow, $ev->step)['label'] : $ev->step }}</td>
                    <td class="px-3 py-2">{{ $ev->note ?: '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ optional($ev->doer)->name }}, {{ $ev->done_at->format('d M Y H:i') }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        @if ($fieldJacket)
          @php
            $cdFjItems = [['Open field jacket', 'briefcase', route('cadastral-dept.field-jackets.show', $fieldJacket->id)]];
            if (\App\Services\CadastralDept\DeskAccess::can(auth()->user(), 'print')) {
                $cdFjItems[] = ['Print jacket cover', 'printer', route('cadastral-dept.field-jackets.print', $fieldJacket->id), true];
                if ($fieldJacket->job?->its_number) {
                    $cdFjItems[] = ['Print I to S', 'file-text', route('cadastral-dept.field-jackets.its.print', $fieldJacket->id), true];
                }
            }
          @endphp
          <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mt-6 mb-3">Field jacket</h3>
          <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Field Jacket</th><th class="px-3 py-2">Job No.</th><th class="px-3 py-2">Surveyor</th><th class="px-3 py-2">I to S</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">Action</th></tr>
              </thead>
              <tbody>
                <tr>
                  <td class="px-3 py-2 text-gray-500">1</td>
                  <td class="px-3 py-2 font-mono">{{ $fieldJacket->field_jacket_no }}</td>
                  <td class="px-3 py-2 font-mono">{{ $fieldJacket->job?->job_number ?: '—' }}</td>
                  <td class="px-3 py-2">{{ $fieldJacket->job?->surveyor_name ?: '—' }}@if ($fieldJacket->assignments->count() > 1)<div class="text-xs text-gray-500">reassigned {{ $fieldJacket->assignments->count() - 1 }}×</div>@endif</td>
                  <td class="px-3 py-2 font-mono">{{ $fieldJacket->job?->its_number ?: '—' }}</td>
                  <td class="px-3 py-2">
                    @if ($fieldJacket->job)
                      @include('cadastral_dept.field_jackets._status', ['cdStatus' => $fieldJacket->job->status])
                    @else
                      <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">No job yet</span>
                    @endif
                  </td>
                  <td class="px-3 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => $cdFjItems])</td>
                </tr>
              </tbody>
            </table>
          </div>
        @endif
      </div>

      {{-- Movement history --}}
      <div>
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Movement history</h3>
        {{-- Oldest first, starting with the registry the file came from (user, 2026-10-12), as on the File Movement Card. --}}
        <ol class="relative border-l border-gray-200 ml-2 space-y-4">
          <li class="ml-4">
            <div class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-gray-400 border border-white"></div>
            <div class="text-xs text-gray-500">{{ optional($case->received_at ?? $case->created_at)->format('d M Y H:i') }} · {{ optional($case->receiver)->name }}</div>
            <div class="text-sm text-gray-800">
              {{ $case->file_source === 'other' ? $case->fileSourceLabel() : $case->fileSourceLabel() . ' Registry' }} &rarr; Received at Cadastral Registry
            </div>
          </li>
          @foreach ($case->movements as $m)
            <li class="ml-4">
              <div class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-{{ $c }}-400 border border-white"></div>
              <div class="text-xs text-gray-500">{{ $m->acted_at->format('d M Y H:i') }} · {{ optional($m->actor)->name }}</div>
              <div class="text-sm text-gray-800">
                @if ($m->outcome === 'registered')
                  Registered at Registry &rarr; {{ $def->deskLabel($m->to_desk) }}
                @elseif ($m->outcome === 'received')
                  Received at {{ $def->deskLabel($m->to_desk) }}
                @elseif ($m->outcome === 'close')
                  Closed at {{ $def->deskLabel($m->from_desk) }}
                @elseif (in_array($m->outcome, ['to_head', 'head_return'], true))
                  {{-- Head-of-unit approval, within the unit (HeadApproval). --}}
                  @php $cdHead = $def->desks()[$m->from_desk]['head'] ?? $def->deskShort($m->from_desk); @endphp
                  {{ $m->outcome === 'to_head' ? 'Sent to the ' . $cdHead . ' for approval' : 'Returned to staff by the ' . $cdHead }}
                @else
                  {{ $def->deskLabel($m->from_desk) }} &rarr; {{ $def->deskLabel($m->to_desk) }}
                  @if ($m->outcome !== 'forward')
                    <span class="text-xs font-semibold text-gray-600">({{ str_replace('_', ' ', $m->outcome) }})</span>
                  @endif
                @endif
              </div>
              @if ($m->remarks)<div class="text-xs text-gray-600 italic">{{ $m->remarks }}</div>@endif
            </li>
          @endforeach
        </ol>
      </div>
    </div>
  </div>
  @include('admin.footer')
</div>

@if ($jobDue)
  @include('cadastral_dept.field_jackets._attach_job_modal')
@endif

@if ($pndItsDefault !== null)
  {{-- P&D's plain I to S: the instruction to AD Cad Info to attach a job and a surveyor. --}}
  <div id="cd-pnd-its-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
    <form method="POST" action="{{ route('cadastral-dept.cases.its.store', $case->id) }}" data-confirm-title="Issue the I to S?" data-confirm="The I to S goes with the field jacket to AD Cad Info, who attaches the job and the surveyor." class="w-full max-w-lg rounded-xl bg-white shadow-xl p-5 space-y-4 max-h-full overflow-y-auto">
      @csrf
      <div>
        <div class="text-xs font-bold uppercase text-gray-500">{{ $fieldJacket->field_jacket_no }} · {{ $case->file_number }}</div>
        <div class="text-lg font-bold text-gray-800">Issue I to S</div>
        <p class="text-xs text-gray-500 mt-1">No job or surveyor here: AD Cad Info attaches them from the Job Book.</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Instruction</label>
        <textarea name="its_instructions" rows="4" required maxlength="8000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('its_instructions', $pndItsDefault) }}</textarea>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('cd-pnd-its-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
        <button class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Issue I to S</button>
      </div>
    </form>
  </div>
@endif

@if ($itsDefault !== null)
  {{-- AD Cad Info: the job's Instruction to Surveyor, numbered and addressed to the surveyor. --}}
  <div id="cd-its-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
    <form method="POST" action="{{ route('cadastral-dept.cases.job-its.store', $case->id) }}" data-confirm-title="Issue the Instruction to Surveyor?" data-confirm="The instruction is numbered and issued to the surveyor." class="w-full max-w-lg rounded-xl bg-white shadow-xl p-5 space-y-4 max-h-full overflow-y-auto">
      @csrf
      <div>
        <div class="text-xs font-bold uppercase text-gray-500">{{ $fieldJacket->field_jacket_no }} · job {{ $fieldJacket->job->job_number }}</div>
        <div class="text-lg font-bold text-gray-800">Issue Instruction to Surveyor</div>
        <p class="text-xs text-gray-500 mt-1">To {{ $fieldJacket->job->surveyor_name }}@if ($fieldJacket->job->firm_name) ({{ $fieldJacket->job->firm_name }})@endif. The number is given on issuing. A surveyor whose licence is not current cannot receive it.</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Instruction</label>
        <textarea name="its_instructions" rows="6" required maxlength="8000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('its_instructions', $itsDefault) }}</textarea>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Signing officer (optional)</label>
        <select name="its_officer_post" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">Officer in Charge</option>
          @foreach (config('cadastral_module.posts') as $cdPost => $cdPostLabel)
            <option value="{{ $cdPost }}" @selected(old('its_officer_post', 'AD_CAD_INFO') === $cdPost)>{{ $cdPostLabel }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('cd-its-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
        <button class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Issue Instruction</button>
      </div>
    </form>
  </div>
@endif

@if ($fmcBackfill !== null)
  @php
    // Filled from the records and locked; a blank one is left for the officer.
    $cdFmcFields = [
        'file_title' => 'File Title', 'allottee_name' => 'Allottee Name',
        'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location',
    ];
  @endphp
  <div id="cd-fmc-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
    <form method="POST" action="{{ route('cadastral-dept.cases.fmc.store', $case->id) }}" data-confirm-title="Commission the FMC?" data-confirm="A File Movement Card is created for {{ $case->file_number }}." class="w-full max-w-lg rounded-xl bg-white shadow-xl p-5 space-y-4 max-h-full overflow-y-auto">
      @csrf
      <div>
        <div class="text-xs font-bold uppercase text-gray-500">{{ $case->case_ref }} · {{ $case->file_number }}</div>
        <div class="text-lg font-bold text-gray-800">Commission File Movement Card</div>
        <p class="text-xs text-gray-500 mt-1">Grey fields come from the file's records. Fill in what the records leave blank.</p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($cdFmcFields as $name => $label)
          <div class="{{ in_array($name, ['file_title', 'allottee_name', 'location'], true) ? 'sm:col-span-2' : '' }}">
            <label class="block text-xs font-semibold text-gray-600 mb-1">{{ $label }}</label>
            @if (isset($fmcBackfill[$name]))
              <input type="text" value="{{ $fmcBackfill[$name] }}" disabled class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-700">
            @else
              <input type="text" name="{{ $name }}" value="{{ old($name) }}" maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @endif
          </div>
        @endforeach
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Block No.</label>
          <input type="text" name="block_no" value="{{ old('block_no') }}" maxlength="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Layout No.</label>
          <input type="text" name="layout_no" value="{{ old('layout_no') }}" maxlength="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
        </div>
      </div>
      <div class="text-xs text-gray-500">Date and time of commissioning are recorded automatically.</div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('cd-fmc-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
        <button class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Commission FMC</button>
      </div>
    </form>
  </div>
@endif
@if ($custodyInstalled)
  @include('cadastral_dept.partials._custody_preview')
@endif
@endsection
