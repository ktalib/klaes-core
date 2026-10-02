<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinalizeDistrictStaging extends Command
{
    protected $signature = 'district:finalize-staging
        {--dry-run : Show what would be done without writing anything}';

    protected $description = 'Clean up district_extraction_staging and populate district_staging ready for promotion into dbo.districts.';

    private const NOISE_SUFFIXES = [
        '% LAYOUT', '% QUARTERS', '% WARD', '% SCHEME',
        '% VILLAGE', '% HOUSING%', '% PHASE%',
    ];

    private const NOISE_EXACT = [
        'PROPERTY DESCRIPTION', 'PIECE OF LAND', 'PLOT OF LAND',
        'RESIDENTIAL PROPERTY', 'COMMERCIAL PROPERTY', 'FARM LAND',
        'N/A', 'NA', 'NIL', 'NONE', 'NOT APPLICABLE', 'NO DISTRICT',
        '(EMPTY-PLOT-ONLY)',
    ];

    // canonical → [aliases to reject]
    private const DUPLICATE_ALIASES = [
        'DORAYI BABBA'  => ['DORAYE BABBA', 'DORAYE BABA', 'DORAYI BABA'],
        'SHARADA'       => ['SHARRADAN', 'SHARRARD', 'SHARRADAN GRA'],
        'KOFAR MAZUGAL' => ['KOFAR MAZUGALI'],
        'GYADI GYADI'   => ['GYADI-GYADI'],
        'TUDUN YOLA'    => ['TUDAN YOLA'],
        'HOTORO'        => ['HOTARO', 'HUTORO'],
        'NAIBAWA'       => ['NAIBAWAR'],
        'BAMBARO'       => ['BANBARO'],
        'YANKABA'       => ['YAN KABA'],
        'PANSHEKARA'    => ['PANSEKARA', 'PANSHAKARA'],
        'BADAWA'        => ['BADAWA NASSARAWA', 'BADAWA NASSARAWA GRA'],
    ];

    public function handle(): int
    {
        $conn    = DB::connection('sqlsrv');
        $dryRun  = (bool) $this->option('dry-run');

        if (!Schema::connection('sqlsrv')->hasTable('district_extraction_staging')) {
            $this->error('district_extraction_staging does not exist. Run extract_district_candidates.sql first.');
            return self::FAILURE;
        }

        $this->step1Cleanup($conn, $dryRun);
        $this->step2CreateTable($conn, $dryRun);
        $this->step3Populate($conn, $dryRun);
        $this->step4Summary($conn);

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Step 1 — cleanup district_extraction_staging
    // -------------------------------------------------------------------------

    private function step1Cleanup($conn, bool $dryRun): void
    {
        $this->info('=== Step 1: Cleanup district_extraction_staging ===');

        // 1a) Strip stray punctuation and double-spaces
        $punctRows = $conn->table('district_extraction_staging')
            ->where(function ($q) {
                $q->where('normalized', 'like', '%.%')
                  ->orWhere('normalized', 'like', '%,%')
                  ->orWhere('normalized', 'like', '%  %');
            })
            ->count();

        $this->line("  [1a] Punctuation/double-space rows to fix: {$punctRows}");

        if (!$dryRun && $punctRows > 0) {
            $conn->table('district_extraction_staging')
                ->where(function ($q) {
                    $q->where('normalized', 'like', '%.%')
                      ->orWhere('normalized', 'like', '%,%')
                      ->orWhere('normalized', 'like', '%  %');
                })
                ->get(['id', 'normalized'])
                ->each(function ($row) use ($conn) {
                    $clean = trim(str_replace(['  ', '.', ','], [' ', '', ''], $row->normalized));
                    $clean = trim(preg_replace('/\s+/', ' ', $clean));
                    if ($clean !== $row->normalized) {
                        $conn->table('district_extraction_staging')
                            ->where('id', $row->id)
                            ->update(['normalized' => $clean]);
                    }
                });
        }

        // 1b) Noise suffixes → INVALID_STREET / rejected
        $noiseSuffixQuery = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->where('review_status', 'pending')
            ->where(function ($q) {
                foreach (self::NOISE_SUFFIXES as $suffix) {
                    $q->orWhere('normalized', 'like', $suffix);
                }
            });

        $noiseSuffixCount = (clone $noiseSuffixQuery)->count();
        $this->line("  [1b] Noise-suffix candidates to reject: {$noiseSuffixCount}");

        if (!$dryRun && $noiseSuffixCount > 0) {
            (clone $noiseSuffixQuery)->update([
                'classification' => 'INVALID_STREET',
                'review_status'  => 'rejected',
            ]);
        }

        // 1c) Noise exact values → INVALID_GENERIC / rejected
        $noiseExactQuery = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->where('review_status', 'pending')
            ->whereIn('normalized', self::NOISE_EXACT);

        $noiseExactCount = (clone $noiseExactQuery)->count();
        $this->line("  [1c] Noise-exact candidates to reject: {$noiseExactCount}");

        if (!$dryRun && $noiseExactCount > 0) {
            (clone $noiseExactQuery)->update([
                'classification' => 'INVALID_GENERIC',
                'review_status'  => 'rejected',
            ]);
        }

        // 1d) Duplicate aliases → DUPLICATE_ALIAS / rejected
        $allAliases = collect(self::DUPLICATE_ALIASES)->flatMap(fn ($aliases) => $aliases)->values()->all();
        $aliasQuery = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->whereIn('normalized', $allAliases);

        $aliasCount = (clone $aliasQuery)->count();
        $this->line("  [1d] Duplicate alias candidates to reject: {$aliasCount}");

        if (!$dryRun && $aliasCount > 0) {
            (clone $aliasQuery)->update([
                'classification' => 'DUPLICATE_ALIAS',
                'review_status'  => 'rejected',
            ]);
        }

        // 1e) L/O (Layout) suffix normalisation
        //     Strip " L/O", " L/Y", " QTRS", " S/TOWN", " H/ESTATE" from the end.
        //     After stripping, if the base name already exists as a CANDIDATE or
        //     DISTRICT_HIT, mark the suffixed row as DUPLICATE_ALIAS/rejected.
        //     Otherwise update its normalized value so it de-dupes in step 3.
        $layoutSuffixes = [' L/O', ' L/Y', ' QTRS', ' S/TOWN', ' H/ESTATE', ' ESTATE'];
        $layoutRows = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->where('review_status', 'pending')
            ->where(function ($q) use ($layoutSuffixes) {
                foreach ($layoutSuffixes as $s) {
                    $q->orWhere('normalized', 'like', '%' . $s);
                }
            })
            ->get(['id', 'normalized']);

        $this->line("  [1e] L/O-suffix rows to normalize: {$layoutRows->count()}");

        // Build a lookup of all current normalized values for duplicate detection
        $allNormalized = $conn->table('district_extraction_staging')
            ->whereIn('classification', ['CANDIDATE', 'DISTRICT_HIT'])
            ->pluck('normalized')
            ->flip(); // use as set

        if (!$dryRun) {
            foreach ($layoutRows as $row) {
                $base = $row->normalized;
                foreach ($layoutSuffixes as $s) {
                    if (str_ends_with($base, $s)) {
                        $base = trim(substr($base, 0, -strlen($s)));
                        break;
                    }
                }
                if ($base === $row->normalized || strlen($base) < 3) {
                    continue; // nothing stripped or too short
                }
                if (isset($allNormalized[$base])) {
                    // base already exists → this row is a duplicate
                    $conn->table('district_extraction_staging')->where('id', $row->id)->update([
                        'classification' => 'DUPLICATE_ALIAS',
                        'review_status'  => 'rejected',
                    ]);
                } else {
                    // unique base — keep it but with stripped name
                    $conn->table('district_extraction_staging')->where('id', $row->id)->update([
                        'normalized' => $base,
                    ]);
                    $allNormalized[$base] = true; // register so later rows don't create a dup
                }
            }
        }

        // 1f) Comprehensive regex rejection pass
        $pendingCandidates = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->where('review_status', 'pending')
            ->get(['id', 'normalized']);

        $regexRejectIds = $pendingCandidates->filter(function ($row) {
            $n = trim($row->normalized);
            return
                // ── Plot / house number patterns ─────────────────────────────
                // Date-like: 19-11-1987, 19/11/1987, 1987-11-19
                preg_match('/^\d{1,4}[-\/]\d{1,2}[-\/]\d{2,4}$/', $n) ||
                // Plot letter-dash-number: C-5, B-12, A-113
                preg_match('/^[A-Z]-\d/', $n) ||
                // Purely numeric with trailing letter(s): 3612B, 13E, 105A
                preg_match('/^\d+[A-Z]{1,2}$/', $n) ||
                // Short letter+digit code (no space): A12, C5
                preg_match('/^[A-Z]\d+$/', $n) ||
                // 1-3 digit + single letter suffix: 19A, 3B
                preg_match('/^\d{1,3}[A-Z]$/', $n) ||
                // Pure numeric
                preg_match('/^\d+$/', $n) ||
                // Zero-padded ref: 00327
                preg_match('/^0\d{3,}$/', $n) ||
                // TP/ reference codes: TP/KN/UC/T8/5A
                preg_match('/^TP\//', $n) ||
                // BLOCK followed by number
                preg_match('/^BLOCK\s+\d/i', $n) ||

                // ── Number ranges / combined plot refs ───────────────────────
                // 17 & 18, 3 & 4, 258 & 259
                preg_match('/\d+\s*&\s*\d/', $n) ||
                // Starts with just & (fragment token)
                preg_match('/^&/', $n) ||
                // "AND digit": AND 2D, 53A AND 53B
                preg_match('/^\d+\s+AND\s+\d/i', $n) ||

                // ── House/plot "NO" prefix ────────────────────────────────────
                // NO 78, NO 15, NO. 78, NO10C, NO15, NO3, N0 39 (zero), N044...
                preg_match('/^N[O0]\.?\s*\d/i', $n) ||

                // ── Address-only prefixes (not district names) ───────────────
                // ALONG AHMADU BELLO WAY, ALONG AIRPORT RD, etc.
                preg_match('/^ALONG\s/i', $n) ||
                // NEAR DAULA HOTEL, NEAR NAF CLINIC, etc.
                preg_match('/^NEAR\s/i', $n) ||
                // OFF AIRPORT, OFF IBB WAY, etc.
                preg_match('/^OFF\s/i', $n) ||
                // OPP DANLADI NASIDI
                preg_match('/^OPP\s/i', $n) ||
                // BY NITEL
                preg_match('/^BY\s/i', $n) ||

                // ── Road / bypass abbreviations ──────────────────────────────
                // W/BYEPASS, W/BYPASS, W/BYE-PASS, W/BYE PASS, W/B, WBP
                preg_match('/^W\/B(YE?[-\s]?PASS|P?$|YP$)/i', $n) ||
                preg_match('/^WBP$/i', $n) ||
                // WESTERN BYE-PASS, WESTERN BYEPASS (pure road name)
                preg_match('/^WESTERN\s+B(YE[-\s]?)?PASS/i', $n) ||

                // ── Terminus / bus-stop references ──────────────────────────
                // S/TERMINAL, S/TERMINALS, N/TERMINUS, N/TERMINIUS, N/TERMINL
                preg_match('/^[SN]\/(TERMINAL|TERMINUS|TERMINIUS|TERMINL)S?$/i', $n) ||
                // NEAR/OFF followed by specific landmarks
                preg_match('/^(NORTH|SOUTH)\s+TERMINUS/i', $n) ||

                // ── Standalone landmark abbreviations ────────────────────────
                // S/GARI, S/G  (Sabon Gari alone is a landmark ref here)
                preg_match('/^S\/G(ARI)?$/i', $n) ||
                // S/N (serial number)
                preg_match('/^S\/N$/i', $n) ||
                // GRA alone (no context)
                preg_match('/^GRA$/i', $n) ||
                // ROAD alone, WAY alone, OFF alone
                preg_match('/^(ROAD|WAY|OFF)$/i', $n) ||
                // NEAR alone
                preg_match('/^NEAR$/i', $n) ||
                // OLD alone
                preg_match('/^OLD$/i', $n) ||

                // ── Ordinal street names ─────────────────────────────────────
                // 9TH ST, 10TH LINK, 12TH AV, 14TH LINK, 15TH AV
                preg_match('/^\d+(ST|ND|RD|TH)\s+(ST|AV|LINK|RD|WAY|CLOSE)\b/i', $n) ||

                // ── Distance markers ─────────────────────────────────────────
                // MILE 9, KM-8
                preg_match('/^MILE\s+\d/i', $n) ||
                preg_match('/^KM[-\/]\d/i', $n);
        })->pluck('id')->all();

        $regexRejectCount = count($regexRejectIds);
        $this->line("  [1f] Address/code/noise candidates to reject: {$regexRejectCount}");

        if (!$dryRun && $regexRejectCount > 0) {
            foreach (array_chunk($regexRejectIds, 500) as $chunk) {
                $conn->table('district_extraction_staging')
                    ->whereIn('id', $chunk)
                    ->update([
                        'classification' => 'INVALID_GENERIC',
                        'review_status'  => 'rejected',
                    ]);
            }
        }

        $totalRejected = $conn->table('district_extraction_staging')
            ->where('review_status', 'rejected')->count();
        $stillPending = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->where('review_status', 'pending')
            ->count();

        $this->info("  Cleanup done — total rejected: {$totalRejected}, still pending review: {$stillPending}");
    }

    // -------------------------------------------------------------------------
    // Step 2 — create district_staging
    // -------------------------------------------------------------------------

    private function step2CreateTable($conn, bool $dryRun): void
    {
        $this->info('=== Step 2: Create district_staging ===');

        if ($dryRun) {
            $this->line('  [dry-run] Would drop + recreate district_staging.');
            return;
        }

        // Drop and recreate
        $conn->unprepared('
            IF OBJECT_ID(\'dbo.district_staging\', \'U\') IS NOT NULL
                DROP TABLE [dbo].[district_staging]
        ');

        $conn->unprepared('
            CREATE TABLE [dbo].[district_staging] (
                [id]             BIGINT IDENTITY(1,1) NOT NULL,
                [name]           NVARCHAR(255) NOT NULL,
                [slug]           NVARCHAR(255) NOT NULL,
                [occurrence]     INT           NOT NULL DEFAULT 0,
                [already_exists] BIT           NOT NULL DEFAULT 0,
                [insert_status]  NVARCHAR(20)  NOT NULL DEFAULT \'pending\',
                [source_id]      BIGINT        NULL,
                [source_raw]     NVARCHAR(255) NULL,
                [created_at]     DATETIME2(0)  NOT NULL DEFAULT SYSUTCDATETIME(),
                CONSTRAINT PK_district_staging PRIMARY KEY CLUSTERED ([id]),
                CONSTRAINT UQ_district_staging_slug UNIQUE ([slug])
            )
        ');

        $conn->unprepared('CREATE NONCLUSTERED INDEX IX_ds_name           ON [dbo].[district_staging]([name])');
        $conn->unprepared('CREATE NONCLUSTERED INDEX IX_ds_insert_status  ON [dbo].[district_staging]([insert_status])');
        $conn->unprepared('CREATE NONCLUSTERED INDEX IX_ds_already_exists ON [dbo].[district_staging]([already_exists])');

        $this->info('  district_staging created.');
    }

    // -------------------------------------------------------------------------
    // Step 3 — populate district_staging
    // -------------------------------------------------------------------------

    private function step3Populate($conn, bool $dryRun): void
    {
        $this->info('=== Step 3: Populate district_staging ===');

        // Fetch all CANDIDATE rows that are approved or pending
        $rows = $conn->table('district_extraction_staging')
            ->where('classification', 'CANDIDATE')
            ->whereIn('review_status', ['approved', 'pending'])
            ->where('normalized', '!=', '(EMPTY-PLOT-ONLY)')
            ->whereRaw('LEN(normalized) >= 3')
            ->orderByDesc('occurrence')
            ->get(['id', 'raw_value', 'normalized', 'occurrence']);

        // Deduplicate: for each normalized name keep highest occurrence (first in desc order)
        // Also deduplicate by slug — different names can produce the same slug
        // e.g. "RIGA-FADA" and "RIGA FADA" both → "riga-fada"
        $seen     = [];   // keyed by upper name
        $seenSlug = [];   // keyed by slug
        $payload  = [];

        // Existing district names (upper) for already_exists check
        $existingDistricts = $conn->table('districts')
            ->whereNotNull('name')
            ->pluck('name')
            ->map(fn ($n) => strtoupper(trim($n)))
            ->flip(); // use as a lookup set

        foreach ($rows as $row) {
            $name = strtoupper(trim($row->normalized));
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            // Strip characters that are not alphanumeric or space before slugifying
            // so "RIGA-FADA" and "RIGA FADA" collapse to the same canonical slug
            $slugBase      = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
            $slugBase      = trim(preg_replace('/\s+/', '-', $slugBase), '-');
            $slug          = $slugBase;

            // If slug already taken (collision), suffix with occurrence count to keep it unique
            if (isset($seenSlug[$slug])) {
                $slug = $slugBase . '-' . $row->occurrence;
                // Still colliding? Skip — it's an alias of a higher-occurrence entry
                if (isset($seenSlug[$slug])) {
                    continue;
                }
            }
            $seenSlug[$slug] = true;

            $alreadyExists = isset($existingDistricts[$name]) ? 1 : 0;

            $payload[] = [
                'name'           => $name,
                'slug'           => $slug,   // already deduplicated above
                'occurrence'     => $row->occurrence,
                'already_exists' => $alreadyExists,
                'insert_status'  => 'pending',
                'source_id'      => $row->id,
                'source_raw'     => $row->raw_value,
                'created_at'     => now(),
            ];
        }

        $total   = count($payload);
        $isNew   = count(array_filter($payload, fn ($r) => $r['already_exists'] === 0));
        $exists  = $total - $isNew;

        if ($dryRun) {
            $this->line("  [dry-run] Would insert {$total} rows into district_staging ({$isNew} new, {$exists} already in districts).");
            if ($total > 0) {
                $preview = array_slice($payload, 0, 10);
                $this->table(['name', 'slug', 'occurrence', 'already_exists'], array_map(
                    fn ($r) => [$r['name'], $r['slug'], $r['occurrence'], $r['already_exists'] ? 'yes' : 'no'],
                    $preview
                ));
            }
            return;
        }

        // Insert in chunks to avoid parameter limits
        foreach (array_chunk($payload, 100) as $chunk) {
            $conn->table('district_staging')->insert($chunk);
        }

        $this->info("  Populated {$total} rows → {$isNew} new, {$exists} already in dbo.districts.");
    }

    // -------------------------------------------------------------------------
    // Step 4 — summary
    // -------------------------------------------------------------------------

    private function step4Summary($conn): void
    {
        $this->info('=== Step 4: Summary ===');

        if (!Schema::connection('sqlsrv')->hasTable('district_staging')) {
            $this->warn('  district_staging not found (dry-run?).');
            return;
        }

        $summary = $conn->table('district_staging')
            ->selectRaw('already_exists, insert_status, COUNT(*) as cnt, SUM(occurrence) as total_occ')
            ->groupBy('already_exists', 'insert_status')
            ->orderBy('already_exists')
            ->orderBy('insert_status')
            ->get();

        $this->table(
            ['already_exists', 'insert_status', 'count', 'total_occurrences'],
            $summary->map(fn ($r) => [
                $r->already_exists ? 'yes' : 'no',
                $r->insert_status,
                $r->cnt,
                $r->total_occ,
            ])->toArray()
        );

        $this->info('');
        $this->info('Top 20 new candidates by frequency:');

        $top = $conn->table('district_staging')
            ->where('already_exists', 0)
            ->where('insert_status', 'pending')
            ->orderByDesc('occurrence')
            ->limit(20)
            ->get(['name', 'slug', 'occurrence', 'source_raw']);

        $this->table(
            ['name', 'slug', 'occurrence', 'source_raw'],
            $top->map(fn ($r) => [$r->name, $r->slug, $r->occurrence, $r->source_raw])->toArray()
        );

        $this->info('');
        $this->comment('To promote all pending-new into dbo.districts, run:');
        $this->comment('  php artisan district:promote-staging');
    }
}
