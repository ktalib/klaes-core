<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill Certificate of Occupancy instruments that were captured via
 * /instruments/create BEFORE the CofO_staging mirror was added
 * (InstrumentCaptureService::syncCofoStaging) into the CofO_staging table.
 *
 * Idempotent: rows already mirrored (matched on registration particulars) are
 * skipped, so it is safe to re-run. Mirrors the same field mapping used by the
 * live capture path.
 */
class BackfillCofoStaging extends Command
{
    protected $signature = 'cofo:backfill-staging
        {--chunk=200 : Rows per chunk}
        {--limit=0 : Stop after N inserts (0 = no limit)}
        {--dry-run : Report what would be inserted without writing}';

    protected $description = 'Backfill already-captured Certificate of Occupancy instruments (instrument_capture) into CofO_staging. Idempotent.';

    private const TITLE_TYPE_MAP = [
        'certificate of occupancy' => 'C of O',
        'st certificate of occupancy' => 'ST C of O',
        'sltr certificate of occupancy' => 'SLTR C of O',
    ];

    public function handle(): int
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable('CofO_staging')) {
            $this->error('CofO_staging table does not exist.');
            return self::FAILURE;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $cofoColumns = Schema::connection('sqlsrv')->getColumnListing('CofO_staging');

        $inserted = 0;
        $skipped = 0;
        $scanned = 0;
        $stop = false;

        $conn->table('instrument_capture')
            ->whereRaw("instrument_type LIKE '%Certificate of Occupancy%'")
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use ($conn, $cofoColumns, $dryRun, $limit, &$inserted, &$skipped, &$scanned, &$stop) {
                foreach ($rows as $row) {
                    if ($stop) {
                        return false;
                    }
                    $scanned++;

                    if ($this->alreadyMirrored($conn, $row)) {
                        $skipped++;
                        continue;
                    }

                    $payload = $this->mapRow($row, $cofoColumns);

                    if ($dryRun) {
                        $inserted++;
                        $this->line(sprintf(
                            '  [dry-run] would insert IC#%d  reg=%s  file=%s  prop_id=%s',
                            $row->id,
                            $row->registration_number ?: '(none)',
                            $payload['fileno'] ?? '(none)',
                            $row->prop_id ?: '(none)'
                        ));
                    } else {
                        $conn->table('CofO_staging')->insert($payload);
                        $inserted++;
                    }

                    if ($limit > 0 && $inserted >= $limit) {
                        $stop = true;
                        return false;
                    }
                }

                return true;
            });

        $this->info(sprintf(
            '%s — scanned: %d, %s: %d, skipped (already mirrored): %d',
            $dryRun ? 'Dry run complete' : 'Backfill complete',
            $scanned,
            $dryRun ? 'would insert' : 'inserted',
            $inserted,
            $skipped
        ));

        return self::SUCCESS;
    }

    /**
     * Skip rows already present in CofO_staging. Prefer the registration number
     * (unique per registration); fall back to prop_id + particulars when a row
     * has no registration number.
     */
    private function alreadyMirrored($conn, $row): bool
    {
        $base = $conn->table('CofO_staging')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });

        $regNo = trim((string) ($row->registration_number ?? ''));
        if ($regNo !== '') {
            return (clone $base)->where('regNo', $regNo)->exists();
        }

        return (clone $base)
            ->where('transaction_type', $row->instrument_type)
            ->when($row->prop_id, fn ($q) => $q->where('prop_id', $row->prop_id))
            ->where('serialNo', $row->serial_no)
            ->where('pageNo', $row->page_no)
            ->where('volumeNo', $row->volume_no)
            ->exists();
    }

    /**
     * Map an instrument_capture CofO row to CofO_staging columns, filtered to
     * columns that actually exist. Mirrors InstrumentCaptureService::syncCofoStaging.
     */
    private function mapRow($row, array $cofoColumns): array
    {
        $instrumentType = (string) $row->instrument_type;
        $titleType = self::TITLE_TYPE_MAP[strtolower($instrumentType)] ?? 'C of O';

        $primaryFileno = $row->mlsFNo ?: $row->kangisFileNo ?: $row->NewKANGISFileno ?: $row->temp_fileno ?: null;

        // duration may be a bare number ("99") or include a unit ("99 Years").
        $period = null;
        if (isset($row->duration) && is_numeric(trim((string) $row->duration))) {
            $period = (int) $row->duration;
        }

        // instrument_capture has no reg_time; use the capture time so the
        // mirrored deeds_time / transaction_time are never blank.
        $regTime = $row->created_at ? date('H:i:s', strtotime((string) $row->created_at)) : '00:00:00';

        $all = [
            'mlsFNo' => $row->mlsFNo ?: null,
            'kangisFileNo' => $row->kangisFileNo ?: null,
            'NewKANGISFileno' => $row->NewKANGISFileno ?: null,
            'temp_fileno' => $row->temp_fileno ?: null,
            'fileno' => $primaryFileno,
            'prop_id' => $row->prop_id ?: null,
            'title_type' => $titleType,
            'transaction_type' => $instrumentType,
            'instrument_type' => $instrumentType,
            'transaction_date' => $row->reg_date ?: null,
            'transaction_time' => $regTime,
            'deeds_date' => $row->reg_date ?: null,
            'deeds_time' => $regTime,
            'serialNo' => $row->serial_no,
            'pageNo' => $row->page_no,
            'volumeNo' => $row->volume_no,
            'regNo' => $row->registration_number ?: null,
            'cofo_type' => $row->cofo_type ?: null,
            'cofo_date' => $row->cofo_date ?: null,
            'period' => $period,

            // Parties (CofO is Grantor -> Grantee)
            'Grantor' => $row->party_1_name ?: null,
            'Grantee' => $row->party_2_name ?: null,
            'party_1' => $row->party_1_name ?: null,
            'party_2' => $row->party_2_name ?: null,
            'party_3' => $row->party_3_name ?: null,
            'party_4' => $row->party_4_name ?: null,

            // Property
            'property_description' => $row->property_description ?: null,
            'location' => $row->property_location ?: null,
            'plot_no' => $row->plot_number ?: null,
            'lgsaOrCity' => $row->lga ?: null,
            'districtName' => $row->district ?: null,
            'tp_no' => $row->tp_no ?: null,
            'lpkn_no' => $row->lpkn_no ?: null,
            'plot_size' => $row->plot_size ?: null,
            'land_use' => $row->land_use ?: null,

            'source' => 'instrument_capture',
            'captured_by' => $row->created_by ?? null,
            'created_by' => $row->created_by ?? null,
            'updated_by' => $row->updated_by ?? null,
            // Preserve the original capture timestamp so timeline ordering matches.
            'created_at' => $row->created_at ?? now(),
            'updated_at' => now(),
            'deleted_at' => null,
            'is_deleted' => 0,
        ];

        $filtered = [];
        foreach ($all as $key => $value) {
            if (in_array($key, $cofoColumns, true)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
