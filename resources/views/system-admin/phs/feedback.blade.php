@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Complaints raised by organizations about incomplete or wrong transactions.'])

    <div class="flex-1 p-6">
        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-amber-50 text-amber-700 ring-1 ring-amber-100">
                        <i data-lucide="message-square-warning" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Feedback &amp; Complaints</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Issues reported by members about incomplete or wrong transactions in their search slips.</p>
                    </div>
                </div>
                <a href="{{ route('system-admin.phs.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Back
                </a>
            </div>

            {{-- Status filter chips --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @php
                    $chips = [
                        '' => 'All',
                        'open' => 'Open',
                        'in_review' => 'In review',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ];
                @endphp
                @foreach ($chips as $value => $label)
                    @php $active = ($statusFilter ?? '') === $value; @endphp
                    <a href="{{ route('system-admin.phs.feedback') }}{{ $value ? '?status=' . $value : '' }}"
                       class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold {{ $active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                        @if ($value && isset($statsByStatus[$value]))
                            <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-white/20 px-1 text-[10px] {{ $active ? 'text-white' : 'text-slate-500' }}">{{ $statsByStatus[$value] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @forelse ($items as $item)
                @php
                    $statusStyles = [
                        'open' => 'bg-amber-50 text-amber-700',
                        'in_review' => 'bg-blue-50 text-blue-700',
                        'resolved' => 'bg-emerald-50 text-emerald-700',
                        'closed' => 'bg-slate-100 text-slate-500',
                    ];
                    $style = $statusStyles[$item->status] ?? 'bg-slate-100 text-slate-500';
                @endphp
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white">{{ $item->categoryLabel() }}</span>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $style }}">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</span>
                                @if ($item->file_number)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                                        <i data-lucide="folder" class="h-3 w-3"></i> {{ $item->file_number }}
                                    </span>
                                @endif
                                @if ($item->reference_no)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-mono text-slate-500">{{ $item->reference_no }}</span>
                                @endif
                            </div>

                            @if ($item->subject)
                                <h3 class="mt-3 font-bold text-slate-900">{{ $item->subject }}</h3>
                            @endif
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $item->message }}</p>

                            <p class="mt-3 text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">{{ \Illuminate\Support\Str::title(optional($item->institution)->name) ?: 'Unknown organization' }}</span>
                                @if (optional($item->member)->name) · {{ \Illuminate\Support\Str::title($item->member->name) }} @endif
                                · {{ optional($item->created_at)->format('M j, Y g:i A') }}
                            </p>

                            @if ($item->admin_response)
                                <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <p class="mb-0.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Staff response</p>
                                    {{ $item->admin_response }}
                                </div>
                            @endif
                        </div>

                        {{-- Status / response form --}}
                        <form method="POST" action="{{ route('system-admin.phs.feedback.update', ['id' => $item->id]) }}"
                              class="w-full shrink-0 space-y-2 lg:w-72">
                            @csrf
                            <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @foreach (['open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $val => $label)
                                    <option value="{{ $val }}" @selected($item->status === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="admin_response" rows="3" maxlength="2000" placeholder="Response to the organization (optional)"
                                      class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $item->admin_response }}</textarea>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">
                                <i data-lucide="save" class="h-3.5 w-3.5"></i> Update
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500 shadow-sm">
                    No feedback has been submitted yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
