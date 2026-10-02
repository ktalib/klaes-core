@extends('layouts.app')

@php
    // Reached from the Legal Search module (?url=ls) every token is a legal search,
    // so the reason is fixed rather than chosen.
    $lockPaymentReason = request('url') === 'ls';

    $formatTitle = static function ($value, string $fallback = '—'): string {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return \Illuminate\Support\Str::of($text)
            ->squish()
            ->lower()
            ->title()
            ->value();
    };

    $formatUpper = static function ($value, string $fallback = '—'): string {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        return \Illuminate\Support\Str::of($text)
            ->squish()
            ->upper()
            ->value();
    };
@endphp

@section('page-title')
    {{ __('Transaction Token Control (TTC)') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header', [
            'PageTitle' => 'Transaction Token Control (TTC)',
            'PageDescription' => 'Generate and manage legal search tokens for property verification.'
        ])

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Page Header --}}
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Transaction Token Control</h1>
                        <p class="text-slate-500 text-sm mt-1">Manage search tokens and tracking.</p>
                    </div>
                    <button type="button"
                        onclick="openTokenModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all shadow-sm hover:shadow-md active:scale-95">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Generate Token
                    </button>
                </div>

                {{-- Statistics Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="key" class="h-28 w-28 text-indigo-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 shadow-sm">
                                <i data-lucide="key" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Tokens</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ $tokens->total() }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="check-circle" class="h-28 w-28 text-emerald-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 shadow-sm">
                                <i data-lucide="check-circle" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Used Tokens</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ \App\Models\LegalSearchToken::where('is_used', true)->count() }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="alert-circle" class="h-28 w-28 text-amber-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100 shadow-sm">
                                <i data-lucide="alert-circle" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Unused Tokens</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ \App\Models\LegalSearchToken::where('is_used', false)->count() }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Records Table --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-indigo-600"></i>
                        <h3 class="font-bold text-slate-800">Generated Tokens</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1460px] text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                    <th class="px-6 py-4">S/N</th>
                                    <th class="px-6 py-4">Token</th>
                                    <th class="px-6 py-4">File Number</th>
                                    <th class="px-6 py-4">Applicant</th>
                                    <th class="px-6 py-4">Client Name</th>
                                    <th class="px-6 py-4">Client Address</th>
                                    <th class="px-6 py-4">Transaction Purpose</th>
                                    <th class="px-6 py-4">Receipt</th>
                                    <th class="px-6 py-4 text-center">Usage</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Created At</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[13px] text-slate-600">
                                @forelse($tokens as $token)
                                    @php
                                        $applicantName = $formatTitle($token->applicant_name, 'No applicant name');
                                        $propertyLocation = $formatTitle($token->property_location, 'No location provided');
                                        $clientName = $formatTitle($token->client_name);
                                        $clientAddress = $formatTitle($token->client_address);
                                        $receiptNumber = $formatUpper($token->receipt_number);
                                        $creatorName = $formatTitle(optional($token->creator)->name, 'System');
                                    @endphp
                                    <tr class="align-top hover:bg-slate-50/60 even:bg-slate-50/30 transition-colors">
                                        <td class="px-6 py-4 text-xs font-bold text-slate-400">
                                            {{ ($tokens->currentPage() - 1) * $tokens->perPage() + $loop->iteration }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2 cursor-pointer group" onclick="toggleToken(this, '{{ $token->token }}')">
                                                <span class="token-display font-mono font-bold text-indigo-600/40 tracking-widest">************</span>
                                                <i data-lucide="eye" class="h-3 w-3 text-slate-300 group-hover:text-indigo-400 transition-colors"></i>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-extrabold text-blue-700 border border-blue-100 tracking-wide uppercase">
                                                {{ $formatUpper($token->file_number) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 min-w-[220px]">
                                            <div class="font-semibold text-slate-900 leading-snug">{{ $applicantName }}</div>
                                            <div class="text-[11px] text-slate-500 mt-1 max-w-[260px] truncate" title="{{ $propertyLocation }}">
                                                {{ $propertyLocation }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 min-w-[180px]">
                                            <span class="text-sm font-semibold text-slate-900 leading-snug">{{ $clientName }}</span>
                                        </td>
                                        <td class="px-6 py-4 min-w-[240px]">
                                            <span class="text-sm text-slate-700 leading-snug">{{ $clientAddress }}</span>
                                        </td>
                                        <td class="px-6 py-4 min-w-[210px]">
                                            @if($token->payment_reason)
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 border border-indigo-100 text-[10px] font-bold text-indigo-700">{{ $formatTitle($token->payment_reason) }}</span>
                                                    @if($token->general_body)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-50 border border-amber-100 text-[10px] font-bold text-amber-700">{{ $formatUpper($token->general_body) }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-[11px] text-slate-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-xs font-extrabold text-slate-700 tracking-wide">{{ $receiptNumber }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-bold border border-slate-200 text-slate-700"
                                                  title="{{ $token->usage_count }} search(es) run on this token">
                                                <i data-lucide="repeat" class="h-3 w-3"></i>
                                                {{ $token->usage_count }}x
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if(!$token->is_used)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest whitespace-nowrap">
                                                    Unused
                                                </span>
                                            @elseif(!$token->is_expired)
                                                <div class="inline-flex flex-col items-center gap-1">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest whitespace-nowrap">
                                                        Active
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap"
                                                          title="Expires {{ $token->expires_at->format('d M, Y h:i A') }}">
                                                        {{ $token->expires_at->diffForHumans(null, true) }} left
                                                    </span>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-rose-50 text-rose-700 border border-rose-100 uppercase tracking-widest whitespace-nowrap"
                                                      title="Expired {{ $token->expires_at->format('d M, Y h:i A') }}">
                                                    Expired
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="text-xs text-slate-600 font-medium">{{ $token->created_at->format('d M, Y') }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $token->created_at->format('h:i A') }}</div>
                                            <div class="mt-1 text-[9px] font-bold text-indigo-500 uppercase tracking-tighter">By: {{ $creatorName }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button type="button"
                                                data-token-id="{{ $token->id }}"
                                                data-usage="{{ (int) $token->usage_count }}"
                                                data-file="{{ $token->file_number }}"
                                                onclick="openTokenActions(event, this)"
                                                class="inline-flex items-center justify-center p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                                                title="Actions">
                                                <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="px-6 py-12 text-center text-slate-400">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <i data-lucide="search" class="h-8 w-8 opacity-20"></i>
                                                <p>No tokens generated yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($tokens->hasPages())
                        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100">
                            {{ $tokens->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>

    {{-- Generate Token Modal --}}
    <div id="token-modal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeTokenModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="bg-indigo-600 px-6 py-5 flex items-center justify-between">
                <div class="flex items-center gap-3 text-white">
                    <div class="p-2 bg-white/20 rounded-xl">
                        <i data-lucide="key" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Transaction Token Control (TTC)</h3>
                        <p class="text-indigo-100 text-[11px] font-medium uppercase tracking-wider">New verification credential</p>
                    </div>
                </div>
                <button onclick="closeTokenModal()" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <form id="token-form" class="p-6 space-y-5">
                @csrf
                <div class="space-y-4">

                    {{-- General Search (statutory body request) --}}
                    <div class="p-4 bg-amber-50/40 rounded-2xl border border-amber-100">
                        <label class="inline-flex items-center gap-2 select-none {{ $lockPaymentReason ? 'cursor-not-allowed pointer-events-none' : 'cursor-pointer' }}">
                            {{-- checked + pointer-events-none rather than disabled, so the value still posts --}}
                            <input type="checkbox" id="is_general" name="is_general" value="1" checked
                                @if($lockPaymentReason) tabindex="-1" aria-readonly="true" @endif
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30 {{ $lockPaymentReason ? 'cursor-not-allowed opacity-70' : 'cursor-pointer' }}">
                            <span class="text-xs font-black text-slate-500 uppercase tracking-widest">General</span>
                        </label>
                        <div id="general-body-wrapper" class="hidden mt-3">
                            <label for="general_body" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Requesting Body</label>
                            <select id="general_body" name="general_body" disabled
                                class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                                <option value="" disabled selected>Select a requesting body...</option>
                                @foreach(\App\Models\LegalSearchToken::GENERAL_REASONS as $body)
                                    <option value="{{ $body }}">{{ $body }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                       {{-- Client Information (editable) --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Client Name</label>
                            <input type="text" id="client_name" name="client_name"
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none"
                                placeholder="Enter client name">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Client Address</label>
                            <input type="text" id="client_address" name="client_address"
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none"
                                placeholder="Enter client address">
                        </div>
                    </div>

                    <hr>
                    {{-- File Selection --}}
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">File Selection</label>
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <input type="text" id="file_number" name="file_number" readonly required
                                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none placeholder:font-normal"
                                    placeholder="Select a file number...">
                            </div>
                            <button type="button" onclick="openFileSelector()"
                                class="px-4 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-all shadow-sm flex items-center gap-2 font-bold text-sm">
                                <i data-lucide="search" class="h-4 w-4"></i>
                                Browse
                            </button>
                        </div>
                    </div>

                  

                    {{-- Prefilled Data (Read-only) --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Applicant Name</label>
                            <input type="text" id="applicant_name" name="applicant_name" readonly required
                                class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-sm font-medium text-slate-500 cursor-not-allowed outline-none"
                                placeholder="Auto-filled from selection...">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Property Location</label>
                            <input type="text" id="property_location_display" readonly
                                class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-sm font-medium text-slate-500 cursor-not-allowed outline-none"
                                placeholder="Auto-filled from selection...">
                            <input type="hidden" id="property_location" name="property_location">
                        </div>
                    </div>


                   

                    {{-- Payment Details Group --}}
                    <div class="p-5 bg-slate-50/50 rounded-2xl border border-slate-100 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="credit-card" class="h-4 w-4 text-indigo-600"></i>
                            <span class="text-xs font-black text-slate-600 uppercase tracking-widest">Payment Information</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" id="amount_paid" name="amount_paid" value="0">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Receipt No.</label>
                                <input type="text" id="receipt_number" name="receipt_number" required
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none"
                                    placeholder="RPT/...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Date Paid</label>
                                <input type="date" id="date_paid" name="date_paid" value="{{ date('Y-m-d') }}" required
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                            </div>
                        </div>
                        <div>
                            <label for="payment_reason" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Reason for Payment</label>
                            <select id="payment_reason" name="payment_reason" required
                                @if($lockPaymentReason) tabindex="-1" aria-readonly="true" @endif
                                class="w-full px-3 py-2.5 {{ $lockPaymentReason ? 'bg-slate-100 text-slate-500 cursor-not-allowed pointer-events-none' : 'bg-white text-slate-700' }} border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                                @if($lockPaymentReason)
                                    <option value="{{ \App\Models\LegalSearchToken::GENERAL_PAYMENT_REASON }}" selected>{{ \App\Models\LegalSearchToken::GENERAL_PAYMENT_REASON }}</option>
                                @else
                                    <option value="" disabled selected>Select a reason...</option>
                                    @foreach($paymentReasons as $reason)
                                        <option value="{{ $reason->name }}">{{ $reason->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>

                    {{-- Metadata (Read-only) --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 opacity-70">
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Generated By</label>
                            <input type="text" readonly value="{{ Auth::user()->name }}" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[11px] font-bold text-slate-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Time</label>
                            <input type="text" readonly value="{{ date('h:i A') }}" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[11px] font-bold text-slate-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Date</label>
                            <input type="text" readonly value="{{ date('d M, Y') }}" 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[11px] font-bold text-slate-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeTokenModal()"
                        class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg active:scale-95 flex items-center gap-2">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        Generate Token
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Row Actions Menu (shared, positioned by JS) --}}
    <div id="token-actions-menu" class="hidden fixed z-[1000] w-52 bg-white rounded-xl shadow-2xl border border-slate-200 py-1.5 text-sm">
        <button type="button" data-action="reset"
            class="w-full text-left px-3 py-2 flex items-center gap-2.5 text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
            <i data-lucide="rotate-ccw" class="h-4 w-4 text-indigo-500"></i>
            Token Reset
        </button>
        <div class="my-1 border-t border-slate-100" data-role="delete-divider"></div>
        <button type="button" data-action="delete"
            class="w-full text-left px-3 py-2 flex items-center gap-2.5 text-red-600 hover:bg-red-50 transition-colors">
            <i data-lucide="trash-2" class="h-4 w-4"></i>
            Delete Token
        </button>
    </div>

    {{-- Token Reset Modal --}}
    <div id="token-reset-modal" class="fixed inset-0 z-[1001] hidden items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeResetModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="bg-indigo-600 px-6 py-5 flex items-center justify-between">
                <div class="flex items-center gap-3 text-white">
                    <div class="p-2 bg-white/20 rounded-xl">
                        <i data-lucide="rotate-ccw" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Token Reset</h3>
                        <p class="text-indigo-100 text-[11px] font-medium uppercase tracking-wider">Grant a fresh 24-hour window</p>
                    </div>
                </div>
                <button onclick="closeResetModal()" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <div class="p-6 space-y-5">
                {{-- File + current usage summary --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">File Number</p>
                        <p id="reset-file-number" class="text-sm font-bold text-slate-800 break-words">—</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Times Used</p>
                        <p id="reset-usage" class="text-sm font-bold text-slate-800">—</p>
                    </div>
                </div>

                {{-- Reset action --}}
                <div class="space-y-2.5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Reset</p>
                    <button type="button" id="reset-confirm-btn"
                        class="reset-option group w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 transition-all text-left">
                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-amber-50 text-amber-600 text-sm font-black border border-amber-100">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Reset to Unused</span>
                            <span class="block text-[11px] text-slate-400">Clears usage; next use starts a fresh 24-hour window</span>
                        </span>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 ml-auto group-hover:text-indigo-500"></i>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end">
                <button type="button" onclick="closeResetModal()"
                    class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- Global File Number Selector Modal --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endsection

@push('scripts')
<script>
    function openTokenModal() {
        $('#token-modal').removeClass('hidden').addClass('flex');
    }

    function closeTokenModal() {
        $('#token-modal').addClass('hidden').removeClass('flex');
        $('#token-form')[0].reset();
        toggleGeneralBody();
    }

    // "General" is ticked by default — an ordinary client-paid search. Unticking it
    // marks the search as requested by a statutory body: the Requesting Body dropdown
    // appears, there is no client receipt (so the number is system generated) and the
    // reason is fixed to the legal search payment.
    const GENERAL_RECEIPT = @json(\App\Models\LegalSearchToken::GENERAL_RECEIPT);
    const GENERAL_PAYMENT_REASON = @json(\App\Models\LegalSearchToken::GENERAL_PAYMENT_REASON);
    const LOCK_PAYMENT_REASON = @json($lockPaymentReason);

    function toggleGeneralBody() {
        const isGeneral = $('#is_general').is(':checked');
        const needsBody = !isGeneral;
        const $receipt = $('#receipt_number');
        const $reason = $('#payment_reason');

        $('#general-body-wrapper').toggleClass('hidden', !needsBody);
        $('#general_body').prop('disabled', !needsBody).prop('required', needsBody);

        // readonly (not disabled) so the value still posts with the form
        $receipt.prop('readonly', needsBody)
            .toggleClass('bg-white text-slate-700', !needsBody)
            .toggleClass('bg-slate-100 text-slate-500 cursor-not-allowed', needsBody)
            .val(needsBody ? GENERAL_RECEIPT : '');

        if (needsBody) {
            if (!$reason.find('option[value="' + GENERAL_PAYMENT_REASON + '"]').length) {
                $reason.append($('<option>', { value: GENERAL_PAYMENT_REASON, text: GENERAL_PAYMENT_REASON }));
            }
            $reason.val(GENERAL_PAYMENT_REASON);
        } else {
            $('#general_body').val('');
            $reason.val(LOCK_PAYMENT_REASON ? GENERAL_PAYMENT_REASON : '');
        }
    }

    $(function () { toggleGeneralBody(); });

    $(document).on('change', '#is_general', toggleGeneralBody);

    function toggleToken(element, token) {
        const display = $(element).find('.token-display');
        const icon = $(element).find('i');
        const isMasked = display.text().includes('*');

        if (isMasked) {
            display.text(token).removeClass('text-indigo-600/40').addClass('text-indigo-600');
            icon.attr('data-lucide', 'eye-off');
        } else {
            display.text('************').removeClass('text-indigo-600').addClass('text-indigo-600/40');
            icon.attr('data-lucide', 'eye');
        }
        
        // Refresh Lucide icons
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function openFileSelector() {
        GlobalFileNoModal.open({
            callback: function(data) {
                console.log('File Selected:', data);
                $('#file_number').val(data.fileNumber);
                
                // Extract applicant name and location from record if available
                if (data.record) {
                    const record = data.record;
                    const applicant = record.file_title || record.file_name || record.FileName || '';
                    const location = record.location || record.Location || record.ma_location || '';
                    const lga = record.lga || record.LGA || record.fi_lga || '';
                    const locStr = `${location}${lga ? ', ' + lga : ''}`;

                    $('#applicant_name').val(applicant);
                    $('#property_location').val(locStr);
                    $('#property_location_display').val(locStr);
                }
            }
        });
    }

    $('#token-form').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="animate-spin h-4 w-4 border-2 border-white/30 border-t-white rounded-full"></i> Processing...');

        $.ajax({
            url: "{{ route('legal-search-tokens.store') }}",
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Token Generated!',
                        html: `<div class="text-slate-600 mb-2">A new legal search token has been successfully created.</div>
                               <div class="text-sm font-black text-indigo-600 tracking-[0.5em]">************</div>`,
                        icon: 'success',
                        confirmButtonText: 'Great!',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong';
                Swal.fire('Error', message, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ---- Row Actions Menu (Token Reset / Delete) ----
    let activeTokenId = null;
    let activeTokenUsage = 0;
    let activeTokenFile = '';

    function openTokenActions(event, btn) {
        event.stopPropagation();
        activeTokenId = parseInt(btn.getAttribute('data-token-id'), 10);
        activeTokenUsage = parseInt(btn.getAttribute('data-usage'), 10) || 0;
        activeTokenFile = btn.getAttribute('data-file') || '';

        const menu = document.getElementById('token-actions-menu');
        const rect = btn.getBoundingClientRect();

        // A token that has been used at least once can no longer be deleted (only reset) —
        // preserves the audit trail of a spent token.
        const deleteBtn = menu.querySelector('[data-action="delete"]');
        const deleteDivider = menu.querySelector('[data-role="delete-divider"]');
        const canDelete = activeTokenUsage === 0;
        deleteBtn.classList.toggle('hidden', !canDelete);
        deleteDivider.classList.toggle('hidden', !canDelete);

        // Show first (to measure), then position anchored to the button's right edge.
        menu.classList.remove('hidden');
        const menuWidth = menu.offsetWidth;
        let left = rect.right - menuWidth;
        if (left < 8) left = 8;
        let top = rect.bottom + 6;
        if (top + menu.offsetHeight > window.innerHeight - 8) {
            top = rect.top - menu.offsetHeight - 6;
        }
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';

        if (window.lucide) window.lucide.createIcons();
    }

    function closeTokenActions() {
        document.getElementById('token-actions-menu').classList.add('hidden');
    }

    // Close the menu on any outside click or scroll.
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('token-actions-menu');
        if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
            closeTokenActions();
        }
    });
    window.addEventListener('scroll', closeTokenActions, true);

    // Wire the menu buttons.
    document.querySelectorAll('#token-actions-menu [data-action]').forEach(function(item) {
        item.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            closeTokenActions();
            if (activeTokenId === null) return;
            if (action === 'delete') {
                deleteToken(activeTokenId);
            } else if (action === 'reset') {
                openResetModal();
            }
        });
    });

    // ---- Token Reset Modal ----
    function openResetModal() {
        document.getElementById('reset-file-number').textContent = activeTokenFile || '—';
        document.getElementById('reset-usage').textContent = activeTokenUsage;
        $('#token-reset-modal').removeClass('hidden').addClass('flex');
        if (window.lucide) window.lucide.createIcons();
    }

    function closeResetModal() {
        $('#token-reset-modal').addClass('hidden').removeClass('flex');
    }

    // Wire the reset action inside the reset modal.
    document.getElementById('reset-confirm-btn')?.addEventListener('click', function() {
        closeResetModal();
        resetToken(activeTokenId);
    });

    function resetToken(id) {
        Swal.fire({
            title: `Reset to Unused?`,
            html: `This clears the token's usage. Its 24-hour validity window will start fresh from its next use.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#64748b',
            confirmButtonText: `Yes, reset`
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: `/legal-search-tokens/${id}/reset`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Reset!', response.message, 'success').then(() => {
                            window.location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong';
                    Swal.fire('Error', message, 'error');
                }
            });
        });
    }

    function deleteToken(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/legal-search-tokens/${id}`,
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON.message, 'error');
                    }
                });
            }
        });
    }
</script>
@endpush
