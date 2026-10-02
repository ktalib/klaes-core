{{--
    Years the File Number selector must refuse on the indexing form.

    One source of truth — config('fileindexing.non_indexable_years') — read by
    create-indexing-dialog.js and by the legacy edit form, both of which pass it
    to GlobalFileNoModal as `blockedYears` on the PRIMARY file-number pickers only.
    Related-file and counterpart pickers are left alone: pointing at a 2026 file is
    not indexing it.

    Include this on any page that hosts the indexing form, BEFORE the scripts.
    A page that forgets it simply gets no restriction, and the dialog says so in
    the console.
--}}
<script>
    window.FILEINDEXING_NON_INDEXABLE_YEARS = @json(array_map('intval', (array) config('fileindexing.non_indexable_years', [])));
</script>
