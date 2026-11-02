@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
@php
    $naira = function ($v) {
        $v = (float) $v;
        return '₦' . number_format($v, abs($v - round($v)) < 0.005 ? 0 : 2);
    };
@endphp
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6 space-y-6">
        @if($canViewAll)
            {{-- Admin bar: see any indexer's progress exactly as they see it. --}}
            <form method="GET" class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-yellow-900"><i data-lucide="eye" class="h-4 w-4"></i> Admin view</span>
                <label for="ip-user" class="text-sm text-yellow-900">See the progress of</label>
                <select id="ip-user" name="user" class="border border-yellow-300 bg-white rounded-md px-3 py-2 text-sm" style="min-width:260px" onchange="this.form.submit()">
                    <option value="">— myself{{ $eligible || $viewingOther ? '' : ' (preview)' }} —</option>
                    @foreach($indexers as $id => $name)
                        <option value="{{ $id }}" @selected($viewingOther && (int) $target->id === (int) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="month" value="{{ $month }}">
                <span class="text-xs text-yellow-800">{{ count($indexers) }} indexers on indexing pay. They see the same card on their profile and the strip on Create File Index.</span>
            </form>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $viewingOther ? $targetName . ' — Indexing Earnings' : 'My Indexing Earnings' }}</h1>
                <p class="text-sm text-gray-500">Base fee for every file indexed, plus a rate for every record captured.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canSee && count($months) > 1)
                    <form method="GET" class="flex items-center gap-2">
                        @if($viewingOther)<input type="hidden" name="user" value="{{ $target->id }}">@endif
                        <label for="ip-month" class="text-sm text-gray-600">Month</label>
                        <select id="ip-month" name="month" class="border border-gray-300 rounded-md px-3 py-2 text-sm" onchange="this.form.submit()">
                            @foreach($months as $value => $label)
                                <option value="{{ $value }}" @selected($value === $month)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @if($canViewAll)
                    <a href="{{ route('indexing-earnings.sheet') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white hover:bg-gray-50">
                        <i data-lucide="users" class="h-4 w-4"></i> All indexers
                    </a>
                @endif
                <a href="{{ route('fileindexing.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-white bg-green-700 hover:bg-green-800">
                    <i data-lucide="file-plus" class="h-4 w-4"></i> Index a file
                </a>
            </div>
        </div>

        @unless($canSee)
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3"><i data-lucide="wallet" class="h-6 w-6 text-gray-400"></i></div>
                <h2 class="text-lg font-semibold text-gray-800">Indexing earnings are for indexing staff</h2>
                <p class="text-sm text-gray-500 mt-1">They show for users holding: <strong>{{ implode(', ', $modules) }}</strong>. Ask your administrator if you should have it.</p>
            </div>
        @else
            @if($viewingOther && !$eligible)
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">{{ $targetName }} does not hold {{ implode(', ', $modules) }}, so nothing they do is paid. These figures are shown for checking only.</div>
            @endif
            @include('indexing_pay.partials.earnings_card', [
                's' => $summary,
                'showDetailsLink' => false,
                'viewUserId' => $viewingOther ? $target->id : null,
                'preview' => !$eligible && !$viewingOther,
                'cardTitle' => $viewingOther ? $targetName . ' — Progress & Earnings' : 'My Indexing Progress & Earnings',
            ])

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                    <div>
                        <h2 class="font-semibold text-gray-800">Everything earned on {{ \Carbon\Carbon::parse($date)->format('l, j F Y') }}</h2>
                        <p class="text-sm text-gray-500">{{ number_format($dayTotals['files']) }} files · {{ number_format($dayTotals['records']) }} records · <b class="text-green-800">{{ $naira($dayTotals['amount']) }}</b></p>
                    </div>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="month" value="{{ $month }}">
                        @if($viewingOther)<input type="hidden" name="user" value="{{ $target->id }}">@endif
                        <label for="ip-date" class="text-sm text-gray-600">Day</label>
                        <input id="ip-date" type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left w-14">S/N</th>
                                <th class="px-4 py-3 text-left">Time</th>
                                <th class="px-4 py-3 text-left">File No.</th>
                                <th class="px-4 py-3 text-left">Item</th>
                                <th class="px-4 py-3 text-left">Paid as</th>
                                <th class="px-4 py-3 text-right">Earned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($dayItems as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-2.5 text-gray-500">{{ $item['time'] }}</td>
                                    <td class="px-4 py-2.5 font-medium text-gray-800">{{ $item['file_number'] ?: '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if($item['kind'] === 'file')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-800 border border-green-100 px-2 py-0.5 text-xs font-medium"><i data-lucide="file-check" class="h-3 w-3"></i> File indexed</span>
                                        @else
                                            {{ $item['label'] }}
                                        @endif
                                        @if($item['note'])<div class="text-xs text-gray-400">{{ $item['note'] }}</div>@endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $item['kind'] === 'file' ? $item['label'] : (\App\Services\IndexingPay\IndexingPaySettings::RECORDS[$item['kind']] ?? '') . ' record' }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $naira($item['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Nothing earned on this day.</td></tr>
                            @endforelse
                        </tbody>
                        @if(count($dayItems))
                            <tfoot><tr class="bg-green-50 font-bold text-green-900"><td colspan="5" class="px-4 py-3 text-right">Total</td><td class="px-4 py-3 text-right tabular-nums">{{ $naira($dayTotals['amount']) }}</td></tr></tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm text-gray-600">
                <h3 class="font-semibold text-gray-800 mb-2">How your pay is worked out</h3>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Every file you index through <b>Create File Index</b> earns its base fee once, on the day you index it. Editing a file later does not earn again.</li>
                    <li>Every Transaction, Ground Rent and Bill Balance record earns its rate for the person who <b>captured</b> it, on the day it was captured. A record entered twice is paid once.</li>
                    <li>A transaction counts when it names the instrument and has its serial, page or volume number.</li>
                    <li>Your month is the total of your days, weekends included. "At your pace" is your average per day so far × the days in the month.</li>
                </ul>
            </div>
        @endunless
    </div>
    @include('admin.footer')
</div>
@endsection
