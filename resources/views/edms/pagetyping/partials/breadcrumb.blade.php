<nav class="breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            @if($fileIndexing->recertification_application_id)
                <a href="{{ route('edms.recertification.index', $fileIndexing->recertification_application_id) }}" class="breadcrumb-link">
                    EDMS Workflow
                </a>
            @elseif($fileIndexing->subapplication_id)
                <a href="{{ route('edms.index', [$fileIndexing->main_application_id, 'sub']) }}" class="breadcrumb-link">
                    EDMS Workflow
                </a>
            @elseif($fileIndexing->main_application_id)
                <a href="{{ route('edms.index', $fileIndexing->main_application_id) }}" class="breadcrumb-link">
                    EDMS Workflow
                </a>
            @else
                <a href="#" class="breadcrumb-link">
                    EDMS Workflow
                </a>
            @endif
        </li>
        <li class="breadcrumb-separator">
            <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
        </li>
        <li class="breadcrumb-item">
            <span class="breadcrumb-current">Page Typing</span>
        </li>
    </ol>
</nav>
