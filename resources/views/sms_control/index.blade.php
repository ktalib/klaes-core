@extends('layouts.app')

@php
  /*
   | One blade, two pages.
   |
   | $senderGroup null  -> /sms-control, every message, wallet shown.
   | $senderGroup set   -> /sms-management/{sender}, the System Admin sub-module:
   |                       only the messages that go out under that sender ID,
   |                       and NO wallet -- the prepaid account is shared by
   |                       every sender, so a balance printed under a sender's
   |                       name would be read as that sender's own credit.
   |
   | Defaulted here rather than assumed, so the view still renders if anything
   | reaches it without them.
   */
  $senderGroup = $senderGroup ?? null;
  $senderId = $senderId ?? null;
  $showCredits = $showCredits ?? true;
  $senderRegistered = $senderRegistered ?? true;
  $senderTabs = \App\Models\SmsSetting::senderOptions();
@endphp

@section('page-title', $senderId ? 'SMS Management - ' . $senderId : 'SMS Control Centre')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header')

  <div
    x-data="smsControl()"
    x-init="load(); loadLog()"
    x-cloak
    class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
  >
    <div>
      <h1 class="text-2xl font-semibold text-slate-800">
        {{ $senderId ? 'SMS Management' : 'SMS Control Centre' }}
        @if($senderId)
          <span class="text-slate-400 font-normal">&middot;</span>
          <span class="font-mono">{{ $senderId }}</span>
        @endif
      </h1>
      <p class="text-sm text-slate-500 mt-1">
        @if($senderId)
          The messages that go out to a handset as <span class="font-mono">{{ $senderId }}</span>.
          Switch each one on or off and edit its wording.
        @else
          Switch each message on or off, edit its wording, and see what it costs before you enable it.
        @endif
      </p>
    </div>

    {{--
      The three sender names, so an officer can move between them without going
      back to the menu. Rendered from the same config the "Sent as" dropdown
      uses, so a sender added later appears here on its own.
    --}}
    @if($senderGroup !== null)
      <div class="flex flex-wrap items-center gap-2">
        @foreach($senderTabs as $group => $id)
          <a href="{{ route('sms-management.index', ['sender' => strtolower($id)]) }}"
             class="rounded-full px-4 py-1.5 text-sm font-medium font-mono border transition-colors
                    {{ $group === $senderGroup
                        ? 'bg-blue-600 border-blue-600 text-white'
                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            {{ $id }}
          </a>
        @endforeach
        <a href="{{ route('sms-control.index') }}"
           class="ml-2 text-sm text-slate-500 hover:text-slate-700">All senders</a>
      </div>

      {{--
        An unregistered sender ID is the one failure on this gateway that looks
        like success: Bulk-SMS.ng accepts the message, bills for it and delivers
        it to nobody, which is indistinguishable from every other reason a
        handset stays silent. Named here rather than left to be discovered.
      --}}
      @unless($senderRegistered)
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
          <span class="font-semibold font-mono">{{ $senderId }}</span> is not on this server's list of
          sender IDs confirmed with Bulk-SMS.ng. The gateway <span class="font-semibold">accepts and bills</span>
          a message sent under an unregistered name and then delivers it to nobody, and that looks exactly
          like every other delivery failure. Confirm the name with the vendor, then add it to
          <code class="bg-amber-100 px-1 rounded">KLAES_SMS_REGISTERED_SENDERS</code> to clear this notice.
        </div>
      @endunless
    @endif

    {{--
      Gateway strip. This is deliberately the first thing on the page: a missing
      credential, an empty wallet and a switched-off message all look identical
      from the outside — nothing arrives — so the page says which one it is
      rather than showing toggles that would silently do nothing.
    --}}
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="flex flex-wrap items-center gap-6">
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Gateway</div>
          <div class="text-sm text-slate-800 mt-1">Bulk-SMS.ng &middot; promotional route</div>
        </div>
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Sender ID</div>
          @if($senderId)
            <div class="text-sm text-slate-800 mt-1 font-mono">{{ $senderId }}</div>
          @else
            <div class="text-sm text-slate-800 mt-1" x-text="state.gateway?.sender || '—'"></div>
          @endif
        </div>
        {{--
          Wallet only on the unfiltered page. The prepaid account is shared by
          every sender ID, so a balance here would read as this sender's credit.
        --}}
        @if($showCredits)
          <div>
            <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Wallet</div>
            <div class="text-sm mt-1"
                 :class="state.gateway?.balance ? 'text-slate-800' : 'text-amber-700'"
                 x-text="state.gateway?.balance || 'unknown'"></div>
          </div>
        @endif
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            Today{{ $senderId ? ', as ' . $senderId : '' }}
          </div>
          <div class="text-sm text-slate-800 mt-1" x-text="todaySummary()"></div>
        </div>

        <div class="ml-auto flex items-center gap-3">
          {{--
            The master switch is global. On a sender page it is labelled as such,
            because switching it off here silences all three senders, not this one.
          --}}
          <span class="text-sm font-medium text-slate-700">{{ $senderId ? 'All messages, every sender' : 'All messages' }}</span>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only"
                   :checked="state.master"
                   @change="saveMaster($event.target.checked)">
            <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
          </label>
        </div>
      </div>

      <template x-if="state.gateway && state.gateway.problem">
        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800"
             x-text="state.gateway.problem"></div>
      </template>

      <template x-if="!state.master">
        <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600">
          The master switch is off, so nothing is sent{{ $senderId ? ' — under any sender ID —' : '' }}
          no matter how the individual messages below are set.
        </div>
      </template>
    </div>

    {{--
      A sender with nothing on it. KANGIS starts here: it is a name to send
      under, not a set of messages, until one is moved onto it.
    --}}
    <template x-if="state.loaded && !Object.keys(state.groups || {}).length">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
        <p class="text-sm text-slate-600">
          @if($senderId)
            No message goes out as <span class="font-mono font-semibold">{{ $senderId }}</span> yet.
          @else
            There are no messages in the catalogue.
          @endif
        </p>
        @if($senderId)
          <p class="text-sm text-slate-500 mt-2">
            To move one here, open the sender it is on now and change its
            <span class="font-medium">Sent as</span> to <span class="font-mono">{{ $senderId }}</span>.
          </p>
        @endif
      </div>
    </template>

    <template x-for="(messages, group) in state.groups" :key="group">
      <div class="space-y-4">
        <h2 class="text-sm font-semibold tracking-wide text-slate-500 uppercase" x-text="group"></h2>

        <template x-for="m in messages" :key="m.key">
          <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">

            <div class="flex items-start gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="text-base font-semibold text-slate-800" x-text="m.label"></h3>
                  <span class="text-[11px] px-2 py-0.5 rounded-full"
                        :class="m.audience === 'officer' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700'"
                        x-text="m.audience === 'officer' ? 'to staff' : 'to applicant'"></span>
                  {{-- The name the recipient sees the message come from. --}}
                  <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-mono"
                        x-text="m.sender_id"></span>
                </div>
                <p class="text-sm text-slate-500 mt-1" x-text="m.recipient"></p>
                <template x-if="m.always_on">
                  <p class="text-xs text-slate-500 mt-1">
                    <span class="font-medium">Always on.</span>
                    This is the code people need in order to sign in, so it cannot be switched off here.
                    Edit the wording freely; to pause phone verification entirely, set
                    <code class="bg-slate-100 px-1 rounded">PHONE_VERIFICATION_ENABLED=false</code>.
                  </p>
                </template>
              </div>

              {{--
                An always-on message (the sign-in code) shows its state but
                cannot be switched off here -- doing so would leave nobody able
                to sign in and turn it back on. The endpoint refuses it too.
              --}}
              <label class="relative inline-flex items-center mt-1"
                     :class="m.always_on ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
                     :title="m.always_on ? 'Always on — this is the code people need to sign in' : ''">
                <input type="checkbox" class="peer sr-only"
                       :checked="m.enabled"
                       :disabled="m.always_on === true"
                       @change="m.enabled = $event.target.checked; save(m)">
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
              </label>
            </div>

            {{-- Staff attendance wording lives in code, so those cards show no editor. --}}
            <template x-if="!m.toggle_only">
              <div class="mt-4 space-y-3">
                <div>
                  <label class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Wording</label>
                  <textarea rows="3"
                            class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none"
                            x-model="m.template"
                            @input="repreview(m)"></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-xs text-slate-400">Insert:</span>
                  <template x-for="t in m.tokens" :key="t">
                    <button type="button"
                            class="text-[11px] px-2 py-1 rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200"
                            @click="m.template = (m.template || '') + ' [' + t + ']'; repreview(m)"
                            x-text="'[' + t + ']'"></button>
                  </template>
                </div>

                {{--
                  Live preview with the page count beside it. These wordings are
                  fixed by the Ministry and most run past 160 characters, so the
                  page shows what that costs rather than hiding it — an editor who
                  trims a sentence should be able to see the page drop.
                --}}
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                  <div class="text-sm text-slate-700 whitespace-pre-wrap" x-text="m.preview || '—'"></div>
                  <div class="mt-2 text-xs" :class="m.pages > 1 ? 'text-amber-700' : 'text-slate-400'">
                    <span x-text="m.characters"></span> characters &middot;
                    <span x-text="m.pages"></span> <span x-text="m.pages === 1 ? 'page' : 'pages'"></span>
                    <template x-if="m.pages > 1"><span> — this message bills as <span x-text="m.pages"></span> SMS per send</span></template>
                  </div>
                </div>

                {{--
                  Batch wording. Shown only where one message can cover several
                  files -- a commissioning batch for one applicant. [FileNo] then
                  carries a range, so this form stays two pages whether it is
                  about 2 files or 200.
                --}}
                <template x-if="m.has_plural">
                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <label class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Batch wording <span class="normal-case font-normal text-slate-400">— used when one message covers several files</span>
                    </label>
                    <textarea rows="3"
                              class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none"
                              x-model="m.plural_template"
                              @input="repreviewPlural(m)"></textarea>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                      <div class="text-sm text-slate-700 whitespace-pre-wrap" x-text="m.plural_preview || '—'"></div>
                      <div class="mt-2 text-xs" :class="m.plural_pages > 1 ? 'text-amber-700' : 'text-slate-400'">
                        <span x-text="m.plural_characters"></span> characters &middot;
                        <span x-text="m.plural_pages"></span> <span x-text="m.plural_pages === 1 ? 'page' : 'pages'"></span>
                      </div>
                    </div>
                  </div>
                </template>

                <div class="flex flex-wrap items-center gap-3">
                  <button type="button"
                          class="inline-flex items-center justify-center rounded-full bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700"
                          @click="save(m)">Save</button>

                  <button type="button"
                          class="text-sm text-slate-500 hover:text-slate-700"
                          x-show="!m.is_default"
                          @click="m.template = ''; save(m)">Reset to the shipped wording</button>

                  {{--
                    Sender ID. A fixed two-way choice, never free text: an
                    unregistered sender is accepted and billed by the gateway and
                    then delivered to nobody, which looks exactly like every
                    other delivery failure.
                  --}}
                  <label class="flex items-center gap-2 text-sm text-slate-600"
                         @if($senderId) title="Changing this moves the message to that sender's page" @endif>
                    <span>Sent as</span>
                    <select class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            x-model="m.sender_group"
                            @change="save(m)">
                      <template x-for="(id, group) in state.senders" :key="group">
                        <option :value="group" x-text="id"></option>
                      </template>
                    </select>
                  </label>

                  <div class="ml-auto flex items-center gap-2">
                    <input type="text" placeholder="08031234567"
                           class="w-40 rounded-xl border border-slate-200 px-3 py-2 text-sm"
                           x-model="m.testPhone">
                    <button type="button"
                            class="rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            @click="sendTest(m)">Send test</button>
                  </div>
                </div>
              </div>
            </template>

            {{--
              The last few sends. 'skipped' is shown as prominently as 'failed'
              on purpose: "no phone number on file" is the commonest reason a
              message never arrives, and it is not a failure anyone would
              otherwise go looking for.
            --}}
            <template x-if="m.recent && m.recent.length">
              <div class="mt-4 border-t border-slate-100 pt-3">
                <div class="text-xs font-semibold tracking-wide text-slate-400 uppercase mb-2">Recent</div>
                <div class="space-y-1">
                  <template x-for="r in m.recent" :key="r.id">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                      <span class="px-2 py-0.5 rounded-full font-medium"
                            :class="{
                              'bg-emerald-50 text-emerald-700': r.status === 'sent',
                              'bg-rose-50 text-rose-700': r.status === 'failed',
                              'bg-amber-50 text-amber-700': r.status === 'skipped',
                              'bg-slate-100 text-slate-600': r.status === 'pending'
                            }"
                            x-text="r.status"></span>
                      <span class="text-slate-500" x-text="r.at"></span>
                      <span class="text-slate-400" x-text="r.file_number || r.phone || ''"></span>
                      <span class="text-slate-400" x-text="r.reason || ''"></span>
                    </div>
                  </template>
                </div>
              </div>
            </template>

          </div>
        </template>
      </div>
    </template>

    {{--
      The dispatch log.

      The per-card "Recent" list answers "did this message go out"; this table
      answers "what has this sender actually sent, and what happened to it".
      'skipped' is given the same weight as 'failed' throughout: no phone number
      on file is the commonest reason an applicant is never told anything, and
      it is not a fault anyone would otherwise go looking for.
    --}}
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
      <div class="flex flex-wrap items-center gap-3 p-5 border-b border-slate-100">
        <div>
          <h2 class="text-base font-semibold text-slate-800">
            Dispatch log
            @if($senderId)
              <span class="text-slate-400 font-normal">&middot;</span>
              <span class="font-mono">{{ $senderId }}</span>
            @endif
          </h2>
          <p class="text-xs text-slate-500 mt-0.5">
            Every send, newest first.
            <span class="text-slate-400">
              &ldquo;Delivered&rdquo; means the gateway accepted it — this account gets no delivery receipt,
              so a DND-blocked handset receives nothing and still reads as delivered here.
            </span>
          </p>
        </div>

        {{-- Tallies over the whole filtered set, not the page on screen. --}}
        <div class="flex flex-wrap items-center gap-2 ml-auto">
          <template x-for="s in ['sent', 'failed', 'skipped', 'pending']" :key="s">
            <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                  x-show="logState.counts[s]"
                  :class="{
                    'bg-emerald-50 text-emerald-700': s === 'sent',
                    'bg-rose-50 text-rose-700': s === 'failed',
                    'bg-amber-50 text-amber-700': s === 'skipped',
                    'bg-slate-100 text-slate-600': s === 'pending'
                  }">
              <span x-text="logState.counts[s]"></span> <span x-text="s"></span>
            </span>
          </template>
          <button type="button"
                  class="text-sm text-slate-500 hover:text-slate-700"
                  @click="loadLog(logState.current_page)">Refresh</button>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3 px-5 py-3 border-b border-slate-100 bg-slate-50/60">
        <select class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                x-model="logFilters.key"
                @change="loadLog(1)">
          <option value="">All messages</option>
          <template x-for="(label, key) in logState.messages" :key="key">
            <option :value="key" x-text="label"></option>
          </template>
        </select>

        <select class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                x-model="logFilters.status"
                @change="loadLog(1)">
          <option value="">Any outcome</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
          <option value="skipped">Skipped</option>
          <option value="pending">Pending</option>
        </select>

        <input type="text"
               placeholder="File number or phone"
               class="w-56 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
               x-model="logFilters.file_number"
               @input.debounce.400ms="loadLog(1)">

        <button type="button"
                class="text-sm text-slate-500 hover:text-slate-700"
                x-show="logFilters.key || logFilters.status || logFilters.file_number"
                @click="logFilters = { key: '', status: '', file_number: '' }; loadLog(1)">Clear</button>

        <div class="ml-auto text-xs text-slate-500" x-show="logState.total">
          <span x-text="logState.from"></span>&ndash;<span x-text="logState.to"></span>
          of <span x-text="logState.total"></span>
        </div>
      </div>

      {{-- Wide content scrolls inside its own box rather than the page. --}}
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100">
              <th class="font-semibold px-5 py-2.5 whitespace-nowrap">When</th>
              <th class="font-semibold px-3 py-2.5">Message</th>
              <th class="font-semibold px-3 py-2.5 whitespace-nowrap">Outcome</th>
              <th class="font-semibold px-3 py-2.5 whitespace-nowrap">To</th>
              <th class="font-semibold px-3 py-2.5 whitespace-nowrap">File</th>
              <th class="font-semibold px-3 py-2.5">Gateway said</th>
              <th class="font-semibold px-5 py-2.5 whitespace-nowrap">By</th>
            </tr>
          </thead>
          <template x-for="r in logState.rows" :key="r.id">
            <tbody>
                <tr class="border-b border-slate-50 hover:bg-slate-50/70 cursor-pointer align-top"
                    @click="openRow = (openRow === r.id ? null : r.id)">
                  <td class="px-5 py-3 whitespace-nowrap text-slate-500" x-text="r.at"></td>
                  <td class="px-3 py-3 text-slate-700">
                    <span x-text="r.label"></span>
                    {{-- A test send is not traffic. Marked so it is not read as one. --}}
                    <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500"
                          x-show="r.is_test">test</span>
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                          :class="{
                            'bg-emerald-50 text-emerald-700': r.status === 'sent',
                            'bg-rose-50 text-rose-700': r.status === 'failed',
                            'bg-amber-50 text-amber-700': r.status === 'skipped',
                            'bg-slate-100 text-slate-600': r.status === 'pending'
                          }"
                          x-text="r.status"></span>
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap text-slate-600 font-mono text-xs" x-text="r.phone || '—'"></td>
                  <td class="px-3 py-3 whitespace-nowrap text-slate-600" x-text="r.file_number || '—'"></td>
                  <td class="px-3 py-3"
                      :class="r.status === 'failed' ? 'text-rose-700' : 'text-slate-500'"
                      x-text="r.outcome_text"></td>
                  <td class="px-5 py-3 whitespace-nowrap text-slate-400" x-text="r.by || '—'"></td>
                </tr>

                {{--
                  The wording as it went out. Worth keeping visible: an edit to a
                  message does not rewrite what was already sent, so this row and
                  the card above it can legitimately disagree.
                --}}
                <tr x-show="openRow === r.id" class="border-b border-slate-100 bg-slate-50">
                  <td colspan="7" class="px-5 py-3">
                    <div class="text-xs font-semibold tracking-wide text-slate-400 uppercase mb-1">Sent as</div>
                    <div class="text-sm text-slate-700 whitespace-pre-wrap" x-text="r.message || 'No wording was recorded for this row.'"></div>
                    {{--
                      What the gateway actually returned. Kept because the column
                      above is deliberately plain: 604 reads "Failed" there, but
                      it is the one failure with a remedy — the account is out of
                      credit — and that must stay findable.
                    --}}
                    <div class="mt-3 text-xs text-slate-500" x-show="r.gateway_detail">
                      <span class="font-semibold tracking-wide text-slate-400 uppercase">Gateway</span>
                      <span x-text="r.gateway_detail"></span>
                    </div>
                    <div class="mt-2 text-xs text-slate-400">
                      <span x-show="r.attempts">attempt <span x-text="r.attempts"></span> &middot; </span>
                      <span x-text="r.key"></span>
                    </div>
                  </td>
                </tr>
            </tbody>
          </template>
        </table>
      </div>

      <div class="px-5 py-4 text-sm text-slate-500 text-center" x-show="logState.loading">Loading&hellip;</div>

      <div class="px-5 py-8 text-sm text-center" x-show="logState.error">
        <span class="text-amber-700" x-text="logState.error"></span>
      </div>

      <div class="px-5 py-8 text-sm text-slate-500 text-center"
           x-show="!logState.loading && !logState.error && !logState.rows.length">
        <template x-if="logFilters.key || logFilters.status || logFilters.file_number">
          <span>Nothing matches those filters.</span>
        </template>
        <template x-if="!(logFilters.key || logFilters.status || logFilters.file_number)">
          <span>
            @if($senderId)
              Nothing has been sent as <span class="font-mono">{{ $senderId }}</span> yet.
            @else
              Nothing has been sent yet.
            @endif
          </span>
        </template>
      </div>

      <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100"
           x-show="logState.last_page > 1">
        <button type="button"
                class="rounded-full border border-slate-300 px-4 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="logState.current_page <= 1"
                @click="loadLog(logState.current_page - 1)">Previous</button>

        <span class="text-xs text-slate-500">
          Page <span x-text="logState.current_page"></span> of <span x-text="logState.last_page"></span>
        </span>

        <button type="button"
                class="rounded-full border border-slate-300 px-4 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="logState.current_page >= logState.last_page"
                @click="loadLog(logState.current_page + 1)">Next</button>
      </div>
    </div>
  </div>
</div>

<script>
/*
 | Vanilla Alpine, no build step — the layout already loads Alpine 3 and Tailwind
 | 2 from a CDN, so only classes that already appear elsewhere in this app are
 | guaranteed to exist in that build.
 |
 | The preview shown while typing is rendered here, client-side, and is a
 | deliberate approximation: the authoritative render is KlaesSmsDispatcher's,
 | and the page re-reads it from the server after every save. The two agree on
 | the rule that matters — a token nobody supplies is emptied rather than left as
 | a literal "[Foo]" in somebody's inbox.
 */
function smsControl() {
  return {
    // `loaded` keeps the empty state from flashing before the first fetch
    // returns -- an unfetched page and a sender with no messages both start out
    // with an empty groups map.
    state: { master: false, groups: {}, gateway: null, today: {}, loaded: false },

    /*
     | The dispatch log, paged server-side. Kept apart from `state` because the
     | two refresh on different triggers: saving a message re-reads state, but
     | leaves the log alone; paging the log leaves the cards alone.
     */
    logState: {
      rows: [], counts: {}, messages: {},
      total: 0, current_page: 1, last_page: 1, from: 0, to: 0,
      loading: false, error: null,
    },

    logFilters: { key: '', status: '', file_number: '' },

    // Which row has its wording opened. One at a time.
    openRow: null,

    /*
     | Rendered straight in by Blade rather than read off a data-* attribute.
     |
     | The attribute version used `this.$el`, which in Alpine 3 is the element
     | of the CURRENTLY EVALUATING expression -- the root div under x-init, but
     | the button under @click. So load() worked and every save and test send
     | fetched the string "undefined". This script is inline in the same Blade
     | file, so there is no reason for the indirection at all.
     */
    urls: {
      // The sender narrows the catalogue server-side, so the page never holds
      // messages it is not showing.
      state: '{{ route('sms-control.api.state') }}@if($senderGroup)?sender={{ $senderGroup }}@endif',
      updateBase: '{{ url('/sms-control/api/messages') }}',
      test: '{{ route('sms-control.api.test') }}',
      log: '{{ route('sms-control.api.log') }}',
    },

    // The sender narrows the log server-side too, so a page never shows another
    // sender's traffic.
    sender: @json($senderGroup),

    async load() {
      const res = await fetch(this.urls.state, { headers: { 'Accept': 'application/json' } });
      const body = await res.json();

      if (!body.success) { this.state = { ...this.state, loaded: true }; return; }

      // Sample values come from the server so the typing preview matches the one
      // the server renders after a save.
      Object.values(body.data.groups).forEach(list => list.forEach(m => {
        m.testPhone = '';
        m.sample = m.sample || {};
      }));

      this.state = { ...body.data, loaded: true };
    },

    async loadLog(page = 1) {
      this.logState.loading = true;
      this.logState.error = null;

      const params = new URLSearchParams();
      if (this.sender) { params.set('sender', this.sender); }
      if (this.logFilters.key) { params.set('key', this.logFilters.key); }
      if (this.logFilters.status) { params.set('status', this.logFilters.status); }
      if (this.logFilters.file_number) { params.set('file_number', this.logFilters.file_number); }
      params.set('page', page);

      try {
        const res = await fetch(this.urls.log + '?' + params.toString(), {
          headers: { 'Accept': 'application/json' },
        });
        const body = await res.json();

        if (!body.success) {
          // Say why rather than showing an empty table, which would read as
          // "nothing has ever been sent".
          this.logState.rows = [];
          this.logState.error = body.message || 'The dispatch log could not be read.';
          return;
        }

        this.logState = { ...this.logState, ...body.data, error: null };
      } catch (e) {
        this.logState.rows = [];
        this.logState.error = 'The dispatch log could not be read.';
      } finally {
        this.logState.loading = false;
        this.openRow = null;
      }
    },

    todaySummary() {
      const t = this.state.today || {};
      const parts = Object.keys(t).map(k => t[k] + ' ' + k);
      return parts.length ? parts.join(', ') : 'nothing yet';
    },

    /* A local approximation of the server's substitute(), for live typing. */
    repreview(m) {
      let body = m.template || '';
      body = body.replace(/\[[^\]\[]{1,40}\]/g, '');
      body = body.replace(/[ \t]{2,}/g, ' ').replace(/\s+([.,])/g, '$1').trim();
      m.preview = body;
      m.characters = body.length;
      m.pages = body.length ? Math.max(1, Math.ceil(body.length / 160)) : 0;
    },

    /* Same approximation as repreview(), for the batch box. */
    repreviewPlural(m) {
      let body = m.plural_template || '';
      body = body.replace(/\[[^\]\[]{1,40}\]/g, '');
      body = body.replace(/[ \t]{2,}/g, ' ').replace(/\s+([.,])/g, '$1').trim();
      m.plural_preview = body;
      m.plural_characters = body.length;
      m.plural_pages = body.length ? Math.max(1, Math.ceil(body.length / 160)) : 0;
    },

    async save(m) {
      const res = await fetch(this.urls.updateBase + '/' + m.key, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({
          enabled: m.enabled,
          template: m.template || '',
          plural_template: m.plural_template || '',
          sender: m.sender_group || null,
        }),
      });

      const body = await res.json();

      if (!body.success) {
        Swal.fire({ icon: 'error', title: 'Not saved', text: body.message || 'Could not save this message.' });
        // Re-read, so the page never shows a switch in a state the server rejected.
        await this.load();
        return;
      }

      // Re-read rather than trusting the local copy: the server renders the
      // authoritative preview and recomputes the page count.
      await this.load();
    },

    async saveMaster(enabled) {
      await fetch(this.urls.updateBase + '/master', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ enabled: enabled, template: '' }),
      });

      await this.load();
    },

    async sendTest(m) {
      if (!m.testPhone) {
        Swal.fire({ icon: 'info', title: 'Which number?', text: 'Type a number to send the test to.' });
        return;
      }

      const res = await fetch(this.urls.test, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ key: m.key, phone: m.testPhone }),
      });

      const body = await res.json();

      Swal.fire({
        icon: body.success ? 'success' : 'error',
        title: body.success ? 'Sent' : 'Not sent',
        text: body.message,
      });

      // A test writes a log row either way, so both views are re-read.
      await this.load();
      await this.loadLog(1);
    },
  };
}
</script>
@endsection
