<?php

/*
|--------------------------------------------------------------------------
| Land Registration
|--------------------------------------------------------------------------
|
| The Land Registry's own registration pipeline. It clones the Deeds screens
| (capture -> registration -> RDS/CoR) but writes to the SAME tables:
| instrument_capture, deed_registrations and instrument_number_vaults. The
| instrument type is what separates a Land Registration row from a Deeds row,
| so `instrument_type` below is the single value the whole module is scoped on.
|
| Everything the registry is likely to revise -- the type name, the card and
| party labels, the authority wording on the printed documents and the stamp
| text -- is here rather than spread through controllers and blades, because
| further amendments are expected.
|
| NOT here on purpose: the running Serial/Page/Volume numbers. Those live in
| the `instrument_number_vaults` row and are edited from the "Manage Instrument
| Types" modal, so the registry can renumber without a code change and without
| a deploy. `vault_seed` below is only the opening position, applied once by
| `php artisan land-registration:install` and never re-applied.
|
*/

return [

    /*
    | The instrument type. This exact string is written to
    | instrument_capture.instrument_type and deed_registrations.instrument_type,
    | is the name of the row in both lookup tables, and is the vault key.
    | Changing it after go-live orphans existing rows from the module -- rename
    | the data first if it ever has to change.
    */
    'instrument_type' => 'Deed of Purchase',

    /*
    | The key the capture JS uses for this instrument (public/js/instruments-capture.js).
    | Also the id of the <template> blocks on the capture page.
    */
    'type_key' => 'deed-of-purchase',

    /*
    | Role gating the sidebar section and the module's screens.
    */
    'role' => 'Land Registration',

    /*
    | Card copy on the capture screen.
    */
    'card' => [
        'title' => 'Deed of Purchase',
        'description' => 'A legal document that proves the transfer of ownership of a property '
            . 'or asset from a vendor to a purchaser.',
    ],

    /*
    | The description stored on the two lookup rows. Kept short deliberately:
    | it renders under the name inside a table cell in the Manage Instrument
    | Types modal, where the card's full sentence stretched the row and pushed
    | the Vault and Actions columns out of view.
    */
    'lut_description' => 'Sale and purchase of an interest in land.',

    /*
    | Party roles. The capture JS relabels the two party panels with these and
    | squashes both into the generic Grantor/Grantee fields before submit, so
    | InstrumentCaptureService::mapParties() needs no change.
    */
    'parties' => [
        'first' => 'Party 1',
        'second' => 'Party 2',
    ],

    /*
    | Authority block. Every document this module prints carries these instead
    | of the Deeds Registry / Registrar of Deeds wording. Deeds documents are
    | untouched -- they keep their own hardcoded strings.
    */
    'authority' => [
        'ministry' => 'KANO STATE MINISTRY OF LAND & PHYSICAL PLANNING',
        // `registry` alone also titles the register screen, so the department is
        // a separate key rather than a second line baked into it.
        'registry' => 'LAND REGISTRY',
        'department' => 'LAND DEPARTMENT',
        'signatory' => 'DIRECTOR LAND',
    ],

    /*
    | The registry's rubber stamp, reproduced on the bottom of the CoR. Set
    | typographically rather than as a scanned image so the particulars print
    | filled in and the block stays legible at the ~4cm slip size.
    |
    | `fill_particulars` prints the live Serial/Page/Volume in the blanks. Set
    | it to false to print empty rules for hand entry, as on the paper stamp.
    */
    'stamp' => [
        'enabled' => true,
        'heading' => 'DEEDS OF SALE REGISTERED',
        'registry_line' => 'OF THE LAND REGISTRY IN THE OFFICE AT KANO',
        'signatory' => 'DIRECTOR LAND',
        'fill_particulars' => true,
    ],

    /*
    |----------------------------------------------------------------------
    | Numbering rules
    |----------------------------------------------------------------------
    |
    | Per docs/KLAES_Deed_of_Purchase_Land_Registration_Update.md sections 10-14.
    | These are NOT the Deeds rules and must not be confused with them:
    |
    |   Deeds  serial resets at 300 AND that same event bumps the volume.
    |   Land   serial and page are four-digit counters that roll 9999 -> 1
    |          WITHOUT touching the volume; the volume advances on its own,
    |          after `volume_capacity` registrations have been written into it.
    |
    | So a Land volume can legitimately hold serial 4019, and a serial rollover
    | at 9999 leaves the volume exactly where it was.
    */
    'numbering' => [
        'serial_max' => 9999,
        'page_max' => 9999,
        'volume_capacity' => 300,
    ],

    /*
    | Opening position of the numbering series - where the digital register
    | picks up from the paper one. The install command applies it ONCE and
    | refuses to touch a vault that already exists, because re-seeding a live
    | counter re-issues serials that may already be printed on certificates.
    |
    | current_* are the LAST values used, not the next ones: the first digital
    | registration is current_serial + 1 / current_page + 1 / current_volume.
    |
    | Section 18 of the spec gives the last manual entry as serial 4019 / page
    | 4019 / volume 22, so the first system record is 4020/4020/22. Section 9
    | qualifies that: the handwritten serial and page must be VERIFIED against
    | the final manual entry before go-live. The volume (22) is confirmed.
    |
    | current_volume_count is how many registrations volume 22 already holds,
    | which is what the 300-entry capacity rule counts against. It is unknown
    | from the paper register alone - set it before go-live, or volume 22 will
    | run to 300 more entries than it should.
    */
    'vault_seed' => [
        'current_volume' => 22,
        'current_page' => 4019,
        'current_serial' => 4019,
        'current_volume_count' => 0,
    ],

];
