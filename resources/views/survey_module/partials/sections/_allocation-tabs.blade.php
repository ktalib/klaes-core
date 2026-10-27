{{-- Allocation Records tabs: surveyor portion allocations, and Direct Allocation files from file indexings (2026-10-27). --}}
@php $alTab = $tab ?? 'surveyors'; @endphp
<style>
    .survey-proto .al-tabs { display: flex; gap: 4px; margin-bottom: 18px; border-bottom: 1px solid var(--gray-200, #e5e7eb); }
    .survey-proto .al-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 14px; color: var(--gray-600, #4b5563);
        border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; }
    .survey-proto .al-tabs a:hover { color: var(--gray-900, #111827); }
    .survey-proto .al-tabs a.on { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
</style>
<div class="al-tabs" role="tablist">
    <a href="{{ route('survey-module.compensation.allocation-records') }}" class="{{ $alTab === 'surveyors' ? 'on' : '' }}"
       role="tab" aria-selected="{{ $alTab === 'surveyors' ? 'true' : 'false' }}">
        <i class="fas fa-user-friends"></i> Surveyor Allocations
    </a>
    <a href="{{ route('survey-module.compensation.allocation-records', ['tab' => 'direct']) }}" class="{{ $alTab === 'direct' ? 'on' : '' }}"
       role="tab" aria-selected="{{ $alTab === 'direct' ? 'true' : 'false' }}">
        <i class="fas fa-folder-open"></i> Direct Allocation Files
    </a>
</div>
