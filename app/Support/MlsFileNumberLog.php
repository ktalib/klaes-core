<?php

namespace App\Support;

/**
 * Logger for the MLPP File Number screen (/mls-fileno) and its Commission New File
 * Number modal.
 *
 * Writes to storage/logs/mls_file_number.log — see the "mls_file_number" channel in
 * config/logging.php and the shared behaviour in {@see ChannelLog}.
 *
 * Covers the whole screen rather than just the insert, because a commissioning is
 * not one write: a single Generate reserves a serial, resolves prefix and land use,
 * allocates a prop_id, writes the fileNumber row, mirrors to PRA/instrument tables,
 * opens tracking lines and creates the EDMS folder — and the officer sees one
 * sentence when any of it fails. MlsFileNoController's own entries and the browser's
 * account of the modal (via MlsFileNumberDiagnosticsController::clientLog) land here
 * stamped with the same user/ip/route, so one commissioning reads as one timeline.
 *
 * Two neighbouring channels stay where they are, deliberately:
 *  - mls_batch / mls_batch_errors — Batch Mode generation, already separated so the
 *    per-file lines of a 50-file batch do not bury the single-file path.
 *  - fileno_duplicates — the duplicate/force-file-number audit, which is read on its
 *    own as a record of overrides rather than as a trace of one screen.
 *
 * Entries carry file numbers, serials, tracking ids and outcomes — the identifiers
 * needed to find a record — not a mirror of every field the officer typed.
 */
class MlsFileNumberLog extends ChannelLog
{
    public const CHANNEL = 'mls_file_number';
}
