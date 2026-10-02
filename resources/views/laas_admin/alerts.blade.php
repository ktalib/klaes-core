@extends('layouts.app')

@section('page-title')
    {{ __('LAAS — Desk Alerts') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'LAAS — Desk Alerts',
        'PageDescription' => 'Portal applications that need action from the Land Office / OSS Unit.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <a href="{{ route('laas-admin.index') }}" class="mb-2 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to queue
                    </a>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Desk alerts</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Raised when Cadastral returns a completed survey report and a recommendation falls due.
                    </p>
                </div>

                <a href="{{ route('laas-admin.alerts', $showingAll ? [] : ['all' => 1]) }}"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ $showingAll ? 'Show unread only' : 'Show all alerts' }}
                </a>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if($alerts->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <i data-lucide="inbox" class="mx-auto mb-3 h-10 w-10 text-slate-400"></i>
                    <p class="font-semibold text-slate-900">{{ $showingAll ? 'No alerts have been raised yet.' : 'Nothing outstanding.' }}</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($alerts as $alert)
                        @php $application = $applications[$alert->laas_application_id] ?? null; @endphp
                        <div class="flex flex-wrap items-start gap-4 rounded-xl border bg-white p-5 shadow-sm {{ $alert->is_read ? 'border-slate-200 opacity-70' : 'border-amber-300' }}">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full {{ $alert->is_read ? 'bg-slate-100 text-slate-400' : 'bg-amber-100 text-amber-700' }}">
                                <i data-lucide="{{ $alert->is_read ? 'check' : 'bell-ring' }}" class="h-4 w-4"></i>
                            </div>

                            <div class="min-w-[240px] flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900">{{ $alert->title }}</p>
                                    <p class="text-xs text-slate-400">{{ $alert->created_at->format('j M Y, g:ia') }}</p>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $alert->message }}</p>
                                <p class="mt-1.5 text-xs text-slate-400">
                                    {{ $alert->department }}
                                    @if($application) · {{ $application->reference_no }} @endif
                                </p>
                            </div>

                            <div class="flex flex-shrink-0 items-center gap-2">
                                @if($application)
                                    <a href="{{ route('laas-admin.show', $application->id) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                                @endif
                                @unless($alert->is_read)
                                    <form method="POST" action="{{ route('laas-admin.alerts.read', $alert->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Clear</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">{{ $alerts->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
