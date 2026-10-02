<div class="form-section">
    <div class="flex items-center gap-2 mb-4">
        <i data-lucide="archive" class="h-5 w-5 text-amber-600"></i>
        <h3 class="form-section-title" style="margin-bottom: 0;">Digital Archive Details</h3>
    </div>

    <hr class="mb-6 border-t border-gray-200">

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label for="archive-file-no" class="block text-sm font-medium text-gray-700 mb-2">File No</label>
            <input type="text" id="archive-file-no"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly data-permanent-readonly="true">
        </div>
        <div class="form-group">
            <label for="awaiting-file-no" class="block text-sm font-medium text-gray-700 mb-2">Awaiting file no</label>
            <div class="flex">
                <input type="text" id="awaiting-file-no"
                    class="flex-grow mr-2 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                    readonly data-permanent-readonly="true">
                <button type="button" id="refresh-archive-details-btn"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-500 hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    title="Refresh archive details from API">
                    <i data-lucide="refresh-cw" class="h-3 w-3"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="form-group">
            <label for="mdc-batch-no" class="block text-sm font-medium text-gray-700 mb-2">MDC Batch No</label>
            <input type="text" id="mdc-batch-no"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly data-permanent-readonly="true">
        </div>
        <div class="form-group">
            <label for="registry-batch-no" class="block text-sm font-medium text-gray-700 mb-2">Registry Batch
                No</label>
            <input type="text" id="registry-batch-no" name="registry_batch_no"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly data-permanent-readonly="true">
        </div>
        <div class="form-group">
            <label for="sys-batch-no" class="block text-sm font-medium text-gray-700 mb-2">SYS Batch No</label>
            <input type="text" id="sys-batch-no"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly data-permanent-readonly="true">
        </div>
    </div>

    {{-- Physical Registry moved to the General Registry card: the two ask the same
         question (which registry holds this file) and were being answered in two
         different places. This row keeps the batch/shelf fields, which describe
         where the SCAN lives. Three columns now, not four. --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="form-group">
            @php
                $registryAssignRole = strtolower((string) (auth()->user()->assign_role ?? ''));
                $canOverrideRegistry = in_array($registryAssignRole, ['super admin', 'supper admin', 'administrator', 'admin', 'editor'], true);
            @endphp
            <label for="registry" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-1">
                Registry
                <span class="text-xs text-gray-400" title="Auto-detected from the file number prefix + year">(auto)</span>
                @if($canOverrideRegistry)
                    <button type="button" id="registry-admin-unlock-btn" title="Admin override: unlock to set manually"
                        class="text-gray-400 hover:text-indigo-600 focus:outline-none">
                        <i data-lucide="lock" class="h-3 w-3 pointer-events-none"></i>
                    </button>
                @endif
            </label>
            <input type="text" id="registry" name="registry"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly
                @if(!$canOverrideRegistry) data-permanent-readonly="true" @endif
                data-admin-overridable="{{ $canOverrideRegistry ? 'true' : 'false' }}">
        </div>
        <div class="form-group">
            <label for="shelf-rack-no" class="block text-sm font-medium text-gray-700 mb-2">Shelf/Rack No</label>
            <input type="text" id="shelf-rack-no"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly data-permanent-readonly="true">
        </div>
        <div class="form-group">
            <label for="serial-no" class="block text-sm font-medium text-gray-700 mb-2">Serial No</label>
            <input type="text" id="serial-no"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                readonly data-permanent-readonly="true">
        </div>
    </div>



    <div class="grid grid-cols-2 gap-4 mt-4">
        <div class="form-group">
            <label for="indexed-by" class="block text-sm font-medium text-gray-700 mb-2">Indexed by</label>
            <input type="text" id="indexed-by"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none focus:ring-0 focus:border-gray-300 sm:text-sm"
                placeholder="Indexer name" disabled readonly data-permanent-readonly="true">
        </div>
        <div class="form-group">
            <label for="indexed-date" class="block text-sm font-medium text-gray-700 mb-2">Indexed Date</label>
            <input type="date" id="indexed-date"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-gray-500 focus:border-gray-500 sm:text-sm bg-gray-100 cursor-not-allowed"
                value="{{ date('Y-m-d') }}" readonly data-permanent-readonly="true">
        </div>
    </div>
</div>