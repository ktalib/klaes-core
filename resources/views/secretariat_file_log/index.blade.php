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
            receive: @js(route('secretariat-file-log.receive')),
            forward: @js(route('secretariat-file-log.forward')),
        },
        userId: @js(auth()->id()),
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

            {{-- Title + office --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">File Movement (Department)</h1>
                    <p class="text-sm text-gray-500">Receive and send files for the HC, PS and Directors' offices. Scan any KLAES QR code, or log a file manually.</p>
                </div>

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
                            <button type="button" class="text-xs font-semibold text-indigo-600 hover:underline" @click="officeLocked = false">Change</button>
                        </div>
                    </template>
                    <template x-if="!officeLocked">
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

            <div x-show="!officeLocked" class="bg-white border border-dashed border-indigo-300 rounded-xl p-10 text-center">
                <i data-lucide="building" class="w-10 h-10 text-indigo-300 mx-auto"></i>
                <p class="mt-3 text-gray-600 font-medium">Select your office to start logging files.</p>
                <p class="text-sm text-gray-400">It is remembered on this computer.</p>
            </div>

            <div x-show="officeLocked" class="space-y-6">

                {{-- Dashboard cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <template x-for="card in cards()" :key="card.key">
                        <button type="button" class="bg-white rounded-lg border border-gray-200 p-5 text-left hover:shadow-md transition-shadow focus:outline-none"
                                :class="card.tab && tab === card.tab ? 'ring-2 ring-indigo-200' : ''"
                                @click="card.tab && (tab = card.tab)">
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
                        </button>
                    </template>
                </div>

                {{-- Scan station --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="h-1 w-full" :class="mode === 'in' ? 'bg-gradient-to-r from-green-600 via-emerald-500 to-green-400' : 'bg-gradient-to-r from-orange-600 via-amber-500 to-orange-400'"></div>
                    <div class="p-5 space-y-4">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center" :class="mode === 'in' ? 'bg-green-100' : 'bg-orange-100'">
                                    <span class="inline-flex" :class="mode === 'in' ? 'text-green-600' : 'text-orange-600'"><i data-lucide="scan-line" class="w-6 h-6"></i></span>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900" x-text="mode === 'in' ? 'Incoming — scan to receive' : 'Outgoing — scan to send'"></h2>
                                    <p class="text-xs text-gray-500">Works with any KLAES QR: tracking sheet, RofO, recommendation, commissioning sheet, consent, file label…</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <div class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-100 p-0.5">
                                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                            :class="mode === 'in' ? 'bg-white text-green-700 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-700'"
                                            @click="setMode('in')"><i data-lucide="log-in" class="h-3.5 w-3.5"></i> Receive</button>
                                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                            :class="mode === 'out' ? 'bg-white text-orange-700 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-700'"
                                            @click="setMode('out')"><i data-lucide="log-out" class="h-3.5 w-3.5"></i> Send</button>
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer" x-show="mode === 'in'">
                                    <input type="checkbox" x-model="autoReceive" @change="saveAutoReceive()" class="rounded border-gray-300 text-green-600">
                                    Receive automatically after scan
                                </label>
                                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100"
                                        @click="openManual()"><i data-lucide="pencil-line" class="h-3.5 w-3.5"></i> Log manually</button>
                            </div>
                        </div>

                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="qr-code" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <input type="text" x-ref="scanBox" x-model="scan.q"
                                   @input="onScanInput()" @keydown="onScanKeydown($event)" @paste="onScanPaste()"
                                   @focus="scanFocused = true" @blur="scanFocused = false"
                                   class="w-full border-2 rounded-xl pl-12 pr-40 py-3.5 text-base focus:outline-none"
                                   :class="scanFocused ? 'border-indigo-400 sfl-scan-ready' : 'border-gray-200'"
                                   placeholder="Scan the QR code now — or type a tracking ID / file number and press Enter" autocomplete="off" spellcheck="false">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2">
                                <span class="hidden md:inline-flex items-center gap-1.5 text-xs" :class="scanFocused ? 'text-green-600' : 'text-gray-400'">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75" :class="scanFocused ? 'bg-green-400 animate-ping' : ''"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="scanFocused ? 'bg-green-500' : 'bg-gray-300'"></span>
                                    </span>
                                    <span x-text="scan.loading ? 'Looking up…' : (scanFocused ? 'Scanner ready' : 'Click to scan')"></span>
                                </span>
                                <button type="button" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg disabled:opacity-50"
                                        :disabled="scan.loading || !scan.q.trim()" @click="resolve()">Find</button>
                            </div>
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
                                        <div class="text-xs text-gray-500" x-text="(nice(m.file_title) || '—') + ' · ' + m.source"></div>
                                    </button>
                                </template>
                            </div>
                        </template>
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
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="b.cls" x-text="b.label"></span>
                                    </template>
                                </div>
                                <p class="text-gray-600 mt-0.5" x-text="profileTitle()"></p>
                                <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">Now at</div>
                                        <div class="font-semibold text-gray-800" x-text="nice(profile.data && profile.data.tracker && profile.data.tracker.current_office) || 'Not tracked yet'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">Tracking ID</div>
                                        <div class="font-mono text-gray-700 text-xs mt-0.5" x-text="(profile.data && profile.data.tracker && profile.data.tracker.tracking_id) || (profile.match && profile.match.tracking_id) || '—'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">Location</div>
                                        <div class="text-gray-700" x-text="profileLocation()"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase tracking-wide">EDMS pages</div>
                                        <div class="text-gray-700" x-text="profile.data ? profile.data.edms.total : '…'"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex lg:flex-col gap-2 flex-shrink-0">
                                <button type="button" x-show="canReceive()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow-sm disabled:opacity-50"
                                        :disabled="busy" @click="receiveProfile()">
                                    <i data-lucide="log-in" class="w-4 h-4"></i> Receive file
                                </button>
                                <button type="button" x-show="canSend()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm"
                                        @click="startSendFromProfile()">
                                    <i data-lucide="send" class="w-4 h-4"></i> Send to next office
                                </button>
                                <div x-show="!canReceive() && !canSend() && heldHere()" class="px-3 py-2 text-xs rounded-lg bg-green-50 text-green-700 border border-green-200">Held at your office</div>
                                <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50" @click="closeProfile()">
                                    <i data-lucide="x" class="w-4 h-4"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Send form (inline) --}}
                    <div x-show="send.row" x-transition class="px-6 py-4 bg-orange-50 border-b border-orange-100">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Next office *</label>
                                <select x-model="send.to_office" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2 bg-white">
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
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Purpose</label>
                                <input type="text" x-model="send.purpose" list="sfl-purposes" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2" placeholder="e.g. For approval">
                                <datalist id="sfl-purposes">
                                    <option value="For approval"></option><option value="For minuting"></option><option value="For signature"></option>
                                    <option value="For information"></option><option value="For necessary action"></option><option value="Returned"></option>
                                </datalist>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                                <input type="text" x-model="send.notes" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                            </div>
                            <div class="flex gap-2 justify-end">
                                <button type="button" class="px-3 py-2 text-sm text-gray-600" @click="send.row = null">Cancel</button>
                                <button type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50"
                                        :disabled="busy || !send.to_office" @click="forward()"><i data-lucide="send" class="w-4 h-4"></i> Track to next office</button>
                            </div>
                        </div>
                    </div>

                    {{-- Profile tabs --}}
                    <div class="px-6 border-b border-gray-100">
                        <nav class="flex gap-6 text-sm">
                            <button type="button" class="py-3 border-b-2 border-transparent text-gray-500" :class="profile.tab === 'history' && 'sfl-tab-active'" @click="profile.tab = 'history'">
                                Log history <span class="ml-1 text-xs px-1.5 rounded bg-gray-100" x-text="profile.data ? profile.data.history.length : 0"></span>
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
                                {{-- History --}}
                                <div x-show="profile.tab === 'history'" class="sfl-scroll pr-2">
                                    <template x-if="!profile.data.history.length">
                                        <p class="text-sm text-gray-400 py-6 text-center">No movement has been logged for this file yet. Receiving it will start its log.</p>
                                    </template>
                                    <ol class="relative border-l-2 border-gray-100 ml-2">
                                        <template x-for="(h, i) in profile.data.history" :key="i">
                                            <li class="mb-5 ml-5">
                                                <span class="absolute -left-2 rounded-full sfl-timeline-dot border-2 border-white" :class="statusDot(h.status)"></span>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-gray-800" x-text="nice(h.office)"></span>
                                                    <span class="text-xs px-2 py-0.5 rounded-full" :class="statusPill(h.status)" x-text="statusLabel(h.status)"></span>
                                                    <span x-show="h.purpose" class="text-xs text-indigo-600" x-text="h.purpose"></span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1 space-x-3">
                                                    <span x-show="h.in"><i data-lucide="log-in" class="inline w-3 h-3"></i> <span x-text="fmt(h.in)"></span></span>
                                                    <span x-show="h.out"><i data-lucide="log-out" class="inline w-3 h-3"></i> <span x-text="fmt(h.out)"></span></span>
                                                    <span x-show="!h.in && h.at" x-text="'Sent ' + fmt(h.at)"></span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    <span x-show="h.by" x-text="'By ' + nice(h.by)"></span>
                                                    <span x-show="h.accepted_by" x-text="' · Received by ' + nice(h.accepted_by)"></span>
                                                </div>
                                                <div x-show="h.notes" class="text-xs text-gray-600 mt-1 bg-gray-50 rounded px-2 py-1 inline-block" x-text="h.notes"></div>
                                            </li>
                                        </template>
                                    </ol>
                                </div>

                                {{-- EDMS --}}
                                <div x-show="profile.tab === 'edms'">
                                    <template x-if="!profile.data.edms.pages.length">
                                        <div class="py-8 text-center text-gray-400">
                                            <i data-lucide="file-x" class="w-8 h-8 mx-auto"></i>
                                            <p class="mt-2 text-sm" x-text="'No EDMS documents for this file.'"></p>
                                        </div>
                                    </template>
                                    <p x-show="profile.data.edms.untyped" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-3"
                                       x-text="profile.data.edms.typed + ' page-typed · ' + profile.data.edms.untyped + ' uploaded and awaiting page typing'"></p>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
                                        <template x-for="(p, i) in profile.data.edms.pages" :key="p.id">
                                            <button type="button" class="group text-left border border-gray-200 rounded-lg overflow-hidden hover:shadow-md hover:border-indigo-300 transition"
                                                    @click="openLightbox(profile.data.edms.pages, i)">
                                                <div class="sfl-thumb bg-gray-100 flex items-center justify-center overflow-hidden">
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
                                                    <div class="text-xs truncate" :class="p.untyped ? 'text-amber-600' : 'text-gray-400'"
                                                         x-text="p.untyped ? ('Awaiting page typing' + (p.uploaded_at ? ' · ' + fmt(p.uploaded_at).slice(0, 11) : '')) : ((p.code || '') + (p.page ? ' · p' + p.page : ''))"></div>
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
                                       :class="manual.entry_type === opt.value ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" class="mt-1" :value="opt.value" x-model="manual.entry_type" @change="manualTypeChanged()">
                                    <div>
                                        <div class="font-semibold text-sm text-gray-800 flex items-center gap-1.5">
                                            <i :data-lucide="opt.icon" class="w-4 h-4 text-indigo-500"></i><span x-text="opt.label"></span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5" x-text="opt.hint"></div>
                                    </div>
                                </label>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div x-show="manual.entry_type !== 'non_file'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">File number *</label>
                                <div class="flex gap-2">
                                    <input type="text" :value="manual.file_number" readonly
                                           class="flex-1 border border-gray-300 bg-gray-50 rounded-lg px-3 py-2 text-sm" placeholder="Use the file number selector">
                                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg" @click="pickFileNumber()">
                                        <i data-lucide="search" class="w-4 h-4"></i> Select
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="manual.entry_type === 'non_file' ? 'Title / subject *' : 'File title'"></label>
                                <input type="text" x-model="manual.file_title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div x-show="manual.entry_type === 'non_file'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Sender</label>
                                <input type="text" x-model="manual.sender" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Person or organisation">
                            </div>
                            <div x-show="manual.entry_type === 'non_file'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Reference no.</label>
                                <input type="text" x-model="manual.reference" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Received from (office)</label>
                                <select x-model="manual.from_office" class="w-full border border-gray-300 rounded-lg text-sm px-2 py-2">
                                    <option value="">— Not from a KLAES office / unknown —</option>
                                    <template x-for="o in offices.filter(i => i.office_code !== office)" :key="'from-' + o.office_code">
                                        <option :value="o.office_code" x-text="o.office_name.trim() + ' (' + o.office_code + ')'"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                                <textarea x-model="manual.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="px-4 py-2 text-sm text-gray-600" @click="manual.open = false">Cancel</button>
                            <button type="button" class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg disabled:opacity-50"
                                    :disabled="busy || !manualValid()" @click="receiveManual()"><i data-lucide="check" class="w-4 h-4"></i> Create log &amp; receive</button>
                        </div>
                    </div>
                </div>

                {{-- Register --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="px-5 pt-3 border-b border-gray-100 flex flex-wrap items-end justify-between gap-3">
                        <nav class="flex gap-5 text-sm">
                            <template x-for="t in registerTabs" :key="t.key">
                                <button type="button" class="pb-3 border-b-2 border-transparent text-gray-500" :class="tab === t.key && 'sfl-tab-active'" @click="tab = t.key">
                                    <span x-text="t.label"></span>
                                    <span class="ml-1 px-1.5 rounded text-xs" :class="t.badge" x-text="lists[t.key].length"></span>
                                </button>
                            </template>
                        </nav>
                        <div class="flex items-center gap-2 pb-2 text-sm">
                            <div class="relative">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-2 top-2"></i>
                                <input type="text" x-model="filter" placeholder="Filter…" class="border border-gray-300 rounded-lg pl-8 pr-2 py-1.5 text-sm w-52">
                            </div>
                            <select x-show="tab === 'sent'" x-model.number="days" @change="loadLists()" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="1">Today</option>
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                            <button type="button" class="p-2 text-gray-500 hover:text-indigo-600 rounded-lg hover:bg-gray-50" title="Refresh" @click="loadLists()">
                                <span class="inline-flex" :class="listsLoading && 'animate-spin'"><i data-lucide="refresh-cw" class="w-4 h-4"></i></span>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <tr>
                                    <th class="px-5 py-3 text-left">File / document</th>
                                    <th class="px-5 py-3 text-left" x-text="tab === 'sent' ? 'Sent to' : 'From'"></th>
                                    <th class="px-5 py-3 text-left" x-text="tab === 'sent' ? 'Sent' : (tab === 'held' ? 'Received' : 'Sent at')"></th>
                                    <th class="px-5 py-3 text-left">Purpose / notes</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in visibleRows()" :key="tab + '-' + row.id">
                                    <tr class="border-t border-gray-100 hover:bg-indigo-50 cursor-pointer" @click="openRow(row)">
                                        <td class="px-5 py-3">
                                            <div class="font-semibold text-gray-800" x-text="row.file_number || nice(row.file_title)"></div>
                                            <div class="text-xs text-gray-500" x-show="row.file_number" x-text="nice(row.file_title)"></div>
                                            <div class="text-xs mt-0.5 space-x-1">
                                                <span class="text-gray-400 font-mono" x-text="row.tracking_id"></span>
                                                <span x-show="row.file_type === 'NON_FILE'" class="px-1.5 rounded bg-blue-100 text-blue-700">Not a regular file</span>
                                                <span x-show="row.not_indexed" class="px-1.5 rounded bg-orange-100 text-orange-700">Not indexed</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-gray-700" x-text="nice(tab === 'sent' ? row.to : row.from) || '—'"></td>
                                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap" x-text="fmt(row.since)"></td>
                                        <td class="px-5 py-3 text-gray-600">
                                            <div x-text="row.purpose || ''"></div>
                                            <div class="text-xs text-gray-400" x-text="row.notes || ''"></div>
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap" @click.stop>
                                            <template x-if="tab === 'pending'">
                                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50" :disabled="busy" @click="receivePending(row)">
                                                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i> Receive
                                                </button>
                                            </template>
                                            <template x-if="tab === 'held'">
                                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-lg" @click="openRow(row, true)">
                                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Send
                                                </button>
                                            </template>
                                            <template x-if="tab === 'sent'">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold"
                                                      :class="row.state === 'Received' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800'"
                                                      x-text="row.state"></span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!visibleRows().length">
                                    <td colspan="5" class="px-5 py-10 text-center text-gray-400">
                                        <i data-lucide="inbox" class="w-8 h-8 mx-auto"></i>
                                        <p class="mt-2" x-text="listsLoading ? 'Loading…' : 'Nothing here.'"></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lightbox --}}
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
