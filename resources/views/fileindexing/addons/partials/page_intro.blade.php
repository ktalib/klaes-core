<div class="page-intro">
    <h1 data-create-indexing-breadcrumb>{{ $PageTitle ?? 'Create File Index' }}</h1>
    <p>
        {{ $PageDescription ?? 'Provide the full set of archival, property, and certificate data to create a digital record for this file. The form mirrors the indexing dialog so the transition from grouping to indexing stays familiar.' }}
    </p>
    <div class="page-actions">
        <a href="{{ route('fileindexing.index') }}">
            <span>&larr; Back to File Indexing Dashboard</span>
        </a>
    </div>
</div>
