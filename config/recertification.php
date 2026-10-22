<?php

return [
    /*
    | First KN number a recertification may issue to a land file that has no
    | KANGIS file yet. 9000 was set by the user on 2026-10-20; replace it with the KANGIS office's
    | real last number + 1 once they supply it (RECERT_KN_START in .env).
    */
    'kn_start' => (int) env('RECERT_KN_START', 9000),
];
