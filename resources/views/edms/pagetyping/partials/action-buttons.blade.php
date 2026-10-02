<div class="action-buttons">
    <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn-back">
        <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
        Back to Document Scanning
    </a>

    <div style="display: flex; align-items: center; gap: 1rem;">
        @if($fileIndexing->recertification_application_id)
            <a href="{{ route('recertification.index') }}" class="btn-primary">
                <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                Finish EDMS
            </a>
        @elseif($fileIndexing->subapplication_id)
            <a href="{{ route('sectionaltitling.units') }}" class="btn-primary">
                <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                Finish EDMS
            </a>
        @elseif($fileIndexing->main_application_id)
            <a href="{{ route('sectionaltitling.primary') }}?url=infopro" class="btn-primary">
                <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                Finish EDMS
            </a>
        @else
            <a href="{{ url('/dashboard') }}" class="btn-primary">
                <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                Finish EDMS
            </a>
        @endif
    </div>
</div>
