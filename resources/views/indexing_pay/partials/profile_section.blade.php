{{-- Profile page: the indexer's progress & earnings card (Indexing Pay, 2026-11-01).
     Shown only to users who can earn, and only while switched on in Configurable Entries. --}}
@php
    $ipProfile = null;
    try {
        $ipEarnings = app(\App\Services\IndexingPay\EarningsService::class);
        $ipCardNow = app(\App\Services\IndexingPay\IndexingPaySettings::class)->current();
        $ipPreview = ! $ipEarnings->isEligible(auth()->user());
        if ($ipCardNow['show']['profile'] && \App\Http\Controllers\IndexingPay\IndexingEarningsController::canSeeProgress(auth()->user())) {
            [$ipStart, $ipEnd] = $ipEarnings->programme();
            $ipMonth = now()->format('Y-m');
            if ($ipMonth < substr($ipStart, 0, 7)) $ipMonth = substr($ipStart, 0, 7);
            if ($ipMonth > substr($ipEnd, 0, 7)) $ipMonth = substr($ipEnd, 0, 7);
            $ipProfile = $ipEarnings->summary(auth()->user(), $ipMonth);
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Indexing earnings card failed on profile', ['error' => $e->getMessage()]);
        $ipProfile = null;
    }
@endphp
@if($ipProfile)
    <div class="mt-6">
        @include('indexing_pay.partials.earnings_card', ['s' => $ipProfile, 'showDetailsLink' => true, 'preview' => $ipPreview])
    </div>
@endif
