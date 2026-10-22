<?php

return [

    /*
    | Legacy Parcel Update linkage groups (manual_file_linkages.linkage_group_id) whose
    | files are kept OUT of Land Recommendation batches until the lineage is confirmed.
    | They are not loaded as children/grandchildren of a mother, not counted in the
    | picker, and a batch that still carries them (e.g. from a saved draft) is refused.
    | Remove the entry to release them. No data is changed by holding a group.
    */
    'held_linkage_groups' => [
        // 2026-10-19 21:26 "Import by Range" CON-RES-2026-487 … 686 linked straight to
        // CON-AG-2010-110, which already has 217 grandchildren through its Change of
        // Purpose (CON-RES-2026-4611). Held 2026-10-22 until the user confirms where
        // these 200 belong.
        '71d27b85-4c52-4f15-b878-04d00fc72ec1' => 'CON-RES-2026-487 to 686 (range linked to CON-AG-2010-110) — lineage awaiting confirmation',
    ],

];
