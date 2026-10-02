<div class="flex flex-col gap-3 mb-3" data-section="file-number">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="w-full">
            <p class="text-xs italic text-red-700 bg-red-50 border border-red-200 rounded-md p-2">
                For Mortgage or Surrender & Release ensure to search for the Existing Temp FileNo, if not found then
                Select a New Temp FileNo.
            </p>
        </div>
        <label for="fileno-select" class="block text-sm font-medium text-gray-700">
            Select File Number <span class="text-red-500">*</span>
        </label>
    </div>
</div>

<div data-role="smart-wrapper">
    <div id="smart-fileno-container" data-role="smart-container">
        @include('propertycard.partials.smart_fileno_selector')
    </div>
</div>