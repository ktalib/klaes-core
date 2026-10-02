<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restores oss_applications.op_serial_number for OSS Change-of-Name rows left blank.
 *
 * The Change of Name page reads the OP serial straight off oss_applications, but the
 * capture flow only ever persisted it onto the pra row it created — so the column is
 * empty on most CoN applications and the page shows a blank OP SerialNo.
 *
 * The pra row is the surviving record of the serial. The mother OP is written before any
 * Transfer of Title row for the same file, so the lowest pra.id carries the original
 * serial; later ToT rows inherit it (see PraRecordService::isOssTransferOfTitleOp, which
 * zeroes serialNo/pageNo/volumeNo but deliberately keeps op_serial_number).
 *
 * Two classes of row are skipped rather than guessed at:
 *  - pra holds the literal '0' — a sentinel, not a serial.
 *  - no pra row carries a serial at all — nothing to restore from.
 *
 * Idempotent — only blank targets are touched. Use --dry-run to preview.
 */
class RestoreOssOpSerialNumbers extends Command
{
    protected $signature = 'oss:restore-op-serials {--dry-run : Show what would change without writing}';

    protected $description = 'Restore blank oss_applications.op_serial_number for Change-of-Name rows from pra';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $db = DB::connection('sqlsrv');

        // Latest application per file_no, mirroring the de-dupe the CoN page itself applies.
        $candidates = $db->select("
            WITH oa AS (
                SELECT id, file_no, op_serial_number,
                       ROW_NUMBER() OVER (PARTITION BY file_no ORDER BY id DESC) rn
                FROM oss_applications
                WHERE system_source = 'OSSOPCHANGEOFNAME'
                  AND (is_deleted IS NULL OR is_deleted = 0)
            )
            SELECT b.id, b.file_no,
                   (SELECT TOP 1 LTRIM(RTRIM(p.op_serial_number))
                      FROM pra p
                     WHERE (p.mlsFNo = b.file_no OR p.fileno = b.file_no)
                       AND NULLIF(LTRIM(RTRIM(p.op_serial_number)), '') IS NOT NULL
                     ORDER BY p.id ASC) AS pra_op
              FROM oa b
             WHERE b.rn = 1
               AND NULLIF(LTRIM(RTRIM(b.op_serial_number)), '') IS NULL
             ORDER BY b.file_no
        ");

        if (empty($candidates)) {
            $this->info('Nothing to restore — every Change-of-Name row already carries an OP serial.');
            return self::SUCCESS;
        }

        $restored = $skippedNoSource = $skippedSentinel = 0;

        foreach ($candidates as $row) {
            if ($row->pra_op === null) {
                $skippedNoSource++;
                continue;
            }

            if ($row->pra_op === '0') {
                $skippedSentinel++;
                $this->line("  skip {$row->file_no} — pra holds sentinel '0'");
                continue;
            }

            $this->line("  {$row->file_no} -> {$row->pra_op}");

            if (! $dryRun) {
                $db->table('oss_applications')
                    ->where('id', $row->id)
                    ->update(['op_serial_number' => $row->pra_op]);
            }

            $restored++;
        }

        $verb = $dryRun ? 'Would restore' : 'Restored';
        $this->info("{$verb} {$restored} OP serial(s).");

        if ($skippedSentinel) {
            $this->warn("Skipped {$skippedSentinel} row(s) whose only source value was '0'.");
        }

        if ($skippedNoSource) {
            $this->warn("Skipped {$skippedNoSource} row(s) with no pra serial to restore from.");
        }

        return self::SUCCESS;
    }
}
