{{--
    Super Admin override for config('fileindexing.non_indexable_years').
    Rendered for Super Admins only. When ticked, the File Number selector stops
    refusing those years and the save posts override_year_block=1, which
    App\Support\FileIndexingYearPolicy honours only for a Super Admin.
--}}
@php
    $fiOverrideYears = \App\Support\FileIndexingYearPolicy::blockedYears();
@endphp
@if(\App\Support\FileIndexingYearPolicy::canOverride(auth()->user()))
    <label for="fileindexing-year-override"
        class="mt-2 flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 cursor-pointer">
        <input type="checkbox" id="fileindexing-year-override" name="override_year_block" value="1"
            class="mt-0.5 h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
        <span>
            <strong>Override year restriction (Super Admin)</strong><br>
            Allow indexing a {{ implode(' / ', $fiOverrideYears) }} file number.
        </span>
    </label>
@endif
