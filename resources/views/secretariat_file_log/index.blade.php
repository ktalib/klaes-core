@extends('layouts.app')

@section('page-title')
    {{ __('File Movement (Department)') }}
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .sfl-tab-active { border-color: #4f46e5 !important; color: #4338ca; font-weight: 600; }
    .sfl-thumb { height: 9rem; }
    .sfl-scroll { max-height: 26rem; overflow-y: auto; }
    .sfl-timeline-dot { width: .875rem; height: .875rem; }
    .sfl-scan-ready { box-shadow: 0 0 0 4px rgba(99, 102, 241, .15); }
</style>
@endpush

@section('content')
<div class="flex-1 overflow-y-auto overflow-x-hidden"
     x-data="secretariatFileLog({
        offices: @js($offices),
        urls: {
            resolve: @js(route('secretariat-file-log.resolve')),
            lists: @js(route('secretariat-file-log.lists')),
            profile: @js(route('secretariat-file-log.profile')),
            deleteLog: @js(route('secretariat-file-log.delete-log')),
            editLog: @js(route('secretariat-file-log.edit-log')),
            editDetails: @js(route('secretariat-file-log.edit-details')),
            deleteTracker: @js(route('secretariat-file-log.delete-tracker')),
            {{-- Relative on purpose: the sheet builder runs in a same-origin frame. --}}
            trackingPage: @js(route('create-file-tracker.index', [], false)),
            trackerSearch: @js(route('create-file-tracker.search', [], false)),
            indexed: @js(route('secretariat-file-log.indexed')),
            receive: @js(route('secretariat-file-log.receive')),
            forward: @js(route('secretariat-file-log.forward')),
        },
        userId: @js(auth()->id()),
        canChooseOffice: @js($canChooseOffice),
        canDeleteLogs: @js($canDeleteLogs ?? false),
        canEditLogs: @js($canEditLogs ?? false),
        myOffice: @js($myOffice),
        requestPurposes: @js($requestPurposes),
        officers: @js($officers),
        userName: @js(trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) ?: (auth()->user()->name ?? '')),
        externalSenders: @js($externalSenders ?? []),
        officerRanks: @js($officerRanks ?? []),
        lgas: @js($lgas ?? []),
     })">

    @include('admin.header')

    {{-- Module banner, same pattern as Log a File --}}
    <div class="bg-gradient-to-r from-indigo-700 via-indigo-600 to-purple-700 px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="book-open-check" class="h-5 w-5 text-white flex-shrink-0"></i>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-white font-bold text-sm uppercase tracking-widest">File Tracking</span>
            <span class="text-indigo-200 text-sm">·</span>
            <span class="text-white text-sm font-medium">File Movement (Department)</span>
            <template x-if="officeLocked">
                <span class="flex items-center gap-2">
                    <span class="text-indigo-200 text-sm">·</span>
                    <span class="text-indigo-100 text-sm" x-text="officeName(office)"></span>
                </span>
            </template>
        </div>
    </div>

    <div class="p-6" x-cloak>
        <div class="container mx-auto py-2 space-y-6">

            {{-- Office (the page title lives in the shared header) --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-end gap-4">

                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="building-2" class="w-5 h-5 text-indigo-600"></i>
                    </div>
                    <template x-if="officeLocked">
                        <div class="flex items-center gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400">My office</div>
                                <div class="font-semibold text-gray-800" x-text="officeName(office)"></div>
                            </div>
                            <button type="button" x-show="canChooseOffice" class="text-xs font-semibold text-indigo-600 hover:underline" @click="officeLocked = false">Change</button>
                        </div>
                    </template>
                    <template x-if="!officeLocked && !canChooseOffice">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-400">My office</div>
                            <div class="font-semibold text-red-600">Not assigned</div>
                        </div>
                    </template>
                    <template x-if="!officeLocked && canChooseOffice">
                        <div class="flex items-center gap-2">
                            <select x-model="office" class="border border-gray-300 rounded-lg text-sm px-2 py-2 w-72">
                                <option value="">— Select your office —</option>
                                <template x-for="group in officeGroups()" :key="group.department">
                                    <optgroup :label="group.department">
                                        <template x-for="o in group.items" :key="o.office_code">
                                            <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                        </template>
                                    </optgroup>
                                </template>
                            </select>
                            <button type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50"
                                    :disabled="!office" @click="lockOffice()">Set office</button>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="!officeLocked && canChooseOffice" class="bg-white border border-dashed border-indigo-300 rounded-xl p-10 text-center">
                <i data-lucide="building" class="w-10 h-10 text-indigo-300 mx-auto"></i>
                <p class="mt-3 text-gray-600 font-medium">Select the office to work as.</p>
                <p class="text-sm text-gray-400">Only administrators can choose; other users are fixed to their own office.</p>
            </div>
            <div x-show="!officeLocked && !canChooseOffice" class="bg-white border border-dashed border-red-300 rounded-xl p-10 text-center">
                <i data-lucide="user-x" class="w-10 h-10 text-red-300 mx-auto"></i>
                <p class="mt-3 text-gray-700 font-medium">No office is assigned to your account.</p>
                <p class="text-sm text-gray-500">Your office comes from the Department and Rank on your user account. Ask the administrator to set them.</p>
            </div>

            <div x-show="officeLocked" class="space-y-6">

                {{-- Dashboard cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <template x-for="card in cards()" :key="card.key">
                        <button type="button" class="bg-white rounded-lg border border-gray-200 p-5 text-left hover:shadow-md transition-shadow focus:outline-none"
                                :class="cardActive(card) ? 'ring-2 ring-indigo-200' : ''"
                                @click="openCard(card)">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600" x-text="card.label"></p>
                                    <p class="text-2xl font-bold" :class="card.text" x-text="listsLoading ? '…' : card.value"></p>
                                </div>
                                <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="card.bg">
                                    <span class="inline-flex" :class="card.text"><i :data-lucide="card.icon" class="h-6 w-6"></i></span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2" x-text="card.hint"></p>
                            <p class="text-xs mt-1 font-semibold text-gray-600" x-show="!listsLoading">
                                <span x-text="'Files ' + card.files"></span><span class="text-gray-300"> · </span><span x-text="'Mails ' + card.memo"></span>
                            </p>
                        </button>
                    </template>
                </div>

                {{-- Scan station: scan box (left) and controls (right). Paused while logging manually. --}}
                <div class="relative bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden transition"
                     :class="manual.open ? 'opacity-50 filter grayscale pointer-events-none select-none' : ''"
                     :aria-disabled="manual.open ? 'true' : 'false'">
                    <div class="h-1 w-full" :class="mode === 'in' ? 'bg-gradient-to-r from-green-600 via-emerald-500 to-green-400' : 'bg-gradient-to-r from-orange-600 via-amber-500 to-orange-400'"></div>
                    <div class="grid grid-cols-1 lg:grid-cols-3">

                        {{-- Left: scan --}}
                        <div class="lg:col-span-2 p-5 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" :class="mode === 'in' ? 'bg-green-100' : 'bg-orange-100'">
                                    <span class="inline-flex" :class="mode === 'in' ? 'text-green-600' : 'text-orange-600'"><i data-lucide="scan-line" class="w-6 h-6"></i></span>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900" x-text="mode === 'in' ? 'Incoming — scan to receive' : 'Outgoing — scan to send'"></h2>
                                    <p class="text-xs text-gray-500">Works with any KLAES QR: tracking sheet, RofO, recommendation, commissioning sheet, consent, file label…</p>
                                </div>
                            </div>

                            <div class="relative w-full lg:w-4/5">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-lucide="qr-code" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="text" x-ref="scanBox" x-model="scan.q" :disabled="manual.open"
                                       @input="onScanInput()" @keydown="onScanKeydown($event)" @paste="onScanPaste()"
                                       @focus="scanFocused = true" @blur="scanFocused = false"
                                       class="w-full border-2 rounded-xl pl-12 pr-24 py-3.5 text-base focus:outline-none"
                                       :class="scanFocused ? 'border-indigo-400 sfl-scan-ready' : 'border-gray-200'"
                                       placeholder="Scan the QR code, or type a file number" autocomplete="off" spellcheck="false">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button type="button" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg disabled:opacity-50"
                                            :disabled="scan.loading || !scan.q.trim()" @click="resolve()">Find</button>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 text-xs" :class="scanFocused ? 'text-green-600' : 'text-gray-400'">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-full w-full rounded-full opacity-75" :class="scanFocused ? 'bg-green-400 animate-ping' : ''"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="scanFocused ? 'bg-green-500' : 'bg-gray-300'"></span>
                                </span>
                                <span x-text="scan.loading ? 'Looking up…' : (scanFocused ? 'Scanner ready — scans load automatically' : 'Click the box to scan')"></span>
                            </div>

                            <template x-if="scan.message">
                                <div class="flex items-start gap-2 text-sm rounded-lg px-3 py-2"
                                     :class="scan.matches.length > 1 ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 'bg-red-50 text-red-700 border border-red-200'">
                                    <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <span x-text="scan.message"></span>
                                        <button type="button" x-show="!scan.matches.length" class="ml-2 underline font-semibold" @click="openManual()">Log manually</button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="scan.matches.length > 1">
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="m in scan.matches" :key="m.file_number">
                                        <button type="button" class="px-3 py-2 rounded-lg border text-left text-sm hover:border-indigo-400"
                                                :class="profile.match && profile.match.file_number === m.file_number ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                                                @click="selectMatch(m)">
                                            <div class="font-semibold text-gray-800" x-text="m.file_number"></div>
                                            <div class="text-xs text-gray-500" x-text="((nice(m.file_title) || '').toUpperCase() || '—') + ' · ' + m.source"></div>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Right: controls --}}
                        <div class="p-5 space-y-4 bg-gray-50 border-t lg:border-t-0 lg:border-l border-gray-100">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Mode</div>
                                {{-- Each control has its own colour: Receive green, Send orange, Batch purple, Log manually blue. Inline, so the Tailwind v2 CDN cannot drop them. --}}
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button type="button" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold shadow-sm"
                                            :style="mode === 'in' ? 'background:#16a34a;color:#fff;border:1px solid #16a34a;' : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;'"
                                            @click="setMode('in')"><i data-lucide="log-in" class="h-4 w-4"></i> Receive</button>
                                    <button type="button" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold shadow-sm"
                                            :style="mode === 'out' ? 'background:#ea580c;color:#fff;border:1px solid #ea580c;' : 'background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;'"
                                            @click="setMode('out')"><i data-lucide="log-out" class="h-4 w-4"></i> Send</button>
                                </div>
                            </div>

                            <div x-show="mode === 'out'" class="text-xs text-gray-500 bg-white border border-gray-200 rounded-lg px-3 py-2.5">
                                Scan a file held at your office, then choose the next office to send it to.
                            </div>

                            <button type="button" :disabled="batch.running" @click="toggleBatch()"
                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm font-semibold shadow-sm disabled:opacity-50"
                                    :style="batch.on ? 'background:#7c3aed;color:#fff;border:1px solid #7c3aed;' : 'background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;'">
                                <i data-lucide="layers" class="h-4 w-4"></i>
                                <span x-text="batch.on ? 'Batch mode: ON' : 'Batch mode'"></span>
                            </button>
                            <p class="text-xs text-gray-400 text-center -mt-2" x-text="batch.on ? 'Scans are collected on the batch card below.' : (mode === 'in' ? 'Scan several files, then receive them together.' : 'Scan several files, then send them together.')"></p>

                            <button type="button" x-show="!batch.on" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-sm font-semibold shadow-sm"
                                    style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;"
                                    @click="openManual()"><i data-lucide="pencil-line" class="h-4 w-4"></i> Log manually</button>
                            <p x-show="!batch.on" class="text-xs text-gray-400 text-center -mt-2">No tracking sheet or QR code? Log it by hand.</p>
                        </div>
                    </div>
                </div>

                {{-- Batch card: scans collect here, then receive all / send all --}}
                <div x-show="batch.on" x-transition x-cloak class="bg-white border-2 rounded-xl shadow-sm overflow-hidden"
                     :class="mode === 'in' ? 'border-green-300' : 'border-orange-300'">
                    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3"
                         :class="mode === 'in' ? 'bg-green-50' : 'bg-orange-50'">
                        <div>
                            <h3 class="text-base font-bold text-gray-900" x-text="mode === 'in' ? 'Batch receive' : 'Batch send'"></h3>
                            <p class="text-xs text-gray-500" x-text="mode === 'in'
                                ? 'Scan each file\'s QR code, or add files waiting for receipt. Then receive them all at once.'
                                : 'Scan each file\'s QR code, or add files held at your office. Give each a destination, then send them all at once.'"></p>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="px-2 py-1 rounded-full bg-white border border-gray-200 font-semibold text-gray-700"><span x-text="batchPending().length"></span> to <span x-text="mode === 'in' ? 'receive' : 'send'"></span></span>
                            <span x-show="batchCount('done')" class="px-2 py-1 rounded-full bg-green-100 text-green-800 font-semibold"><span x-text="batchCount('done')"></span> done</span>
                            <span x-show="batchCount('error')" class="px-2 py-1 rounded-full bg-red-100 text-red-700 font-semibold"><span x-text="batchCount('error')"></span> failed</span>
                        </div>
                    </div>

                    <div class="px-5 py-4 space-y-4">
                        {{-- Add from the register --}}
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex-1 min-w-0" style="min-width:16rem">
                                <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="mode === 'in' ? 'Or select a file pending receipt' : 'Or select a file held at your office'"></label>
                                <select x-model="batch.pick" @change="addPicked()" :disabled="batch.running" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                    <option value="" x-text="batchPickable().length ? '— Select a file —' : '— No more files to add —'"></option>
                                    <template x-for="r in batchPickable()" :key="'bp-' + r.id">
                                        <option :value="String(r.id)" x-text="(r.file_number || r.related_file_number || 'Document') + (r.file_title ? ' — ' + nice(r.file_title) : '')"></option>
                                    </template>
                                </select>
                            </div>
                            <button type="button" :disabled="batch.running || !batchPickable().length" @click="addAllPickable()"
                                    class="px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-50">
                                Add all (<span x-text="batchPickable().length"></span>)
                            </button>
                        </div>

                        {{-- Send: one destination for every file --}}
                        <div x-show="mode === 'out' && batchPending().length" class="rounded-lg border border-orange-200 bg-orange-50 p-3">
                            <div class="text-xs font-bold uppercase tracking-wide text-orange-800 mb-2">Same destination for all files?</div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-2 items-start">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Send to (office)</label>
                                    <select x-model="batch.all.to_office" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                        <option value="">— Select office —</option>
                                        <template x-for="group in officeGroups()" :key="'bao-' + group.department">
                                            <optgroup :label="group.department">
                                                <template x-for="o in group.items.filter(i => i.office_code !== office)" :key="'baof-' + o.office_code">
                                                    <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Receiving officer</label>
                                    <select x-model="batch.all.receiving_officer_id" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                        <option value="">— Select officer —</option>
                                        <template x-for="o in sendOfficers()" :key="'baof-o-' + o.id">
                                            <option :value="String(o.id)" x-text="nice(o.name) + (o.username ? ' (' + o.username + ')' : '')"></option>
                                        </template>
                                        <option value="other">Other (not listed)…</option>
                                    </select>
                                    <input type="text" x-show="batch.all.receiving_officer_id === 'other'" x-model="batch.all.officer_other" placeholder="Officer's name"
                                           class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Request purpose</label>
                                    <select x-model="batch.all.request_purpose_id" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                        <option value="">— Select purpose —</option>
                                        <template x-for="p in requestPurposes" :key="'bap-' + p.id">
                                            <option :value="String(p.id)" x-text="nice(p.name)"></option>
                                        </template>
                                        <option value="other">Other</option>
                                    </select>
                                    <input type="text" x-show="batch.all.request_purpose_id === 'other'" x-model="batch.all.purpose_other" placeholder="Specify the purpose"
                                           class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                                </div>
                                <div class="md:pt-5">
                                    <button type="button" :disabled="batch.running" @click="applyToAll()"
                                            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg disabled:opacity-50">
                                        <i data-lucide="copy-check" class="w-4 h-4"></i> Apply to all
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- The files --}}
                        <div x-show="!batch.items.length" class="py-8 text-center text-sm text-gray-400 border-2 border-dashed border-gray-200 rounded-lg">
                            <span x-text="mode === 'in' ? 'Scan the first file to start the batch.' : 'Scan or select the first file to send.'"></span>
                        </div>
                        <div x-show="batch.items.length" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-10">#</th>
                                        <th class="px-3 py-2 text-left">File</th>
                                        <th class="px-3 py-2 text-left" x-show="mode === 'in'">Now at</th>
                                        <th class="px-3 py-2 text-left" x-show="mode === 'out'">Send to (office) *</th>
                                        <th class="px-3 py-2 text-left" x-show="mode === 'out'">Receiving officer *</th>
                                        <th class="px-3 py-2 text-left" x-show="mode === 'out'">Request purpose</th>
                                        <th class="px-3 py-2 text-left">Status</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(item, n) in batch.items" :key="'bi-' + (item.tracker_id || item.file_number)">
                                        <tr class="align-top" :class="item.state === 'done' ? 'bg-green-50' : (item.state === 'error' ? 'bg-red-50' : '')">
                                            <td class="px-3 py-2 text-gray-400" x-text="n + 1"></td>
                                            <td class="px-3 py-2">
                                                <div class="font-semibold text-gray-900" x-text="item.file_number || 'Document'"></div>
                                                <div class="text-xs text-gray-500" x-text="(nice(item.file_title) || '').toUpperCase()"></div>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600" x-show="mode === 'in'" x-text="nice(item.where) || '—'"></td>
                                            <td class="px-3 py-2" x-show="mode === 'out'">
                                                <select x-model="item.to_office" :disabled="item.state === 'done' || batch.running"
                                                        class="w-full border rounded-lg text-sm px-2 py-1.5 bg-white" :class="item.to_office || item.state === 'done' ? 'border-gray-300' : 'border-red-300'" style="min-width:11rem">
                                                    <option value="">— Select office —</option>
                                                    <template x-for="group in officeGroups()" :key="'bio-' + group.department">
                                                        <optgroup :label="group.department">
                                                            <template x-for="o in group.items.filter(i => i.office_code !== office)" :key="'biof-' + o.office_code">
                                                                <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                                            </template>
                                                        </optgroup>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2" x-show="mode === 'out'">
                                                <select x-model="item.receiving_officer_id" :disabled="item.state === 'done' || batch.running"
                                                        class="w-full border rounded-lg text-sm px-2 py-1.5 bg-white" :class="item.receiving_officer_id || item.state === 'done' ? 'border-gray-300' : 'border-red-300'" style="min-width:11rem">
                                                    <option value="">— Select officer —</option>
                                                    <template x-for="o in sendOfficers()" :key="'bif-' + o.id">
                                                        <option :value="String(o.id)" x-text="nice(o.name) + (o.username ? ' (' + o.username + ')' : '')"></option>
                                                    </template>
                                                    <option value="other">Other (not listed)…</option>
                                                </select>
                                                <input type="text" x-show="item.receiving_officer_id === 'other'" x-model="item.officer_other" :disabled="item.state === 'done' || batch.running"
                                                       placeholder="Officer's name *" class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-2 py-1.5">
                                            </td>
                                            <td class="px-3 py-2" x-show="mode === 'out'">
                                                <select x-model="item.request_purpose_id" :disabled="item.state === 'done' || batch.running"
                                                        class="w-full border border-gray-300 rounded-lg text-sm px-2 py-1.5 bg-white" style="min-width:10rem">
                                                    <option value="">— Select purpose —</option>
                                                    <template x-for="p in requestPurposes" :key="'bip-' + p.id">
                                                        <option :value="String(p.id)" x-text="nice(p.name)"></option>
                                                    </template>
                                                    <option value="other">Other</option>
                                                </select>
                                                <input type="text" x-show="item.request_purpose_id === 'other'" x-model="item.purpose_other" :disabled="item.state === 'done' || batch.running"
                                                       placeholder="Specify the purpose *" class="mt-1 w-full border border-gray-300 rounded-lg text-sm px-2 py-1.5">
                                            </td>
                                            <td class="px-3 py-2 text-xs" style="min-width:9rem">
                                                <span x-show="item.state === 'ready'" class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-semibold">Ready</span>
                                                <span x-show="item.state === 'working'" class="px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-semibold">Working…</span>
                                                <span x-show="item.state === 'done'" class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-semibold" x-text="mode === 'in' ? 'Received' : 'Sent'"></span>
                                                <span x-show="item.state === 'error'" class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">Failed</span>
                                                <div x-show="item.message && item.state !== 'ready'" class="mt-1" :class="item.state === 'error' ? 'text-red-700' : 'text-gray-500'" x-text="item.message"></div>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <button type="button" x-show="item.state !== 'done'" :disabled="batch.running" @click="removeFromBatch(item)"
                                                        title="Remove from the batch" class="p-1 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 disabled:opacity-50">
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div x-show="batch.items.length" class="flex flex-wrap items-end justify-between gap-3 pt-1">
                            <div class="flex-1" style="min-width:16rem">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Notes (applied to every file)</label>
                                <input type="text" x-model="batch.notes" :disabled="batch.running" maxlength="1000" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" x-show="batchCount('done')" :disabled="batch.running" @click="clearBatchDone()"
                                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 disabled:opacity-50">Clear done</button>
                                <button type="button" :disabled="!batchReady()" @click="runBatch()"
                                        class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white rounded-lg disabled:opacity-50"
                                        :class="mode === 'in' ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700'">
                                    <i data-lucide="check-check" class="w-4 h-4"></i>
                                    <span x-text="batch.running ? 'Working…' : (mode === 'in' ? 'Receive all (' + batchPending().length + ')' : 'Send all (' + batchPending().length + ')')"></span>
                                </button>
                            </div>
                        </div>
                        <p x-show="mode === 'out' && batchPending().length && !batchReady() && !batch.running" class="text-xs text-red-600 text-right">
                            Every file needs an office and a receiving officer. Use Apply to all if they are the same.
                        </p>
                    </div>
                </div>

                {{-- File profile --}}
                <div x-show="profile.open" x-transition class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-5 bg-gradient-to-r from-indigo-50 via-white to-purple-50 border-b border-gray-100">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-5">
                            {{-- Passport --}}
                            <div class="flex-shrink-0">
                                <template x-if="profile.data && profile.data.file.passport_url">
                                    <img :src="profile.data.file.passport_url" alt="Passport" class="w-28 h-32 object-cover rounded-xl border-4 border-white shadow-md cursor-zoom-in"
                                         @click="openLightbox([{ url: profile.data.file.passport_url, is_image: true, type: 'Passport photograph' }], 0)"
                                         x-on:error="profile.data.file.passport_url = null">
                                </template>
                                <template x-if="!(profile.data && profile.data.file.passport_url)">
                                    <div class="w-28 h-32 rounded-xl border-4 border-white shadow-md bg-gradient-to-br from-indigo-100 to-purple-100 flex flex-col items-center justify-center">
                                        <span class="text-2xl font-bold text-indigo-400" x-text="initials(profileTitle())"></span>
                                        <span class="text-xs text-indigo-300 mt-1">No passport</span>
                                    </div>
                                </template>
                            </div>

                            {{-- Identity --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-xl font-bold text-gray-900" x-text="profileHeading()"></h2>
                                    <template x-for="b in profileBadges()" :key="b.label">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="b.cls || ''" :style="b.style || ''" x-text="b.label"></span>
                                    </template>
                                </div>
                                <p class="text-gray-600 mt-0.5" x-text="profileTitle()"></p>
                                <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">Now at</div>
                                        <div class="font-semibold text-gray-800" x-text="nice(profile.data && profile.data.tracker && profile.data.tracker.current_office) || 'Not tracked yet'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">Location</div>
                                        <div class="text-gray-700" x-text="profileLocation()"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">Tracking ID</div>
                                        <div class="text-gray-700 font-mono text-xs mt-0.5 break-all" x-text="(profile.data && profile.data.tracker && profile.data.tracker.tracking_id) || 'Not tracked yet'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">EDMS pages</div>
                                        <div class="text-gray-700" x-text="profile.data ? profile.data.edms.total : '…'"></div>
                                    </div>
                                    <template x-for="st in profileStamps()" :key="'stamp-' + st.label">
                                        <div>
                                            <div class="text-xs text-gray-400 uppercase tracking-wide" x-text="st.label"></div>
                                            <div class="text-gray-700 flex items-center gap-1.5">
                                                <i data-lucide="clock" class="w-3.5 h-3.5 text-gray-400"></i>
                                                <span x-text="st.value"></span>
                                            </div>
                                            <div x-show="st.note" class="text-xs text-gray-400" x-text="st.note"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex lg:flex-col gap-2 flex-shrink-0">
                                {{-- Close first, in red (inline: the Tailwind v2 CDN may lack the shade). --}}
                                <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg shadow-sm"
                                        style="background:#dc2626;color:#fff;border:1px solid #dc2626;" @click="closeProfile()">
                                    <i data-lucide="x" class="w-4 h-4"></i> Close
                                </button>
                                <div x-show="sentFromHere()" class="px-3 py-2 text-xs rounded-lg bg-yellow-50 text-yellow-800 border border-yellow-200">Sent from your office — in transit</div>
                                <button type="button" x-show="canReceive()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow-sm disabled:opacity-50"
                                        :disabled="busy" @click="receiveProfile()">
                                    <i data-lucide="log-in" class="w-4 h-4"></i> Receive file
                                </button>
                                <button type="button" x-show="canSend()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm"
                                        @click="startSendFromProfile()">
                                    <i data-lucide="send" class="w-4 h-4"></i> Send to next office
                                </button>
                                <div x-show="!canReceive() && !canSend() && heldHere()" class="px-3 py-2 text-xs rounded-lg bg-green-50 text-green-700 border border-green-200">Held at your office</div>
                                <button type="button" x-show="profile.data && profile.data.tracker" :disabled="sheetBusy" @click="printTrackingSheet()"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-blue-700 border border-blue-200 bg-blue-50 rounded-lg hover:bg-blue-100 disabled:opacity-50">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                    <span x-text="sheetBusy ? 'Preparing…' : 'Tracking sheet'"></span>
                                </button>
                                <button type="button" x-show="canEditDetails()" :disabled="busy" @click="editDetails()"
                                        title="Correct the title / subject, type, reference and related file number"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-indigo-700 border border-indigo-200 bg-indigo-50 rounded-lg hover:bg-indigo-100 disabled:opacity-50">
                                    <i data-lucide="pencil" class="w-4 h-4"></i> Edit details
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Send form (inline) — same fields as the main Log a File page --}}
                    <div x-show="send.row" x-transition class="px-6 py-4 bg-orange-50 border-b border-orange-100 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Next office *</label>
                                <select x-model="send.to_office" @change="send.receiving_officer_id = ''; send.officerFilter = ''" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                    <option value="">— Select office —</option>
                                    <template x-for="group in officeGroups()" :key="'to-' + group.department">
                                        <optgroup :label="group.department">
                                            <template x-for="o in group.items.filter(i => i.office_code !== office)" :key="'to-' + o.office_code">
                                                <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                            </template>
                                        </optgroup>
                                    </template>
                                </select>
                            </div>
                            <div class="relative" @click.outside="send.officerOpen = false">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Receiving officer *</label>
                                <div class="relative">
                                    <input type="text" x-ref="officerInput" x-model="send.officerFilter"
                                           @focus="send.officerOpen = true" @input="send.receiving_officer_id = ''; send.officerOpen = true"
                                           @keydown.escape="send.officerOpen = false"
                                           placeholder="— Select officer —" autocomplete="off" spellcheck="false"
                                           class="w-full border rounded-lg text-sm pl-3 pr-8 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-orange-200"
                                           :class="send.receiving_officer_id ? 'border-green-400' : 'border-gray-300'">
                                    <button type="button" tabindex="-1" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400"
                                            @click="send.officerOpen = !send.officerOpen; $refs.officerInput.focus()">
                                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <div x-show="send.officerOpen" x-transition.opacity
                                     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                    <template x-for="group in officerGroups()" :key="'og-' + group.label">
                                        <div>
                                            <div class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-400 bg-gray-50 sticky top-0" x-text="group.label"></div>
                                            <template x-for="o in group.items" :key="'of-' + o.id">
                                                <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-orange-50 flex items-center justify-between gap-2"
                                                        :class="String(send.receiving_officer_id) === String(o.id) ? 'bg-orange-50 font-semibold' : ''"
                                                        @mousedown.prevent="pickOfficer(o)">
                                                    <span class="truncate" x-text="nice(o.name)"></span>
                                                    <span class="text-xs text-gray-400 flex-shrink-0" x-text="[o.rank, o.username].filter(Boolean).join(' · ')"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <div x-show="!officerGroups().length" class="px-3 py-3 text-sm text-gray-400">No officer matches.</div>
                                    <button type="button" class="w-full text-left px-3 py-2 text-sm border-t border-gray-100 hover:bg-amber-50 flex items-center gap-2 text-amber-800"
                                            :class="send.receiving_officer_id === 'other' ? 'bg-amber-50 font-semibold' : ''"
                                            @mousedown.prevent="send.receiving_officer_id = 'other'; send.officerFilter = 'Other (not listed)'; send.officerOpen = false">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i> Other (not listed)…
                                    </button>
                                </div>
                                <input type="text" x-show="send.receiving_officer_id === 'other'" x-model="send.officer_other" placeholder="Specify the receiving officer's name *"
                                       class="mt-1 w-full border rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                       :class="send.officer_other.trim() ? 'border-gray-300' : 'border-red-300'">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Request purpose</label>
                                <select x-model="send.request_purpose_id" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                    <option value="">— Select the reason this file is being sent —</option>
                                    <template x-for="p in requestPurposes" :key="'rp-' + p.id">
                                        <option :value="p.id" x-text="nice(p.name)"></option>
                                    </template>
                                    <option value="other">Other</option>
                                </select>
                                <input type="text" x-show="send.request_purpose_id === 'other'" x-model="send.purpose_other" placeholder="Specify the reason this file is being sent *"
                                       class="mt-1 w-full border rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                       :class="send.purpose_other.trim() ? 'border-gray-300' : 'border-red-300'">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                                <input type="text" x-model="send.notes" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                            </div>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button type="button" class="px-3 py-2 text-sm text-gray-600" @click="send.row = null">Cancel</button>
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50"
                                    :disabled="busy || !sendValid()" @click="forward()"><i data-lucide="send" class="w-4 h-4"></i> Track to next office</button>
                        </div>
                    </div>

                    {{-- Profile tabs --}}
                    <div class="px-6 border-b border-gray-100">
                        <nav class="flex gap-6 text-sm">
                            <button type="button" class="py-3 border-b-2 border-transparent text-gray-500" :class="profile.tab === 'history' && 'sfl-tab-active'" @click="profile.tab = 'history'">
                                Log history <span class="ml-1 text-xs px-1.5 rounded bg-gray-100" x-text="historyEvents().length"></span>
                            </button>
                            <button type="button" class="py-3 border-b-2 border-transparent text-gray-500" :class="profile.tab === 'edms' && 'sfl-tab-active'" @click="profile.tab = 'edms'">
                                EDMS files <span class="ml-1 text-xs px-1.5 rounded bg-gray-100" x-text="profile.data ? profile.data.edms.total : 0"></span>
                            </button>
                            <button type="button" class="py-3 border-b-2 border-transparent text-gray-500" :class="profile.tab === 'details' && 'sfl-tab-active'" @click="profile.tab = 'details'">File details</button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <div x-show="profile.loading" class="py-10 text-center text-gray-400">
                            <i data-lucide="loader-2" class="w-6 h-6 mx-auto animate-spin"></i>
                            <p class="mt-2 text-sm">Loading file profile…</p>
                        </div>

                        <template x-if="profile.data && !profile.loading">
                            <div>
                                {{-- History: the Movement Timeline, same data/order/labels as Quick Search --}}
                                <div x-show="profile.tab === 'history'" class="sfl-scroll pr-2">
                                    <template x-if="!historyEntries().length">
                                        <p class="text-sm text-gray-400 py-6 text-center">No movement history available for this file. Receiving it will start its log.</p>
                                    </template>
                                    <template x-if="historyEntries().length">
                                        <div>
                                            <div class="mb-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">Every movement of this file, oldest first. Each step is its own row: <strong>Logged in</strong> when an office receives the file, <strong>Logged out</strong> when it sends it on.</div>

                                            <div x-show="showHomeRow()" class="relative pl-7 pb-4">
                                                <span class="absolute left-2 top-0 bottom-0 w-0.5 bg-gray-200"></span>
                                                <span class="absolute left-0 top-1 h-4 w-4 rounded-full bg-emerald-600 border-2 border-gray-200"></span>
                                                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 flex items-start justify-between gap-2">
                                                    <div>
                                                        <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Archive / Registry</div>
                                                        <div class="text-sm font-bold text-gray-900" x-text="profileRegistry()"></div>
                                                    </div>
                                                    <span class="rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold">In Archive</span>
                                                </div>
                                            </div>

                                            <template x-for="(ev, i) in historyEvents()" :key="'ev-' + i">
                                                <div class="relative pl-7 pb-4">
                                                    <span class="absolute left-2 top-0 bottom-0 w-0.5 bg-gray-200"></span>
                                                    <span class="absolute left-0 top-1 h-4 w-4 rounded-full border-2 border-white shadow" :style="'background:' + ev.dot"></span>
                                                    <div class="rounded-lg border bg-white px-4 py-3" :style="'border-color:' + ev.border">
                                                        <div class="flex items-start justify-between gap-2 flex-wrap">
                                                            <div>
                                                                <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Office</div>
                                                                <div class="text-sm font-bold text-gray-900" x-text="nice(ev.office)"></div>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold whitespace-nowrap" :style="ev.style" x-text="ev.label"></span>
                                                                <button type="button" x-show="canEditEvent(ev)" @click="openEdit(ev)" :disabled="busy"
                                                                        title="Edit this log"
                                                                        class="p-1 rounded-md text-blue-500 hover:bg-blue-50 hover:text-blue-700 disabled:opacity-50">
                                                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                                                </button>
                                                                <button type="button" x-show="canDeleteLogs && ev.ref && ev.primary" @click="deleteLog(ev)" :disabled="busy"
                                                                        title="Delete this log (admin)"
                                                                        class="p-1 rounded-md text-red-500 hover:bg-red-50 hover:text-red-700 disabled:opacity-50">
                                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                                            <div><div class="font-bold uppercase text-gray-400" x-text="ev.timeLabel"></div>
                                                                <div class="mt-0.5"><span class="inline-block rounded-full px-2 py-0.5 font-semibold" :style="ev.style" x-text="ev.time || '—'"></span></div></div>
                                                            <div x-show="ev.to"><div class="font-bold uppercase text-gray-400">Sent to</div><div class="text-gray-800 font-semibold" x-text="nice(ev.to)"></div></div>
                                                            <div x-show="!ev.to"><div class="font-bold uppercase text-gray-400">Receiving Officer</div><div class="text-gray-800 font-semibold" x-text="ev.officer || '—'"></div></div>
                                                            <div><div class="font-bold uppercase text-gray-400">Request Purpose</div><div class="text-gray-800 font-semibold" x-text="nice(ev.purpose) || '—'"></div></div>
                                                            <div x-show="ev.by"><div class="font-bold uppercase text-gray-400">Logged by</div><div class="text-gray-800 font-semibold" x-text="ev.by"></div></div>
                                                            <div x-show="ev.delay" class="col-span-2"><div class="font-bold uppercase text-gray-400">Delay Reason</div><div class="text-gray-800 font-semibold" x-text="ev.delay"></div></div>
                                                        </div>
                                                        <div x-show="ev.notes" class="mt-2 text-xs text-gray-500" x-text="ev.notes"></div>
                                                        <div x-show="ev.logId" class="mt-2 text-xs font-mono text-gray-400" x-text="'Log ID: ' + ev.logId"></div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="approvalRows().length">
                                                <div>
                                                    <div class="mt-2 mb-2 text-xs font-extrabold uppercase tracking-wider text-gray-500">Workflow Approvals</div>
                                                    <div class="space-y-2">
                                                        <template x-for="(e, i) in approvalRows()" :key="'ap-' + i">
                                                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                                                <div class="flex items-start justify-between gap-2 flex-wrap">
                                                                    <div>
                                                                        <div class="text-xs font-bold text-gray-800" x-text="String(e.purpose || '').toLowerCase() === 'recommendation' ? 'Recommendation' : 'Approval'"></div>
                                                                        <div class="mt-1 text-xs text-gray-500" x-text="movementDate(e.log_in_date || e.logInDate, e.log_in_time || e.logInTime)"></div>
                                                                    </div>
                                                                    <div class="text-right text-xs">
                                                                        <div class="font-semibold text-gray-800" x-text="nice(e.office_name || e.office || e.receiving_office_name || 'Unknown')"></div>
                                                                        <div class="text-gray-500" x-text="nice(e.receiving_officer_name || e.accepted_by_name || '—')"></div>
                                                                    </div>
                                                                </div>
                                                                <div x-show="e.notes" class="mt-2 text-xs text-gray-500" x-text="e.notes"></div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                {{-- EDMS --}}
                                <div x-show="profile.tab === 'edms'">
                                    <template x-if="!profile.data.edms.pages.length">
                                        <div class="py-8 text-center text-gray-400">
                                            <i data-lucide="file-x" class="w-8 h-8 mx-auto"></i>
                                            <p class="mt-2 text-sm" x-text="'No EDMS documents for this file.'"></p>
                                        </div>
                                    </template>
                                    
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
                                        <template x-for="(p, i) in profile.data.edms.pages" :key="p.id">
                                            <button type="button" class="group text-left border border-gray-200 rounded-lg overflow-hidden hover:shadow-md hover:border-indigo-300 transition"
                                                    @click="openLightbox(profile.data.edms.pages, i)">
                                                <div class="sfl-thumb bg-gray-100 flex items-center justify-center overflow-hidden relative">
                                                    <span x-show="p.passport" class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded text-xs font-semibold shadow bg-green-600 text-white">Passport</span>
                                                    <template x-if="p.url && p.is_image">
                                                        <img :src="p.url" loading="lazy" class="w-full h-full object-cover group-hover:opacity-90" alt="">
                                                    </template>
                                                    <template x-if="!(p.url && p.is_image)">
                                                        <div class="text-center text-gray-400">
                                                            <i :data-lucide="p.url ? 'file-text' : 'image-off'" class="w-8 h-8 mx-auto"></i>
                                                            <div class="text-xs mt-1" x-text="p.url ? (p.is_pdf ? 'PDF' : 'Document') : 'Missing on disk'"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div class="px-2 py-1.5">
                                                    <div class="text-xs font-semibold text-gray-700 truncate" x-text="p.untyped ? (nice(p.type) || 'Uploaded document') : (nice(p.subtype) || nice(p.type) || 'Page')"></div>
                                                    <div class="text-xs truncate" :class="'text-gray-400'"
                                                         x-text="p.untyped ? (p.uploaded_at ? 'Uploaded ' + fmtDate(p.uploaded_at) : '') : ((p.code || '') + (p.page ? ' · p' + p.page : ''))"></div>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                    <p x-show="profile.data.edms.total > profile.data.edms.pages.length" class="text-xs text-gray-500 mt-3"
                                       x-text="'Showing the first ' + profile.data.edms.pages.length + ' of ' + profile.data.edms.total + ' pages. Open the Digital Archive for the full file.'"></p>
                                </div>

                                {{-- Details --}}
                                <div x-show="profile.tab === 'details'" class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <template x-for="d in profileDetails()" :key="d.label">
                                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                                            <div class="text-xs text-gray-400 uppercase tracking-wide" x-text="d.label"></div>
                                            <div class="font-medium text-gray-800 break-words" x-text="d.value || '—'"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Manual log --}}
                <div x-show="manual.open" x-transition class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50 flex items-center justify-between">
                        <h2 class="font-bold text-gray-900 flex items-center gap-2">
                            <i data-lucide="pencil-line" class="w-5 h-5 text-indigo-600"></i> Log a file manually
                            <span class="text-sm font-normal text-gray-500">— no tracking sheet, no QR code</span>
                        </h2>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="manual.open = false"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <template x-for="opt in entryTypes" :key="opt.value">
                                <label class="border-2 rounded-xl p-4 cursor-pointer flex gap-3 items-start transition"
                                       :style="entryCardStyle(opt, manual.entry_type === opt.value)">
                                    <input type="radio" class="mt-1" :value="opt.value" x-model="manual.entry_type" @change="manualTypeChanged()"
                                           :style="'accent-color:' + opt.solid">
                                    <div>
                                        <div class="font-semibold text-sm flex items-center gap-1.5" :style="'color:' + opt.text">
                                            <span class="inline-flex" :style="'color:' + opt.solid"><i :data-lucide="opt.icon" class="w-4 h-4"></i></span><span x-text="opt.label"></span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5" x-text="opt.hint"></div>
                                    </div>
                                </label>
                            </template>
                        </div>

                        {{-- Not indexed and Not a regular Mail/Letter/Memo/File: the document, then Sender, then Receiving. --}}
                        <div class="rounded-xl" :class="manual.entry_type !== 'file' ? 'border p-4' : ''"
                             :style="manual.entry_type !== 'file' ? 'border-color:#bfdbfe;background:#eff6ff;' : ''">
                            <div x-show="manual.entry_type !== 'file'" class="flex items-center gap-2 mb-3">
                                <span class="inline-flex" style="color:#1e40af"><i data-lucide="file-text" class="w-4 h-4"></i></span>
                                <h3 class="text-sm font-bold" style="color:#1e40af">Document</h3>
                                <span class="text-xs text-gray-500">— what it is and how it is identified</span>
                            </div>
                        <div class="grid grid-cols-1 gap-4" :class="manual.entry_type === 'non_file' ? 'md:grid-cols-3' : 'md:grid-cols-2'">
                            <div x-show="manual.entry_type === 'non_file'" class="md:col-span-3">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">What is it? *</label>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="k in documentKinds" :key="'dk-' + k.value">
                                        <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer text-sm font-semibold transition shadow-sm"
                                               :style="kindPillStyle(k, manual.document_kind === k.value)">
                                            <input type="radio" name="sfl-document-kind" :value="k.value" x-model="manual.document_kind"
                                                   @change="manual.kind_other = ''">
                                            <i :data-lucide="k.icon" class="w-4 h-4"></i><span x-text="k.label"></span>
                                        </label>
                                    </template>
                                </div>
                                <p x-show="!manual.document_kind" class="mt-1 text-xs text-red-600">Choose Mail, Letter, Memo, File or Other.</p>
                                <input type="text" x-show="manual.document_kind === 'other'" x-model="manual.kind_other" maxlength="100"
                                       placeholder="Specify what it is *"
                                       class="mt-2 md:w-1/2 w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                       :class="manual.kind_other.trim() ? 'border-gray-300' : 'border-red-300'">
                            </div>
                            <div x-show="manual.entry_type === 'file'" class="relative" @click.outside="manual.indexOpen = false">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">File number *</label>
                                <div class="relative">
                                    <input type="text" x-ref="indexInput" x-model="manual.indexQ"
                                           @focus="openIndexed()" @input="manual.file_number = ''; manual.file_title = ''; searchIndexed()"
                                           @keydown.escape="manual.indexOpen = false"
                                           placeholder="— Search indexed file number or title —" autocomplete="off"
                                           class="w-full border rounded-lg text-sm pl-3 pr-8 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.file_number ? 'border-green-400' : 'border-gray-300'">
                                    <button type="button" tabindex="-1" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400"
                                            @click="manual.indexOpen ? (manual.indexOpen = false) : openIndexed(); $refs.indexInput.focus()">
                                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <div x-show="manual.indexOpen" x-transition.opacity
                                     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                    <div x-show="indexed.loading" class="px-3 py-3 text-sm text-gray-400">Searching…</div>
                                    <template x-for="f in indexed.items" :key="'ix-' + (f.key || f.id)">
                                        <button type="button" class="w-full text-left px-3 py-2 hover:bg-indigo-50 flex items-center justify-between gap-3"
                                                :class="manual.file_number === f.file_number ? 'bg-indigo-50' : ''"
                                                @mousedown.prevent="pickIndexed(f)">
                                            <span class="text-sm font-semibold text-gray-800 whitespace-nowrap">
                                                <span x-text="f.file_number"></span>
                                                <span x-show="f.temporary" class="ml-1 px-1.5 rounded text-xs font-semibold" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">Temporary</span>
                                            </span>
                                            <span class="text-xs text-gray-500 truncate text-right" x-text="nice(f.file_title)"></span>
                                        </button>
                                    </template>
                                    <div x-show="!indexed.loading && !indexed.items.length" class="px-3 py-3 text-sm text-gray-400">
                                        No indexed file matches. If the file has not been indexed, choose <strong>Not indexed</strong>.
                                    </div>
                                </div>
                            </div>
                            <div x-show="manual.entry_type === 'unindexed'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">File number *</label>
                                <div class="flex gap-2">
                                    <input type="text" :value="manual.file_number" readonly
                                           class="flex-1 border border-gray-300 bg-gray-50 rounded-lg px-3 py-2 text-sm" placeholder="Click Enter to type the file number">
                                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg" @click="pickFileNumber()">
                                        <i data-lucide="pencil" class="w-4 h-4"></i> Enter
                                    </button>
                                </div>
                            </div>
                            <div x-show="manual.entry_type === 'non_file'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">File number <span class="font-normal text-gray-400">(optional — if the document relates to a file)</span></label>
                                <input type="text" x-model="manual.related_file_number" maxlength="255" autocomplete="off"
                                       class="w-full border border-gray-300 bg-white rounded-lg px-3 py-2 text-sm" placeholder="Type the file number (optional)">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="manual.entry_type === 'non_file' ? 'Title / subject *' : 'File title'"></label>
                                <input type="text" x-model="manual.file_title" :readonly="manual.entry_type === 'file'"
                                       :placeholder="manual.entry_type === 'file' ? 'Filled from the selected file' : ''"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                       :class="manual.entry_type === 'file' ? 'bg-gray-50 text-gray-700 cursor-not-allowed' : 'bg-white'">
                            </div>
                            <div x-show="manual.entry_type === 'non_file'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Reference no.</label>
                                <input type="text" x-model="manual.reference" class="w-full border border-gray-300 bg-white rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        </div>

                        <div class="rounded-xl" :class="manual.entry_type !== 'file' ? 'border p-4' : ''"
                             :style="manual.entry_type !== 'file' ? 'border-color:#e2e8f0;background:#f8fafc;' : ''">
                            <div x-show="manual.entry_type !== 'file'" class="flex items-center gap-2 mb-3">
                                <span class="inline-flex" style="color:#334155"><i data-lucide="user-round" class="w-4 h-4"></i></span>
                                <h3 class="text-sm font-bold" style="color:#334155">Sender</h3>
                                <span class="text-xs text-gray-500">— who sent it and where it came from</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div x-show="manual.entry_type === 'non_file'">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Sender</label>
                                    <select x-model="manual.sender_type" @change="senderTypeChanged()" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                        <option value="">— Select sender —</option>
                                        <template x-for="s in senderTypes" :key="'st-' + s">
                                            <option :value="s" x-text="s"></option>
                                        </template>
                                    </select>
                                    {{-- Internal: the department it came from (offices.department), or Other typed by hand. --}}
                                    <select x-show="manual.sender_type === 'Internal'" x-model="manual.sender_dept" @change="pickSenderDept(); manual.sender_person = ''; manual.sender_name = ''"
                                            class="mt-1 w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.sender_dept ? 'border-gray-300' : 'border-red-300'">
                                        <option value="">— Select the department * —</option>
                                        <template x-for="d in departments()" :key="'sd-' + d">
                                            <option :value="d" x-text="d"></option>
                                        </template>
                                        <option value="other">Other (not listed)…</option>
                                    </select>
                                    <input type="text" x-show="manual.sender_type === 'Internal' && manual.sender_dept === 'other'" x-model="manual.sender" maxlength="255"
                                           placeholder="Specify the department *"
                                           class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.sender.trim() ? 'border-gray-300' : 'border-red-300'">
                                    {{-- External: a Kano State MDA (kano_mdas), or Other typed by hand. --}}
                                    <select x-show="manual.sender_type === 'External'" x-model="manual.sender_mda" @change="manual.sender = ''; manual.sender_lga = ''"
                                            class="mt-1 w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.sender_mda ? 'border-gray-300' : 'border-red-300'">
                                        <option value="">— Select the MDA * —</option>
                                        <option value="lga">LGA (Local Government)…</option>
                                        <template x-for="mda in externalSenders" :key="'mda-' + mda">
                                            <option :value="mda" x-text="mda"></option>
                                        </template>
                                        <option value="other">Other (not listed)…</option>
                                    </select>
                                    {{-- LGA: one of Kano's LGAs (lgas table). --}}
                                    <select x-show="manual.sender_type === 'External' && manual.sender_mda === 'lga'" x-model="manual.sender_lga"
                                            class="mt-1 w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.sender_lga ? 'border-gray-300' : 'border-red-300'">
                                        <option value="">— Select the LGA * —</option>
                                        <template x-for="l in lgas" :key="'lga-' + l">
                                            <option :value="l" x-text="l"></option>
                                        </template>
                                    </select>
                                    <input type="text" x-show="manual.sender_type === 'External' && manual.sender_mda === 'other'" x-model="manual.sender" maxlength="255"
                                           placeholder="Specify the MDA or organisation *"
                                           class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.sender.trim() ? 'border-gray-300' : 'border-red-300'">
                                    <input type="text" x-show="manual.sender_type === 'Other'" x-model="manual.sender" maxlength="255"
                                           placeholder="Specify the sender *"
                                           class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.sender_type === 'Other' && !manual.sender.trim() ? 'border-red-300' : 'border-gray-300'">
                                </div>
                                {{-- Sender name: Internal picks a staff member (or Other, typed); External / Other type it. --}}
                                <div x-show="manual.entry_type === 'non_file' && manual.sender_type">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Sender name *</label>
                                    <select x-show="manual.sender_type === 'Internal'" x-model="manual.sender_person" @change="pickSenderPerson()"
                                            class="w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.sender_person ? 'border-gray-300' : 'border-red-300'">
                                        <option value="">— Select the sender —</option>
                                        <template x-for="o in senderPeople()" :key="'sp-' + o.id">
                                            <option :value="String(o.id)" x-text="nice(o.name) + (o.rank ? ' — ' + o.rank : '') + (o.username ? ' (' + o.username + ')' : '')"></option>
                                        </template>
                                        <option value="other">Other (specify)…</option>
                                    </select>
                                    <input type="text" x-show="manual.sender_type !== 'Internal' || manual.sender_person === 'other'" x-model="manual.sender_name" maxlength="255"
                                           :placeholder="manual.sender_type === 'Internal' ? 'Specify the sender\'s name *' : 'Sender\'s name *'"
                                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="(manual.sender_type === 'Internal' ? 'mt-1 ' : '') + (manual.sender_name.trim() ? 'border-gray-300' : 'border-red-300')">
                                </div>
                                <div x-show="manual.entry_type === 'non_file' && manual.sender_type">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Sender rank</label>
                                    <select x-model="manual.sender_rank" @change="manual.sender_rank_other = ''" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                        <option value="">— Select the rank —</option>
                                        <template x-for="r in officerRanks" :key="'rk-' + r">
                                            <option :value="r" x-text="r"></option>
                                        </template>
                                        <option value="other">Other (specify)…</option>
                                    </select>
                                    <input type="text" x-show="manual.sender_rank === 'other'" x-model="manual.sender_rank_other" maxlength="100"
                                           placeholder="Specify the rank *"
                                           class="mt-1 w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.sender_rank_other.trim() ? 'border-gray-300' : 'border-red-300'">
                                </div>
                                {{-- Hidden for an Internal sender (the sender's department above is used) and an External one (the MDA / LGA says where it came from). --}}
                                <div x-show="!senderGivesDepartment() && !senderIsExternal()">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Received from (department) *</label>
                                    <select x-model="manual.from_department" @change="fromDepartmentChanged()"
                                            class="w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.from_department ? 'border-gray-300' : 'border-red-300'">
                                        <option value="">— Select the department —</option>
                                        <template x-for="d in departments()" :key="'fd-' + d">
                                            <option :value="d" x-text="d"></option>
                                        </template>
                                        <option value="other">Other (Specify)…</option>
                                    </select>
                                </div>
                                <div x-show="!senderIsExternal()">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1"><span x-text="senderGivesDepartment() ? 'Sender’s department' : 'Received from (office)'"></span> *</label>
                                    <select x-model="manual.from_office" class="w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.from_office ? 'border-gray-300' : 'border-red-300'">
                                        <option value="" x-text="manual.from_department ? '— Select the office —' : '— Select the department first —'"></option>
                                        <template x-for="o in officesIn(manual.from_department)" :key="'from-' + o.office_code">
                                            <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                        </template>
                                        <option value="other">Other</option>
                                    </select>
                                    <input type="text" x-show="manual.from_office === 'other'" x-model="manual.from_other" placeholder="Specify where the file came from *"
                                           class="mt-1 w-full border rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.from_other.trim() ? 'border-gray-300' : 'border-red-300'">
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl" :class="manual.entry_type !== 'file' ? 'border p-4' : ''"
                             :style="manual.entry_type !== 'file' ? 'border-color:#bbf7d0;background:#f0fdf4;' : ''">
                            <div x-show="manual.entry_type !== 'file'" class="flex items-center gap-2 mb-3">
                                <span class="inline-flex" style="color:#166534"><i data-lucide="inbox" class="w-4 h-4"></i></span>
                                <h3 class="text-sm font-bold" style="color:#166534">Receiving</h3>
                                <span class="text-xs text-gray-500">— who takes it here, why, and where it goes next</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Send: the office it goes to. Pre-filled from the receiving officer's office; the item is logged here and sent on in one step. --}}
                                <div x-show="mode === 'out'">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Send to (office) *</label>
                                    <select x-model="manual.send_to_office" class="w-full border rounded-lg text-sm px-2 py-2 bg-white"
                                            :class="manual.send_to_office ? 'border-gray-300' : 'border-red-300'">
                                        <option value="">— Select the office —</option>
                                        <template x-for="group in officeGroups()" :key="'mst-' + group.department">
                                            <optgroup :label="group.department">
                                                <template x-for="o in group.items.filter(i => i.office_code !== office)" :key="'msto-' + o.office_code">
                                                    <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </div>
                                <div class="relative" @click.outside="manual.officerOpen = false">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Receiving officer *
                                        <span x-show="mode === 'in'" class="font-normal text-gray-400">(you — receiving)</span>
                                    <span x-show="mode === 'out'" class="font-normal text-gray-400">(at the office it is sent to)</span></label>
                                    <input type="text" x-show="mode === 'in'" :value="userName" readonly
                                           title="On Receive, the receiving officer is the logged-in user"
                                           class="w-full border border-green-400 bg-gray-50 text-gray-700 rounded-lg text-sm px-3 py-2 cursor-not-allowed">
                                    <div class="relative" x-show="mode !== 'in'">
                                        <input type="text" x-ref="manualOfficerInput" x-model="manual.officerFilter"
                                               @focus="manual.officerOpen = true" @input="manual.receiving_officer_id = ''; manual.officerOpen = true"
                                               @keydown.escape="manual.officerOpen = false"
                                               placeholder="— Select officer —" autocomplete="off"
                                               class="w-full border rounded-lg text-sm pl-3 pr-8 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                               :class="manual.receiving_officer_id ? 'border-green-400' : 'border-gray-300'">
                                        <button type="button" tabindex="-1" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400"
                                                @click="manual.officerOpen = !manual.officerOpen; $refs.manualOfficerInput.focus()">
                                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                    <div x-show="manual.officerOpen && mode !== 'in'" x-transition.opacity
                                         class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                        <template x-for="group in manualOfficerGroups()" :key="'mog-' + group.label">
                                            <div>
                                                <div class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-400 bg-gray-50 sticky top-0" x-text="group.label"></div>
                                                <template x-for="o in group.items" :key="'mof-' + o.id">
                                                    <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 flex items-center justify-between gap-2"
                                                            :class="String(manual.receiving_officer_id) === String(o.id) ? 'bg-indigo-50 font-semibold' : ''"
                                                            @mousedown.prevent="pickManualOfficer(o)">
                                                        <span class="truncate" x-text="nice(o.name)"></span>
                                                        <span class="text-xs text-gray-400 flex-shrink-0" x-text="o.rank || ''"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <div x-show="!manualOfficerGroups().length" class="px-3 py-3 text-sm text-gray-400">No officer matches.</div>
                                        <button type="button" class="w-full text-left px-3 py-2 text-sm border-t border-gray-100 hover:bg-amber-50 flex items-center gap-2 text-amber-800"
                                                :class="manual.receiving_officer_id === 'other' ? 'bg-amber-50 font-semibold' : ''"
                                                @mousedown.prevent="manual.receiving_officer_id = 'other'; manual.officerFilter = 'Other (not listed)'; manual.officerOpen = false">
                                            <i data-lucide="user-plus" class="w-4 h-4"></i> Other (not listed)…
                                        </button>
                                    </div>
                                    <input type="text" x-show="mode !== 'in' && manual.receiving_officer_id === 'other'" x-model="manual.officer_other" placeholder="Specify the receiving officer's name *"
                                           class="mt-1 w-full border rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.officer_other.trim() ? 'border-gray-300' : 'border-red-300'">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Request purpose
                                        <span x-show="manual.entry_type === 'non_file'" class="font-normal text-gray-400">(optional)</span></label>
                                    <select x-model="manual.request_purpose_id" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
                                        <option value="">— Select the reason this file was brought —</option>
                                        <template x-for="p in requestPurposes" :key="'mrp-' + p.id">
                                            <option :value="p.id" x-text="nice(p.name)"></option>
                                        </template>
                                        <option value="other">Other</option>
                                    </select>
                                    <input type="text" x-show="manual.request_purpose_id === 'other'" x-model="manual.purpose_other" placeholder="Specify the reason this file was brought *"
                                           class="mt-1 w-full border rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                           :class="manual.purpose_other.trim() ? 'border-gray-300' : 'border-red-300'">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                                    <textarea x-model="manual.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="px-4 py-2 text-sm text-gray-600" @click="manual.open = false">Cancel</button>
                            <button type="button" class="inline-flex items-center gap-2 px-5 py-2 text-white text-sm font-semibold rounded-lg disabled:opacity-50"
                                    :class="mode === 'out' ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700'"
                                    :disabled="busy || !manualValid()" @click="receiveManual()">
                                <i data-lucide="check" class="w-4 h-4"></i>
                                <span x-text="mode === 'out' ? 'Create log & send' : 'Create log & receive'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Register --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="px-5 pt-3 border-b border-gray-100 flex flex-wrap items-end justify-between gap-3">
                        <nav class="flex gap-5 text-sm">
                            <template x-for="t in registerTabs" :key="t.key">
                                <button type="button" class="pb-3 border-b-2 border-transparent text-gray-500" :class="tab === t.key && 'sfl-tab-active'" @click="tab = t.key; memoFilter = ''">
                                    <span x-text="t.label"></span>
                                    <span class="ml-1 px-1.5 rounded text-xs" :class="t.badge" x-text="tabCount(t.key)"></span>
                                </button>
                            </template>
                        </nav>
                        <div class="flex items-center gap-2 pb-2 text-sm">
                            <div class="relative">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-2 top-2"></i>
                                <input type="text" x-model="filter" placeholder="Filter…" class="border border-gray-300 rounded-lg pl-8 pr-2 py-1.5 text-sm w-52">
                            </div>
                            <select x-show="tab === 'memo'" x-model="memoFilter" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm" title="Filter by type">
                                <option value="" x-text="'All types (' + memoCount('') + ')'"></option>
                                <template x-for="k in documentKinds" :key="'mf-' + k.value">
                                    <option :value="k.value" x-text="k.label + ' (' + memoCount(k.value) + ')'"></option>
                                </template>
                            </select>
                            {{-- Date range: filters every tab by the row's date; on Outgoing it replaces Last N days. --}}
                            <div class="flex items-center gap-1 border border-gray-300 rounded-lg px-2 py-1 bg-white" title="Date range">
                                <i data-lucide="calendar" class="w-4 h-4 text-gray-400"></i>
                                <input type="date" x-model="dateFrom" :max="dateTo || null" @change="onDateRangeChange()" aria-label="From date"
                                       class="text-sm border-0 p-0 focus:ring-0 text-gray-700" style="width:8.5rem">
                                <span class="text-gray-400 text-xs">to</span>
                                <input type="date" x-model="dateTo" :min="dateFrom || null" @change="onDateRangeChange()" aria-label="To date"
                                       class="text-sm border-0 p-0 focus:ring-0 text-gray-700" style="width:8.5rem">
                                <button type="button" x-show="dateFrom || dateTo" @click="clearDateRange()" title="Clear date range" aria-label="Clear date range"
                                        class="text-gray-400 hover:text-red-500 px-0.5">&times;</button>
                            </div>
                            <select x-show="view() === 'sent' && !dateFrom && !dateTo" x-model.number="days" @change="loadLists()" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="1">Today</option>
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                            <div class="relative" @click.outside="exportOpen = false" @keydown.escape.window="exportOpen = false">
                                <button type="button" @click="exportOpen = !exportOpen" :disabled="listsLoading || !visibleRows().length"
                                        title="Export the rows shown (this tab, sub-tab, filter and date range)" aria-haspopup="true" :aria-expanded="exportOpen ? 'true' : 'false'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg disabled:opacity-50"
                                        style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;">
                                    <i data-lucide="download" class="w-4 h-4"></i> Export <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                                </button>
                                <div x-show="exportOpen" x-cloak x-transition.opacity
                                     class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1" style="z-index:60">
                                    <button type="button" @click="exportRows('csv')" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i data-lucide="file-spreadsheet" class="w-4 h-4 text-green-600"></i> Excel (CSV)
                                    </button>
                                    <button type="button" @click="exportRows('pdf')" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i data-lucide="file-text" class="w-4 h-4 text-red-600"></i> PDF
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="p-2 text-gray-500 hover:text-indigo-600 rounded-lg hover:bg-gray-50" title="Refresh" @click="loadLists()">
                                <span class="inline-flex" :class="listsLoading && 'animate-spin'"><i data-lucide="refresh-cw" class="w-4 h-4"></i></span>
                            </button>
                            <button type="button" @click="toggleAutoRefresh()" :aria-pressed="autoRefresh ? 'true' : 'false'"
                                    :title="(autoRefresh ? 'Auto refresh is on (every ' + autoRefreshSeconds + 's). Click to turn off.' : 'Turn on auto refresh (every ' + autoRefreshSeconds + 's)')
                                            + (lastRefreshed ? ' Last refreshed ' + lastRefreshed.toLocaleTimeString() + '.' : '')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg border"
                                    :class="autoRefresh ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                                <span class="inline-block w-2 h-2 rounded-full" :class="autoRefresh ? 'bg-green-300 animate-pulse' : 'bg-gray-300'"></span>
                                <span x-text="autoRefresh ? 'Auto: On' : 'Auto: Off'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Incoming: waiting for receipt (Receive) and held here (Send). Outgoing: sent from this office. --}}
                    <div class="px-5 py-2 border-b border-gray-100 bg-gray-50 flex gap-2 text-xs">
                        <template x-for="v in subTabs" :key="'sv-' + v.key">
                            <button type="button" class="px-3 py-1 rounded-full border font-semibold"
                                    :class="sub === v.key ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:border-indigo-300'"
                                    @click="sub = v.key">
                                <span x-text="v.label"></span>
                                <span class="ml-1" x-text="subCount(tab, v.key)"></span>
                            </button>
                        </template>
                    </div>

                    {{-- Incoming, split: Yet to Receive (awaiting receipt) and Received (held here). --}}
                    <div x-show="sub === 'incoming'" class="px-5 pt-2 border-b border-gray-100 flex gap-5 text-sm">
                        <template x-for="iv in incomingViews" :key="'iv-' + iv.key">
                            <button type="button" class="pb-2 border-b-2 border-transparent text-gray-500" :class="incomingView === iv.key && 'sfl-tab-active'"
                                    @click="incomingView = iv.key">
                                <span x-text="iv.label"></span>
                                <span class="ml-1 px-1.5 rounded text-xs" :class="iv.key === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'"
                                      x-text="incomingCount(tab, iv.key)"></span>
                            </button>
                        </template>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <tr>
                                    <th class="px-5 py-3 text-left w-12">S/N</th>
                                    <th class="px-5 py-3 text-left whitespace-nowrap">File Number</th>
                                    <th class="px-5 py-3 text-left">File Title</th>
                                    <th class="px-5 py-3 text-left">From</th>
                                    <th class="px-5 py-3 text-left">To</th>
                                    <th class="px-5 py-3 text-left" x-text="view() === 'sent' ? 'Sent' : 'Date'"></th>
                                    <th class="px-5 py-3 text-left">Purpose / Notes</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="uppercase">
                                <template x-for="(row, idx) in pagedRows()" :key="view() + '-' + row.id">
                                    <tr class="border-t border-gray-100 hover:bg-indigo-50 cursor-pointer" @click="openRow(row)">
                                        <td class="px-5 py-3 text-gray-500" x-text="pageOffset() + idx + 1"></td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <div class="font-semibold text-gray-800" x-text="row.file_number || row.related_file_number || '—'"></div>
                                            <div class="text-xs mt-0.5 space-x-1">
                                                <span x-show="row.file_type === 'NON_FILE' && row.document_kind" class="px-1.5 rounded font-semibold"
                                                      :style="kindStyle(row.document_kind)" x-text="kindText(row.document_kind, row.mail_type)"></span>
                                                <span x-show="view() === 'incoming'" class="px-1.5 rounded" :class="row.bucket === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'"
                                                      x-text="row.bucket === 'pending' ? 'Awaiting receipt' : 'Held here'"></span>
                                                <span x-show="row.legacy" class="px-1.5 rounded bg-gray-100 text-gray-700" title="Logged before File Movement and never received here; counted as held">Legacy log</span>
                                                <span x-show="row.not_indexed" class="px-1.5 rounded bg-orange-100 text-orange-700">Not indexed</span>
                                                <span x-show="row.request_type === 'SUBMITTED'" class="px-1.5 rounded bg-purple-100 text-purple-700">Submitted Request</span>
                                            </div>
                                            {{-- Log ID (this movement), wrapped: it is longer than the file number. --}}
                                            <div x-show="row.log_id" class="text-xs font-mono text-gray-400 mt-0.5 whitespace-normal" style="max-width: 9rem" title="Log ID" x-text="row.log_id"></div>
                                        </td>
                                        <td class="px-5 py-3 text-gray-700" x-text="(nice(row.file_title) || '').toUpperCase() || '—'"></td>
                                        <td class="px-5 py-3">
                                            <span x-show="row.from" class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" style="background:#e0f2fe;color:#075985;border:1px solid #bae6fd;" x-text="nice(row.from)"></span>
                                            <span x-show="!row.from" class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-5 py-3">
                                            {{-- To: the receiving office on top, the officer under it (either alone when only one is known). --}}
                                            <div x-show="row.to_officer && row.to" class="mb-0.5 text-xs font-semibold text-gray-600" x-text="nice(row.to)"></div>
                                            <span x-show="row.to_officer || row.to" class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" style="background:#ccfbf1;color:#115e59;border:1px solid #99f6e4;" x-text="nice(row.to_officer || row.to)"></span>
                                            <span x-show="!row.to_officer && !row.to" class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap" x-text="fmt(row.since)"></td>
                                        <td class="px-5 py-3 text-gray-600">
                                            <div x-text="nice(row.purpose) || ''"></div>
                                            <div class="text-xs text-gray-400" x-text="row.notes || ''"></div>
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap normal-case" @click.stop>
                                            <template x-if="view() !== 'sent' && rowBucket(row) === 'pending'">
                                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50" :disabled="busy" @click="receivePending(row)">
                                                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i> Receive
                                                </button>
                                            </template>
                                            <template x-if="view() !== 'sent' && rowBucket(row) === 'held'">
                                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-lg" @click="openRow(row, true)">
                                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Send
                                                </button>
                                            </template>
                                            <template x-if="view() === 'sent'">
                                                <span class="inline-flex flex-col items-center align-middle">
                                                    <span class="px-2 py-1 rounded-full text-xs font-semibold"
                                                          :class="row.state === 'Received' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800'"
                                                          x-text="row.state"></span>
                                                    {{-- Tracked from file commissioning (user, 2026-10-29), as on the File Tracker page. --}}
                                                    <span x-show="row.diit" class="mt-0.5 font-bold uppercase tracking-wider text-indigo-500" style="font-size:10px"
                                                          title="Default In-process In-transit Tracking">DIIT</span>
                                                </span>
                                            </template>
                                            <button type="button" x-show="canDeleteLogs" :disabled="busy" @click="deleteTracker(row)"
                                                    title="Delete this entire file tracker (all log entries) — admin"
                                                    class="ml-1 inline-flex items-center gap-1 px-2.5 py-1.5 border border-red-300 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold rounded-lg disabled:opacity-50">
                                                <i data-lucide="trash" class="w-3.5 h-3.5"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!visibleRows().length">
                                    <td colspan="8" class="px-5 py-10 text-center text-gray-400">
                                        <i data-lucide="inbox" class="w-8 h-8 mx-auto"></i>
                                        <p class="mt-2" x-text="listsLoading ? 'Loading…' : 'Nothing here.'"></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination over the rows this tab, sub-tab, filter and date range show. --}}
                    <div x-show="visibleRows().length" class="px-5 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <span x-text="'Showing ' + (pageOffset() + 1) + '–' + Math.min(pageOffset() + perPage, visibleRows().length) + ' of ' + visibleRows().length"></span>
                            <span class="text-gray-300">·</span>
                            <label class="flex items-center gap-1">
                                <span>Per page</span>
                                <select x-model.number="perPage" class="border border-gray-300 rounded-lg px-2 py-1 text-sm">
                                    <template x-for="n in perPageOptions" :key="'pp-' + n">
                                        <option :value="n" x-text="n" :selected="n === perPage"></option>
                                    </template>
                                </select>
                            </label>
                        </div>
                        <nav x-show="pageCount() > 1" class="flex items-center gap-1" aria-label="Pagination">
                            <button type="button" @click="goToPage(currentPage() - 1)" :disabled="currentPage() === 1"
                                    class="px-2.5 py-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Previous page">&lsaquo; Prev</button>
                            <template x-for="(n, i) in pageNumbers()" :key="'pg-' + i + '-' + n">
                                <span>
                                    <span x-show="n === null" class="px-1 text-gray-400">…</span>
                                    <button type="button" x-show="n !== null" @click="goToPage(n)" x-text="n"
                                            :aria-current="n === currentPage() ? 'page' : null"
                                            class="min-w-[2rem] px-2 py-1 rounded-lg border"
                                            :class="n === currentPage() ? 'bg-indigo-600 border-indigo-600 text-white font-semibold' : 'border-gray-300 bg-white hover:bg-gray-50'"></button>
                                </span>
                            </template>
                            <button type="button" @click="goToPage(currentPage() + 1)" :disabled="currentPage() === pageCount()"
                                    class="px-2.5 py-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Next page">Next &rsaquo;</button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lightbox --}}
    {{-- Edit one log. The entry as it was is kept in a hidden history (file_movement_log_edits). --}}
    <div x-show="edit.open" x-transition.opacity class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4" @keydown.window.escape="edit.open && closeEdit()" x-cloak>
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-full overflow-y-auto" @click.outside="closeEdit()">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Edit log</h3>
                    <p class="text-sm text-gray-500" x-text="nice(edit.office) + (edit.statusLabel ? ' — ' + edit.statusLabel : '')"></p>
                </div>
                <button type="button" class="p-1 text-gray-400 hover:text-gray-600" @click="closeEdit()"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="px-5 py-4 space-y-4 text-sm">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Office *</label>
                    <select x-model="edit.office_code" class="w-full border rounded-lg px-2 py-2 bg-white" :class="edit.office_code ? 'border-gray-300' : 'border-red-300'">
                        <option value="">— Select office —</option>
                        <template x-for="group in officeGroups()" :key="'eog-' + group.department">
                            <optgroup :label="group.department">
                                <template x-for="o in group.items" :key="'eof-' + o.office_code">
                                    <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                </template>
                            </optgroup>
                        </template>
                    </select>
                    <p x-show="edit.office_code && edit.office_code !== edit.initial.office_code && edit.isNewest" class="mt-1 text-xs text-amber-700">This is the file's latest step, so its current location moves to this office too.</p>
                </div>
                <div x-show="edit.status !== 'pending_acceptance'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Log-in date <span class="font-normal text-gray-400">(locked)</span></label>
                        <input type="date" x-model="edit.log_in_date" disabled readonly title="The log-in date and time cannot be edited"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Log-in time <span class="font-normal text-gray-400">(locked)</span></label>
                        <input type="time" x-model="edit.log_in_time" disabled readonly title="The log-in date and time cannot be edited"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed">
                    </div>
                </div>
                <div x-show="edit.status === 'completed'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Log-out date</label>
                        <input type="date" x-model="edit.log_out_date" :max="today()" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Log-out time</label>
                        <input type="time" x-model="edit.log_out_time" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Receiving officer</label>
                        <select x-model="edit.receiving_officer_id" class="w-full border border-gray-300 rounded-lg px-2 py-2 bg-white">
                            <option value="">— Not set —</option>
                            <template x-for="o in officers" :key="'eo-' + o.id">
                                <option :value="String(o.id)" x-text="nice(o.name) + (o.username ? ' (' + o.username + ')' : '')"></option>
                            </template>
                            <option value="other">Other (not listed)…</option>
                        </select>
                        <input type="text" x-show="edit.receiving_officer_id === 'other'" x-model="edit.officer_other" placeholder="Specify the receiving officer's name"
                               class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Request purpose</label>
                        <select x-model="edit.request_purpose_id" class="w-full border border-gray-300 rounded-lg px-2 py-2 bg-white">
                            <option value="">— Not set —</option>
                            <template x-for="p in requestPurposes" :key="'ep-' + p.id">
                                <option :value="String(p.id)" x-text="nice(p.name)"></option>
                            </template>
                            <option value="other">Other</option>
                        </select>
                        <input type="text" x-show="edit.request_purpose_id === 'other'" x-model="edit.purpose_other" placeholder="Specify the purpose"
                               class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                    <textarea x-model="edit.notes" rows="2" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                </div>
                <div x-show="edit.status === 'completed'">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Log-out notes</label>
                    <textarea x-model="edit.completion_notes" rows="2" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Reason for the change *</label>
                    <textarea x-model="edit.reason" rows="2" maxlength="500" placeholder="Why is this log being corrected?"
                              class="w-full border rounded-lg px-3 py-2" :class="edit.reason.trim() ? 'border-gray-300' : 'border-red-300'"></textarea>
                </div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" class="px-4 py-2 text-sm text-gray-600" @click="closeEdit()">Cancel</button>
                <button type="button" :disabled="busy || !editValid()" @click="saveEdit()"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <i data-lucide="save" class="w-4 h-4"></i> Save changes
                </button>
            </div>
        </div>
    </div>

    <div x-show="lightbox.open" x-transition.opacity class="fixed inset-0 z-50 bg-black bg-opacity-80 flex flex-col" @keydown.window.escape="lightbox.open = false" @keydown.window.arrow-right="lightbox.open && lightboxStep(1)" @keydown.window.arrow-left="lightbox.open && lightboxStep(-1)" x-cloak>
        <div class="flex items-center justify-between px-5 py-3 text-white">
            <div class="text-sm" x-text="lightboxCaption()"></div>
            <div class="flex items-center gap-2">
                <a :href="lightboxItem() && lightboxItem().url" target="_blank" class="p-2 hover:bg-white hover:bg-opacity-10 rounded" title="Open in new tab"><i data-lucide="external-link" class="w-5 h-5"></i></a>
                <button type="button" class="p-2 hover:bg-white hover:bg-opacity-10 rounded" @click="lightbox.open = false"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center px-16 pb-6 relative" @click.self="lightbox.open = false">
            <button type="button" x-show="lightbox.items.length > 1" class="absolute left-4 p-3 text-white hover:bg-white hover:bg-opacity-10 rounded-full" @click="lightboxStep(-1)"><i data-lucide="chevron-left" class="w-8 h-8"></i></button>
            <template x-if="lightboxItem() && lightboxItem().url && lightboxItem().is_image">
                <img :src="lightboxItem().url" class="max-h-full max-w-full object-contain rounded shadow-2xl" alt="">
            </template>
            <template x-if="lightboxItem() && lightboxItem().url && !lightboxItem().is_image">
                <iframe :src="lightboxItem().url" class="w-full h-full bg-white rounded"></iframe>
            </template>
            <template x-if="lightboxItem() && !lightboxItem().url">
                <div class="text-white text-center"><i data-lucide="image-off" class="w-10 h-10 mx-auto"></i><p class="mt-2">This page's image is missing on disk.</p></div>
            </template>
            <button type="button" x-show="lightbox.items.length > 1" class="absolute right-4 p-3 text-white hover:bg-white hover:bg-opacity-10 rounded-full" @click="lightboxStep(1)"><i data-lucide="chevron-right" class="w-8 h-8"></i></button>
        </div>
    </div>
</div>

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/secretariat-file-log.js') }}?v={{ filemtime(public_path('js/secretariat-file-log.js')) }}"></script>
@endpush
