{{-- Physical Planning > Bills & Payments: one central page, a tab per application type. --}}
@php
    $ppActiveTab = \App\Services\PhysicalPlanningBillSources::activeTab(request());
    $ppTabs = \App\Services\PhysicalPlanningBillSources::tabs($ppTabsPage);
@endphp
<div class="bg-white rounded-md shadow-sm border border-gray-200 mb-6">
    <nav class="flex flex-wrap px-4" aria-label="Application type">
        @foreach ($ppTabs as $ppTab)
            <a href="{{ $ppTab['url'] }}"
                class="px-4 py-3 text-sm font-medium border-b-2 -mb-px {{ $ppTab['key'] === $ppActiveTab ? 'border-red-600 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' }}"
                @if($ppTab['key'] === $ppActiveTab) aria-current="page" @endif>
                {{ $ppTab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
