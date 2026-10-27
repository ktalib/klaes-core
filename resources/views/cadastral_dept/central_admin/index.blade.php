@extends('layouts.app')

@section('content')
{{-- Central Admin (CADASTRAL UPDATE, 2026-10-26): every open file, with the menu its desk shows.
     The row menus are copied from desk.blade.php; actions are live only for the Central Admin role. --}}
<div class="flex-1 overflow-auto bg-gray-50" x-data="cdDesk()">
  @include('admin.header', ['PageTitle' => 'Central Admin', 'PageDescription' => 'Admin · move any file through the Cadastral workflow'])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    @unless ($canAct)
      <div class="mb-6 flex items-start gap-3 rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm text-gray-700">
        <i data-lucide="lock" class="w-4 h-4 mt-0.5"></i>
        <div><strong>View only.</strong> Acting from Central Admin needs the <strong>{{ $centralRole }}</strong> role, so the actions below are greyed out.
          Ask the system administrator to add it to your user account.</div>
      </div>
    @endunless

    @include('cadastral_dept.partials._stat_cards')

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-6">
      <input type="text" name="q" value="{{ $term }}" placeholder="File no., ref or title"
             class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
      <select name="desk" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All desks</option>
        @foreach ($def->desks() as $k => $d)
          <option value="{{ $k }}" @selected($filterDesk === $k)>{{ $d['short'] }}{{ empty($d['internal']) ? ' (other department)' : '' }} ({{ (int) ($deskCounts[$k] ?? 0) }})</option>
        @endforeach
      </select>
      <select name="workflow" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All application types</option>
        @foreach ($def->workflows() as $k => $w)
          <option value="{{ $k }}" @selected($filterWorkflow === $k)>{{ $w['label'] }}</option>
        @endforeach
      </select>
      <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
        <input type="checkbox" name="stuck" value="1" @checked($stuck) class="rounded border-gray-300"> Waiting over {{ $stuckDays }} days
      </label>
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Filter</button>
      @if ($term !== '' || $filterWorkflow || $filterDesk || $stuck)
        <a href="{{ route('cadastral-dept.central-admin.index') }}" class="text-sm text-gray-600 hover:underline">Clear</a>
      @endif
    </form>

    <div class="rounded-xl border border-gray-200 bg-white">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-2 whitespace-nowrap">@if ($canAct)<input type="checkbox" class="cd-batch-all h-3.5 w-3.5 mr-1.5 align-middle rounded border-gray-300" aria-label="Select all">@endif S/N</th>
            <th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Type</th><th class="px-4 py-2">With</th><th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Last moved</th>
            <th class="px-4 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse (($rows ?? collect()) as $case)
            @php
              $m = $menus[$case->id] ?? null;
              $back = $case->sentBack();
              $isAway = ! ($def->desks()[$case->current_desk]['internal'] ?? false);
              $isStuck = $case->updated_at && $case->updated_at->lt(now()->subDays($stuckDays));
              // Batch Send (_batch_send): the row's own menu actions, only when the menu offers them.
              $cdBatch = ($canAct && $m && ! $m['tasks_open'] && ! $m['failing'] && ! $m['custody'])
                  ? array_map(fn ($a) => ['o' => $a['outcome'], 'l' => $a['label'], 'r' => (bool) $a['reason']], $m['actions'])
                  : [];
            @endphp
            <tr class="hover:bg-gray-50 align-top">
              <td class="px-4 py-2 text-gray-500 whitespace-nowrap">
                @if ($canAct)
                  <input type="checkbox" class="cd-batch-check h-3.5 w-3.5 mr-1.5 align-middle rounded border-gray-300" value="{{ $case->id }}"
                         data-file="{{ $case->file_number }}" data-url="{{ route('cadastral-dept.cases.move.save', $case->id) }}"
                         data-actions='@json($cdBatch)' aria-label="Select {{ $case->file_number }}">
                @endif{{ $rows->firstItem() + $loop->index }}
              </td>
              <td class="px-4 py-2 font-mono whitespace-nowrap">
                {{ $case->case_ref }}
                @if ($case->duplicate_flag)
                  <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-900" title="{{ $case->duplicate_note }}">Duplicate?</span>
                @endif
              </td>
              <td class="px-4 py-2 font-mono">{{ $case->file_number }}</td>
              <td class="px-4 py-2">{{ $case->file_title }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{!! $def->wfChip($case->workflow) !!} <span class="text-xs text-gray-500">{{ $case->fileTypeLabel() }}</span></td>
              <td class="px-4 py-2">
                <div class="font-semibold text-gray-800">{{ $case->deskShort() }}</div>
                <div class="text-xs text-gray-500">{{ $case->stepLabel() }}</div>
              </td>
              <td class="px-4 py-2">
                @if ($isAway)
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-800">Out with {{ $case->deskShort() }}</span>
                @elseif ($m && $m['head']['awaiting'])
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-800">With the {{ $m['head']['head'] }} for approval</span>
                @elseif ($back)
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800">Sent back by {{ $back['desk'] }}</span>
                @elseif ($m && $m['tasks_open'])
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-900">{{ $m['tasks_open'] }} task(s) open</span>
                @elseif ($m && $m['failing'])
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-900" title="{{ implode('; ', $m['failing']) }}">Not ready</span>
                @else
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800">Ready to send</span>
                @endif
                @if ($back && $back['reason'])
                  <div class="text-xs text-red-700 mt-0.5">"{{ $back['reason'] }}"</div>
                @endif
              </td>
              <td class="px-4 py-2 whitespace-nowrap {{ $isStuck ? 'text-red-700 font-semibold' : 'text-gray-500' }}">{{ optional($case->updated_at)->diffForHumans() }}</td>
              <td class="px-4 py-2 text-right">
                <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                  <button type="button" @click="open = !open" title="Actions" aria-label="Actions"
                          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                  </button>
                  <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-72 rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
                    <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> {{ $canAct ? 'Open file (tasks)' : 'View' }}</a>
                    @if ($fmcInstalled && $fmcVisible && $case->fmc)
                      <a href="{{ route('cadastral-dept.cases.fmc.show', $case->id) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><i data-lucide="id-card" class="w-4 h-4 text-gray-500"></i> File Movement Card</a>
                    @endif
                    @if ($canAct)
                      @if ($m && $m['question'] && ! $m['decision'])
                        <div class="px-3 pt-2 pb-1 text-xs font-bold text-gray-500 border-t border-gray-100">{{ $m['question'] }}</div>
                      @endif
                      @if ($m && $m['jacket'])
                        {{-- P&D: Commission Field Jacket, then Issue I to S. AD Cad Info: Attach Job and
                             Surveyor, then Issue Instruction to Surveyor (transcript, 2026-10-16). --}}
                        <div class="border-t border-gray-100">
                          @if ($m['jacket'] === 'commission')
                            <form method="POST" action="{{ route('cadastral-dept.cases.field-jacket.store', $case->id) }}"
                                  data-confirm-title="Commission the Computation File?" data-confirm="A Computation File is numbered for {{ $case->file_number }}.">
                              @csrf
                              <button class="w-full text-left px-3 py-2 text-sm text-blue-700 hover:bg-gray-50">
                                <span class="inline-flex items-center gap-2"><i data-lucide="briefcase" class="w-4 h-4"></i> Commission Computation File (Field Jacket)</span>
                                <span class="block text-xs text-gray-500">Then issue the I to S</span>
                              </button>
                            </form>
                          @elseif ($m['jacket'] === 'attach_job')
                            @php $cdFj = \App\Models\CadastralDept\CadastralFieldJacket::query()->where('cadastral_case_id', $case->id)->value('field_jacket_no'); @endphp
                            <button type="button" class="w-full text-left px-3 py-2 text-sm text-blue-700 hover:bg-gray-50"
                                    data-attach-job data-url="{{ route('cadastral-dept.cases.job.store', $case->id) }}"
                                    data-ref="{{ $case->case_ref }}" data-file="{{ $case->file_number }}" data-plot="{{ $case->plot_no }}"
                                    data-district="{{ $case->district }}" data-lga="{{ $case->lga }}" data-jacket="{{ $cdFj }}">
                              <span class="inline-flex items-center gap-2"><i data-lucide="link" class="w-4 h-4"></i> Attach Job and Surveyor</span>
                              <span class="block text-xs text-gray-500">From the Job Book; then issue the Instruction to Surveyor</span>
                            </button>
                          @else
                            <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="block px-3 py-2 text-sm text-blue-700 hover:bg-gray-50">
                              <span class="inline-flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> {{ $m['jacket'] === 'pnd_its' ? 'Issue I to S' : 'Issue Instruction to Surveyor' }}</span>
                              <span class="block text-xs text-gray-500">On the file page</span>
                            </a>
                          @endif
                        </div>
                      @endif
                      @if ($m && $m['custody'])
                        {{-- Registry: Attach Land File / Detach Correspondence, then Send to Deeds appears. --}}
                        @php
                          $cdCustLabel = $m['custody'] === 'detach' ? 'Detach Correspondence' : 'Attach Land File';
                          $cdCorr = $case->shadow_fileno ?: $case->correspondence_fileno;
                        @endphp
                        {{-- Opens the side-by-side preview (partials/_custody_preview), which submits. --}}
                        <div class="border-t border-gray-100">
                          <button type="button" class="w-full text-left px-3 py-2 text-sm text-blue-700 hover:bg-gray-50"
                                  data-custody-open="{{ $m['custody'] }}" data-url="{{ route('cadastral-dept.cases.correspondence.custody.save', $case->id) }}"
                                  data-ref="{{ $case->case_ref }}" data-file="{{ $case->file_number }}" data-title="{{ $case->file_title }}"
                                  data-corr="{{ $case->correspondence_fileno }}" data-csf="{{ $case->shadow_fileno }}"
                                >
                            <span class="inline-flex items-center gap-2"><i data-lucide="{{ $m['custody'] === 'detach' ? 'unlink' : 'link' }}" class="w-4 h-4"></i> {{ $cdCustLabel }}</span>
                            <span class="block text-xs text-gray-500">{{ $m['tasks_open'] ? 'Then finish ' . $m['tasks_open'] . ' more task(s)' : ($m['custody'] === 'detach' ? 'Then Send to Deeds' : 'Then send on') }}</span>
                          </button>
                        </div>
                      @elseif ($m && $m['tasks_open'] && $m['edms'])
                        {{-- Back from Land: scan and page-type the new pages (EdmsUpdate), on the file page. --}}
                        <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="block px-3 py-2 text-sm text-blue-700 hover:bg-gray-50 border-t border-gray-100">
                          <span class="inline-flex items-center gap-2"><i data-lucide="scan-line" class="w-4 h-4"></i> Update DMS</span>
                          <span class="block text-xs text-gray-500">Scan and page-type the pages added at Land</span>
                        </a>
                      @elseif ($m && $m['tasks_open'])
                        <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="block px-3 py-2 text-sm text-yellow-800 hover:bg-yellow-50">
                          Finish {{ $m['tasks_open'] }} task(s) first
                        </a>
                      @elseif ($m && $m['failing'])
                        <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="block px-3 py-2 text-sm text-yellow-800 hover:bg-yellow-50">
                          Not ready: {{ implode('; ', $m['failing']) }}
                        </a>
                      @elseif ($m && $m['decision'])
                        {{-- One item for the decision; its pop-up offers the answers. --}}
                        <button type="button"
                                @click="open = false; ask(@js([
                                    'url' => route('cadastral-dept.cases.move.save', $case->id),
                                    'ref' => $case->case_ref, 'file' => $case->file_number,
                                    'label' => $m['decision'], 'question' => $m['question'],
                                    'choices' => array_map(fn ($a) => [
                                        'outcome' => $a['outcome'], 'label' => $a['label'], 'to' => $a['to'],
                                        'reason' => (bool) $a['reason'], 'danger' => $a['reason'] || $a['ends'] === 'returned',
                                    ], $m['actions']),
                                ]))"
                                class="w-full text-left px-3 py-2 text-sm text-blue-700 hover:bg-gray-50 border-t border-gray-100">
                          <span class="inline-flex items-center gap-2"><i data-lucide="check-square" class="w-4 h-4"></i> {{ $m['decision'] }}</span>
                          <span class="block text-xs text-gray-500">{{ collect($m['actions'])->pluck('label')->implode(' or ') }}</span>
                        </button>
                      @elseif ($m)
                        @foreach ($m['actions'] as $a)
                          <button type="button"
                                  @click="open = false; ask(@js([
                                      'url' => route('cadastral-dept.cases.move.save', $case->id),
                                      'ref' => $case->case_ref, 'file' => $case->file_number,
                                      'outcome' => $a['outcome'], 'label' => $a['label'],
                                      'question' => $m['question'], 'reason' => $a['reason'], 'to' => $a['to'],
                                      'danger' => $a['reason'] || $a['ends'] === 'returned',
                                  ]))"
                                  class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 {{ $a['reason'] || $a['ends'] === 'returned' ? 'text-red-700' : 'text-blue-700' }}">
                            {{ $a['label'] }}
                            @unless (str_contains($a['label'], $a['to']))
                              <span class="block text-xs text-gray-500">Send to {{ $a['to'] }}</span>
                            @endunless
                          </button>
                        @endforeach
                      @endif
                      @if ($m && $m['head']['required'] && ! $m['head']['is_head'])
                        {{-- Head-of-unit approval: the step's real actions, greyed out for staff (HeadApproval). --}}
                        @if ($m['head']['awaiting'])
                          <div class="px-3 py-2 text-xs text-purple-800 border-t border-gray-100">With the {{ $m['head']['head'] }} for approval.</div>
                        @endif
                        @foreach ($m['head']['locked'] as $cdLocked)
                          <div class="px-3 py-2 text-sm text-gray-400 cursor-not-allowed" title="Only the {{ $m['head']['head'] }} sends the file on">
                            <i data-lucide="lock" class="w-3.5 h-3.5 inline -mt-0.5"></i> {{ $cdLocked['label'] }}
                          </div>
                        @endforeach
                      @endif
                    @elseif ($m)
                      {{-- No Central Admin role: what the file can do next, greyed out. --}}
                      <div class="px-3 pt-2 pb-1 text-xs text-gray-500 border-t border-gray-100">Needs the {{ $centralRole }} role</div>
                      @php
                        $cdGrey = $m['tasks_open'] ? ['Finish ' . $m['tasks_open'] . ' task(s)']
                            : array_column(array_merge($m['actions'], $m['head']['locked'] ?? []), 'label');
                      @endphp
                      @foreach (array_unique($cdGrey) as $cdLabel)
                        <div class="px-3 py-2 text-sm text-gray-400 cursor-not-allowed"><i data-lucide="lock" class="w-3.5 h-3.5 inline -mt-0.5"></i> {{ $cdLabel }}</div>
                      @endforeach
                    @endif
                  </div>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">No open files{{ ($term !== '' || $filterWorkflow || $filterDesk || $stuck) ? ' match the filter' : '' }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($rows && $rows->hasPages())
      <div class="mt-4">{{ $rows->links() }}</div>
    @endif
  </div>

  {{-- Confirmation pop-up for every action. A decision lists its answers to pick from;
       an answer that sends the file back needs its reason here. --}}
  <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" @keydown.escape.window="modal = null">
    <form method="POST" :action="modal && modal.url" class="w-full max-w-md rounded-xl bg-white shadow-xl p-5 space-y-4" @click.outside="modal = null">
      @csrf
      <input type="hidden" name="outcome" :value="cur() && cur().outcome">
      <div>
        <div class="text-xs font-bold uppercase text-gray-500" x-text="modal && (modal.ref + ' · ' + modal.file)"></div>
        <div class="text-lg font-bold text-gray-800 mt-1" x-text="modal && modal.label"></div>
        <div class="text-sm text-gray-600 mt-1" x-show="modal && modal.question" x-text="modal && modal.question"></div>
      </div>
      <template x-if="modal && modal.choices">
        <div class="space-y-2">
          <template x-for="(c, i) in modal.choices" :key="c.outcome">
            <label class="flex items-start gap-3 rounded-lg border px-3 py-2 cursor-pointer"
                   :class="pick === i ? (c.danger ? 'border-red-400 bg-red-50' : 'border-blue-400 bg-blue-50') : 'border-gray-200'">
              <input type="radio" name="cd_pick" class="mt-1" :value="i" x-model.number="pick">
              <span>
                <span class="block text-sm font-semibold" :class="c.danger ? 'text-red-700' : 'text-gray-800'" x-text="c.label"></span>
                <span class="block text-xs text-gray-500" x-text="'Send to ' + c.to"></span>
              </span>
            </label>
          </template>
        </div>
      </template>
      <div class="text-sm text-gray-600" x-show="modal && ! modal.choices && modal.to">Send the file to <strong x-text="modal && modal.to"></strong>.</div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">
          <span x-text="cur() && cur().reason ? 'Reason' : 'Remarks (optional)'"></span>
          <span class="text-red-600" x-show="cur() && cur().reason">*</span>
        </label>
        <textarea name="remarks" rows="3" x-model="remarks" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  :placeholder="cur() && cur().reason ? 'Say why. The desk that receives the file sees this.' : ''"></textarea>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" @click="modal = null" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
        <button type="submit" :disabled="! cur() || (cur().reason && remarks.trim() === '')"
                :class="cur() && cur().danger ? 'bg-red-600' : 'bg-blue-600'"
                class="px-4 py-2 rounded-lg text-white text-sm font-semibold disabled:opacity-50"
                x-text="cur() ? cur().label : 'Choose one'"></button>
      </div>
    </form>
  </div>
  @include('admin.footer')
</div>
@if ($canAct)
  @include('cadastral_dept.partials._custody_preview')
  @include('cadastral_dept.partials._batch_send')
  @if ($waitingJobs !== null)
    @include('cadastral_dept.field_jackets._attach_job_modal')
  @endif
@endif
@endsection

@push('scripts')
<script>
  function cdDesk() {
    return {
      modal: null, remarks: '', pick: null,
      ask(m) { this.remarks = ''; this.pick = null; this.modal = m; },
      // The answer being confirmed: the one picked for a decision, else the single action.
      cur() { return !this.modal ? null : (this.modal.choices ? this.modal.choices[this.pick] || null : this.modal); },
    };
  }
</script>
@endpush
