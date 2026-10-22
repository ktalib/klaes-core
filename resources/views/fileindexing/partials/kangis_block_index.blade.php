{{--
    KANGIS Block Indexing checkbox (user, 2026-10-17). On a KANGIS Registry index the
    land file is the Related File No, and it may be related to one KANGIS file only.
    Ticked, the save marks the land file "multiple KANGIS files" (its
    allow_multiple_kangis), so it may be related to any number of them; its
    kangis_link_count then counts up. create-indexing-dialog.js shows it while General
    Registry is KANGIS and ticks it when the operator answers Yes to the "already
    related" confirmation (App\Services\FileIndexing\KangisBlockIndexing).

    Rendered only for config('fileindexing.kangis_block_usernames') (admin1 for
    now); the controller re-checks the user.
--}}
@if(\App\Services\FileIndexing\KangisBlockIndexing::canUse(auth()->user()))
    <label for="kangis-block-index" id="kangis-block-index-wrapper"
        class="hidden mt-2 flex items-start gap-2 rounded-md border border-purple-300 bg-purple-50 px-3 py-2 text-xs text-purple-900 cursor-pointer">
        <input type="checkbox" id="kangis-block-index"
            class="mt-0.5 h-4 w-4 rounded border-purple-400 text-purple-600 focus:ring-purple-500">
        <span>
            <strong>KANGIS Block Indexing</strong><br>
            Allow the land file (Related File No) to be related to more than one KANGIS file.
        </span>
    </label>
@endif
