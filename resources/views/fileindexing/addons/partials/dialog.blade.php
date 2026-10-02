<div class="dialog-overlay hidden" id="new-file-dialog-overlay">
    <div class="dialog">
        <div class="dialog-header">
            <div class="dialog-title">
                <i data-lucide="file-plus" class="h-5 w-5"></i>
                Create New File Index
            </div>
            <button id="close-dialog-btn" class="text-white" style="background: none; border: none; cursor: pointer;">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <div class="dialog-description flex items-center justify-between gap-3">
            <span>Enter the details for the new file to be indexed</span>
            {{-- Opens the Property Transaction Details modal directly (backfills from the
                 selected File Number), without creating/updating the file index. --}}
            <button type="button"
                class="shrink-0 inline-flex items-center gap-2 px-4 py-2 border border-emerald-300 text-sm font-medium rounded-lg shadow-sm text-emerald-700 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200"
                id="add-property-transaction-btn">
                <i data-lucide="file-plus-2" class="h-4 w-4"></i>
                Add Property Transaction Details
            </button>
        </div>
        <div class="dialog-content">
            <form
                id="new-file-form"
                data-default-indexer="{{ Auth::user()->name ?? 'Current User' }}"
                data-check-indexed-url="{{ route('fileindex.check-indexed') }}"
                data-indexed-view-url-template="{{ route('fileindexing.show', ['id' => '__ID__']) }}"
                data-transactions-url-template="{{ route('fileindexing.transactions', ['fileIndexing' => '__ID__']) }}"
            >
                @include('fileindexing.addons.partials.sections.file_identification')
                @include('fileindexing.addons.partials.sections.property_details')
                @include('fileindexing.addons.partials.sections.entity_customer')
                @include('fileindexing.addons.partials.sections.auto_assignment')
                @include('fileindexing.addons.partials.sections.file_flags')
                @include('fileindexing.addons.partials.sections.file_archive_details')
                @include('fileindexing.addons.partials.sections.cofo_details')
                @include('fileindexing.addons.partials.sections.form_actions')
            </form>
        </div>
    </div>
</div>
