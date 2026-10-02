<?php

namespace App\Services;

use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use Illuminate\Support\Facades\DB;

/**
 * Allocates the two identifiers a duplex runs on.
 *
 *   Duplex ID       DPX-2026-0007
 *   Holding number  DPX-2026-0007-H03
 *
 * The holding namespace is deliberately UNLIKE any registry file number and unlike
 * the "(T)" temporary convention. A "(T)" number is a real file with its own
 * indexing row, and FileLocationResolver / FileIndexingPropagationService treat it
 * as one; a holding number must never be picked up by any of that. It exists only
 * in duplex_parcel_update_files, is never written to fileNumber / file_indexings /
 * mls_file_no, and is retired when the duplex commits.
 */
class DuplexHoldingNumberService
{
    public const PREFIX = 'DPX';

    /**
     * What an Extension stage's holding number carries: DPX-2026-0007-H03 AND EXTENSION.
     *
     * An extension does not mint a number out of the series — it re-numbers the file
     * it receives as "<incoming> AND EXTENSION" — so a bare holding number said the
     * opposite of what the stage was going to do. Carrying the suffix through the plan
     * means the officer reads the same words on the wizard, on the commissioning modal
     * and on the file the registry ends up with.
     *
     * The suffix is presentational only: every serial computation and every registry
     * guard below reads through stripExtensionSuffix(), so it can never shift a serial
     * or open a hole in the namespace.
     */
    public const EXTENSION_SUFFIX = ' AND EXTENSION';

    /** Next free DPX-<year>-<serial> for this year. */
    public function allocateDuplexId(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $like = self::PREFIX . '-' . $year . '-%';

        // Serial is the max already issued this year + 1. Duplex IDs are never
        // re-used, even when a duplex is deleted, so the counter only moves forward.
        $max = 0;
        $existing = DuplexParcelUpdate::where('duplex_id', 'LIKE', $like)
            ->pluck('duplex_id');

        foreach ($existing as $id) {
            if (preg_match('/-(\d+)$/', (string) $id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-%d-%04d', self::PREFIX, $year, $max + 1);
    }

    /** DPX-2026-0007-H03 -> DPX-2026-0007-H03 AND EXTENSION. */
    public function withExtensionSuffix(string $holdingNo): string
    {
        return $this->stripExtensionSuffix($holdingNo) . self::EXTENSION_SUFFIX;
    }

    /** The bare holding number, whatever suffix it was displayed with. */
    public function stripExtensionSuffix(?string $value): string
    {
        return trim(preg_replace('/\s+AND\s+EXTENSION\s*$/i', '', (string) $value));
    }

    /** Apply the extension suffix to a whole list, in order. */
    public function withExtensionSuffixes(array $holdingNos): array
    {
        return array_map(fn ($no) => $this->withExtensionSuffix((string) $no), $holdingNos);
    }

    /**
     * Next holding number for a duplex: DPX-2026-0007-H03.
     *
     * Numbered per duplex and monotonic, so the Land screen can show the chain in
     * the order it was produced regardless of which stage emitted it.
     */
    public function allocateHoldingNumber(DuplexParcelUpdate $duplex): string
    {
        $max = 0;

        $existing = DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->whereNotNull('holding_no')
            ->pluck('holding_no');

        foreach ($existing as $no) {
            if (preg_match('/-H(\d+)(?:\s+AND\s+EXTENSION)?$/i', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-H%02d', $duplex->duplex_id, $max + 1);
    }

    /** Allocate several at once, in order. */
    public function allocateHoldingNumbers(DuplexParcelUpdate $duplex, int $count): array
    {
        // saveStage() deletes the stage's own rows before calling this, so nothing
        // needs excluding here — the same computation serves both.
        return $this->previewHoldingNumbers($duplex, $count);
    }

    /**
     * What the NEXT $count holding numbers would be, without issuing them.
     *
     * The officer needs to see a stage's holding numbers while filling it in, not only
     * after saving — the Change of Purpose especially, since the rest of the plan hangs
     * off them. This shares its implementation with allocateHoldingNumbers() rather
     * than restating the rule, so a preview can never drift from what is actually
     * issued.
     *
     * $excludeStageId drops a stage's own rows from the count, which is what makes a
     * re-save preview correctly: saveStage() clears them before allocating, so a stage
     * being filled in again reclaims the numbers it already holds.
     */
    public function previewHoldingNumbers(
        DuplexParcelUpdate $duplex,
        int $count,
        ?int $excludeStageId = null
    ): array {
        $numbers = [];
        $max = 0;

        $query = DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->whereNotNull('holding_no');

        if ($excludeStageId !== null) {
            $query->where(function ($q) use ($excludeStageId) {
                $q->whereNull('duplex_parcel_update_stage_id')
                    ->orWhere('duplex_parcel_update_stage_id', '!=', $excludeStageId);
            });
        }

        foreach ($query->pluck('holding_no') as $no) {
            if (preg_match('/-H(\d+)(?:\s+AND\s+EXTENSION)?$/i', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        for ($i = 1; $i <= $count; $i++) {
            $numbers[] = sprintf('%s-H%02d', $duplex->duplex_id, $max + $i);
        }

        return $numbers;
    }

    public function isHoldingNumber(?string $value): bool
    {
        // The " AND EXTENSION" an extension stage's holding number carries is part of
        // the holding namespace, not a way out of it: without this the commit guard
        // silently skipped exactly the rows it was written to check.
        return $value !== null
            && (bool) preg_match(
                '/^' . self::PREFIX . '-\d{4}-\d+-H\d+(?:\s+AND\s+EXTENSION)?$/i',
                trim($value)
            );
    }

    /**
     * Guard: a holding number must never appear in the live registry. Called before
     * commit so a coding slip surfaces as a refusal instead of a poisoned index.
     */
    public function assertNotInRegistry(string $holdingNo): void
    {
        $conn = DB::connection('sqlsrv');

        // Checked bare: the suffixed form is a label the wizard shows, so looking that
        // up would miss a leaked DPX-2026-0007-H03 sitting in the registry.
        $bare = $this->stripExtensionSuffix($holdingNo);

        $hit = $conn->table('fileNumber')->where('mlsfNo', $bare)->exists()
            || $conn->table('file_indexings')->where('file_number', $bare)->exists()
            || $conn->table('mls_file_no')->where('full_file_number', $bare)->exists();

        if ($hit) {
            throw new \RuntimeException(
                "Holding number {$holdingNo} was found in the live registry. Holding numbers "
                . 'must never be written to fileNumber / file_indexings / mls_file_no.'
            );
        }
    }
}
