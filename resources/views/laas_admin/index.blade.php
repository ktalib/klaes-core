@extends('layouts.app')

@section('page-title')
    {{ __('LAAS Portal — Applications') }}
@endsection

@section('content')
@php use App\Models\Laas\LaasApplication; @endphp

<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'LAAS Portal — Applications',
        'PageDescription' => 'Land allocation applications submitted through the public portal.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Application queue</h1>
                    <p class="text-slate-500 text-sm mt-1">Review, approve and assign file numbers to portal applications.</p>
                </div>
                <div class="flex items-center gap-2">
                <a href="{{ route('laas-admin.applicants') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="users" class="h-4 w-4"></i> Applicants
                </a>
                <a href="{{ route('laas-admin.alerts') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="bell" class="h-4 w-4"></i> Desk alerts
                    @if($unreadAlerts > 0)
                        <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $unreadAlerts }}</span>
                    @endif
                </a>
                </div>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            {{-- Stage filter chips --}}
            <div class="mb-5 flex flex-wrap gap-2">
                <a href="{{ route('laas-admin.index', ['q' => $search]) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $stage === '' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-100' }}">
                    All ({{ array_sum($counts) }})
                </a>
                @foreach(array_merge(array_slice(LaasApplication::ORDER, 1), [LaasApplication::STAGE_REJECTED]) as $s)
                    @continue(empty($counts[$s]))
                    <a href="{{ route('laas-admin.index', ['stage' => $s, 'q' => $search]) }}"
                       class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $stage === $s ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-100' }}">
                        {{ LaasApplication::label($s) }} ({{ $counts[$s] }})
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('laas-admin.index') }}" class="mb-5 flex flex-wrap gap-2">
                @if($stage !== '')<input type="hidden" name="stage" value="{{ $stage }}">@endif
                <input type="text" name="q" value="{{ $search }}"
                       placeholder="Search reference, applicant, phone or file number…"
                       class="min-w-[260px] flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm">
                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
                @if($search !== '' || $stage !== '')
                    <a href="{{ route('laas-admin.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Clear</a>
                @endif
            </form>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Applicant</th>
                                <th class="px-4 py-3">Land use / location</th>
                                <th class="px-4 py-3">File Number</th>
                                <th class="px-4 py-3">Stage</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3">Age</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($applications as $application)
                                @php
                                    $days = $application->submitted_at ? $application->submitted_at->diffInDays(now()) : null;
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ $application->reference_no }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-900">{{ $application->applicant_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $application->applicant_phone }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-slate-900">{{ $application->land_use }}</p>
                                        <p class="max-w-xs truncate text-xs text-slate-500">{{ $application->location }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $application->file_number ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold
                                            @if($application->stage === LaasApplication::STAGE_REJECTED) bg-red-100 text-red-700
                                            @elseif($application->stage === LaasApplication::STAGE_ROFO_SIGNED) bg-green-100 text-green-800
                                            @elseif($application->stage === LaasApplication::STAGE_SUBMITTED) bg-blue-100 text-blue-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ LaasApplication::label($application->stage) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $application->submitted_at?->format('j M Y') ?: '—' }}</td>
                                    <td class="px-4 py-3 text-xs {{ $days !== null && $days > 30 ? 'font-bold text-red-600' : 'text-slate-500' }}">
                                        {{ $days !== null ? $days . 'd' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('laas-admin.show', $application->id) }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                                            Open <i data-lucide="arrow-right" class="h-3 w-3"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-slate-500">No applications match this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">{{ $applications->links() }}</div>
        </div>
    </div>
</div>
@endsection
