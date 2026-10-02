<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds the temporary "(T)" counterpart of a file number, wherever it is stored.
 *
 * A temporary number `X(T)` and its base `X` are the SAME physical file, but the
 * registry keys them to different columns: `file_indexings` normally holds the base
 * in `file_number` and the temp in `temp_file_no`, while `pra` / `deed_registrations`
 * may have captured either form as the file number itself. Some rows carry ONLY the
 * temp — `file_number` is empty and `temp_file_no` is the row's whole identity.
 *
 * That is why every literal `where('file_number', $n)` lookup misses a temp record
 * silently: no error, just zero rows (see the "(T)" variant bug class, fixed across
 * LegalSearchService and InstrumentRegistrationService). This class is the one place
 * that knows where the "(T)" forms live, so the Decommissioned Files column and the
 * `decommissioning:temp-files` command cannot drift apart.
 *
 * Two lookup modes, same return shape:
 *   resolve()  whereIn on the exact "(T)" forms over DISPLAY_SITES — cheap, for a page
 *   scanAll()  a full LIKE '%(T)%' sweep over every site — exhaustive, for the command
 */
class TemporaryFileNumberResolver
{
    /**
     * Every column that stores a "(T)" number, and whether the column is the row's
     * IDENTITY (the file number the row is filed under) or a temp-marker column
     * hanging off another identity.
     *
     * 'identity' names the column holding the row's own file number, used to tell a
     * row that IS the temp file from a row that merely mentions one. Null means the
     * column is itself the identity.
     *
     * @var array<int, array{table:string, column:string, identity:?string}>
     */
    public const SITES = [
        // The live registry tables that carry decommission flags.
        ['table' => 'file_indexings',    'column' => 'temp_file_no',  'identity' => 'file_number'],
        ['table' => 'file_indexings',    'column' => 'file_number',   'identity' => null],
        ['table' => 'file_indexings',    'column' => 'mls_file_no',   'identity' => 'file_number'],
        ['table' => 'fileNumber',        'column' => 'temp_file_no',  'identity' => 'mlsfNo'],
        ['table' => 'fileNumber',        'column' => 'temp_fileno',   'identity' => 'mlsfNo'],
        ['table' => 'fileNumber',        'column' => 'mlsfNo',        'identity' => null],
        ['table' => 'fileNumber',        'column' => 'kangisFileNo',  'identity' => 'mlsfNo'],
        ['table' => 'customers_staging', 'column' => 'file_number',   'identity' => null],
        ['table' => 'entities_staging',  'column' => 'file_number',   'identity' => null],

        // Record tables — read for display/lineage, never flagged by decommissioning.
        ['table' => 'mls_file_no',        'column' => 'full_file_number', 'identity' => null],
        ['table' => 'pra',                'column' => 'mlsFNo',           'identity' => null],
        ['table' => 'pra',                'column' => 'fileno',           'identity' => null],
        ['table' => 'pra',                'column' => 'temp_fileno',      'identity' => 'mlsFNo'],
        ['table' => 'pra',                'column' => 'resolved_fileno',  'identity' => 'mlsFNo'],
        ['table' => 'deed_registrations', 'column' => 'fileno',           'identity' => null],
    ];

    /**
     * The subset resolve() reads by default, and the only one cheap enough to sit in
     * a page load.
     *
     * None of these columns is indexed, so each one costs a scan of its table: the
     * full SITES list measured at ~6s for a 25-row page (fileNumber.mlsfNo alone 2.1s),
     * which is not a column, it is an outage. These two are the dedicated temp-marker
     * columns — where a file's temporary number is recorded AS the file's temp number —
     * and together they cost ~350ms.
     *
     * What this deliberately gives up: a "(T)" that exists only as a registration in
     * pra / deed_registrations, with no temp marker on the file itself. That is a
     * registration captured under a temporary number, not the file's temp-number
     * attribute, and the sweep (scanAll) still sees it.
     *
     * @var array<int, array{table:string, column:string, identity:?string}>
     */
    public const DISPLAY_SITES = [
        ['table' => 'file_indexings', 'column' => 'temp_file_no', 'identity' => 'file_number'],
        ['table' => 'fileNumber',     'column' => 'temp_file_no', 'identity' => 'mlsfNo'],
    ];

    /**
     * The tables decommissioning actually flags (PlotWorkflowService's table set).
     * A record found outside these is reported but never written to.
     */
    public const FLAGGABLE_TABLES = [
        'fileNumber',
        'file_indexings',
        'customers_staging',
        'entities_staging',
        'kangis_grouping',
    ];

    /**
     * Comparison key for a file number: upper-cased, whitespace removed, trailing
     * "(T)" stripped — so "X", "x", "X (T)" and "X(T)" all reduce to the same key.
     *
     * Whitespace is dropped rather than trimmed because KANGIS numbers legitimately
     * contain spaces ("KNML 1") and are keyed inconsistently between tables.
     */
    public static function baseKey(?string $number): string
    {
        $key = strtoupper(preg_replace('/\s+/', '', (string) $number));

        return (string) preg_replace('/\(T\)$/', '', $key);
    }

    /** Does this number carry the temporary marker? */
    public static function isTemp(?string $number): bool
    {
        return (bool) preg_match('/\(T\)\s*$/i', (string) $number);
    }

    /**
     * The stored shapes a base number's temp form can take. Exact values so the
     * lookup can seek an index instead of scanning for a wildcard.
     *
     * @return string[]
     */
    public static function tempForms(string $number): array
    {
        $trimmed = trim((string) $number);
        $stripped = trim((string) preg_replace('/\s*\(T\)\s*$/i', '', $trimmed));

        if ($stripped === '') {
            return [];
        }

        return array_values(array_unique([$stripped . '(T)', $stripped . ' (T)']));
    }

    /**
     * Temp counterparts for a specific set of file numbers.
     *
     * Reads DISPLAY_SITES by default — the two dedicated temp-marker columns, which is
     * what a page load can afford. Pass SITES for an exhaustive lookup off the request
     * path (see the const's note on what the default gives up).
     *
     * @param  string[] $fileNumbers  base or "(T)" forms, mixed freely
     * @param  array|null $sites      site list to read; defaults to DISPLAY_SITES
     * @return array<string, array{temp_file_no:string, records:array<int, array{table:string,column:string,id:int|string,value:string,identity:?string,shared:bool}>}>
     *         keyed by baseKey()
     */
    public function resolve(array $fileNumbers, ?array $sites = null): array
    {
        $sites = $sites ?? self::DISPLAY_SITES;
        $candidates = [];
        foreach ($fileNumbers as $number) {
            foreach (self::tempForms((string) $number) as $form) {
                $candidates[strtoupper($form)] = $form;
            }
        }

        if (empty($candidates)) {
            return [];
        }

        $candidates = array_values($candidates);
        $found = [];

        foreach ($sites as $site) {
            if (!$this->siteExists($site)) {
                continue;
            }

            // Chunked so a large page cannot blow SQL Server's 2,100-parameter limit.
            foreach (array_chunk($candidates, 500) as $chunk) {
                $rows = $this->select($site, function ($query) use ($site, $chunk) {
                    $query->whereIn($site['column'], $chunk);
                });

                $this->collect($found, $site, $rows);
            }
        }

        return $found;
    }

    /**
     * Every "(T)" number stored anywhere, indexed by base. Used by the sweep, where
     * the set of decommissioned numbers is too large to pass as bind parameters and
     * an unexpected stored shape (odd spacing, a suffix) must not be missed.
     *
     * @return array<string, array{temp_file_no:string, records:array}>
     */
    public function scanAll(): array
    {
        $found = [];

        foreach (self::SITES as $site) {
            if (!$this->siteExists($site)) {
                continue;
            }

            $rows = $this->select($site, function ($query) use ($site) {
                $query->where($site['column'], 'like', '%(T)%');
            });

            $this->collect($found, $site, $rows);
        }

        return $found;
    }

    /** Is this table/column actually present on this database? */
    private function siteExists(array $site): bool
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable($site['table']) || !$schema->hasColumn($site['table'], $site['column'])) {
            return false;
        }

        return $site['identity'] === null || $schema->hasColumn($site['table'], $site['identity']);
    }

    /**
     * Read a site's matching rows, pulling the identity column alongside the value so
     * the caller can tell a temp-only row from one shared with a live base file.
     */
    private function select(array $site, callable $filter)
    {
        $columns = ['id as _pk', $site['column'] . ' as _val'];
        if ($site['identity'] !== null) {
            $columns[] = $site['identity'] . ' as _identity';
        }

        $query = DB::connection('sqlsrv')->table($site['table'])->select($columns);
        $filter($query);

        return $query->orderBy('id')->get();
    }

    /**
     * Fold a site's rows into the index.
     *
     * A row is "shared" when the temp number hangs off a row already filed under its
     * BASE number — the same row is both the live file and its temp marker, so acting
     * on it acts on the live file. A row whose identity is empty, or is the "(T)"
     * itself, belongs to the temp file alone.
     */
    private function collect(array &$found, array $site, $rows): void
    {
        foreach ($rows as $row) {
            $value = trim((string) $row->_val);
            if ($value === '' || !self::isTemp($value)) {
                continue;
            }

            $identity = $site['identity'] === null ? $value : trim((string) ($row->_identity ?? ''));
            $key = self::baseKey($value);

            if (!isset($found[$key])) {
                $found[$key] = ['temp_file_no' => $value, 'records' => []];
            }

            $found[$key]['records'][] = [
                'table'    => $site['table'],
                'column'   => $site['column'],
                'id'       => $row->_pk,
                'value'    => $value,
                'identity' => $identity === '' ? null : $identity,
                'shared'   => $identity !== '' && !self::isTemp($identity),
            ];
        }
    }
}
