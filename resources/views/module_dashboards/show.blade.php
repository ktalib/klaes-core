@extends('layouts.app')

@section('page-title')
    {{ $PageTitle }}
@endsection

{{-- A module's Dashboard (KLAES sidebar outline, 2026-11-10). Built by App\Services\ModuleDashboards. --}}
@section('content')
@php
    // Tailwind v2 CDN palette (no "amber"): card colour => [icon tile, value text].
    $mdTone = [
        'blue' => ['bg-blue-50 text-blue-600', 'text-blue-700'],
        'green' => ['bg-green-50 text-green-600', 'text-green-700'],
        'purple' => ['bg-purple-50 text-purple-600', 'text-purple-700'],
        'amber' => ['bg-yellow-50 text-yellow-600', 'text-yellow-700'],
    ];
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => $PageDescription])

    <div class="p-6 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($d['cards'] as $card)
                @php [$mdTile, $mdValue] = $mdTone[$card['colour']] ?? $mdTone['blue']; @endphp
                <{{ $card['url'] ? 'a' : 'div' }} @if ($card['url']) href="{{ $card['url'] }}" @endif
                    class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm{{ $card['url'] ? ' hover:border-gray-300 hover:shadow-md transition' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</div>
                            <div class="mt-2 text-3xl font-bold {{ $mdValue }}">{{ $card['total'] === null ? '—' : number_format($card['total']) }}</div>
                        </div>
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg {{ $mdTile }}">
                            <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">{{ $card['hint'] }}</div>
                    @if ($card['month'] !== null)
                        <div class="mt-3 border-t border-gray-100 pt-2 text-xs {{ $card['month'] > 0 ? 'text-green-700' : 'text-gray-400' }}">
                            {{ $card['month'] > 0 ? '+' . number_format($card['month']) : 'None' }} this month
                        </div>
                    @endif
                </{{ $card['url'] ? 'a' : 'div' }}>
            @endforeach
        </div>

        @if ($d['links'])
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Go to</h3>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($d['links'] as $link)
                        <a href="{{ $link['url'] }}" class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-200">
                            <i data-lucide="{{ $link['icon'] }}" class="h-4 w-4 text-gray-500"></i>
                            <span class="flex-1">{{ $link['label'] }}</span>
                            <i data-lucide="chevron-right" class="h-4 w-4 text-gray-300"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="text-xs text-gray-400">Figures refresh every {{ $d['cachedFor'] }} minutes. A dash means the figure's records are not set up yet.</p>
    </div>
</div>
@endsection
