<?php

namespace App\Console\Commands;

use App\Support\DepartmentNormalizer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates demo file_tracker rows — a full movement history per file — from a JSON
 * list of file numbers or straight off file_indexings, for populating the File Log
 * Table / commissioner dashboard on a fresh or thin environment.
 *
 * The movement log mirrors how the application itself records a file's life: the file
 * lives in a registry, is logged OUT to an office (entry status `active`), and is logged
 * back in when it returns (a `status_updated` entry carrying new_status `Completed`, as
 * written by FileTrackerController::updateStatus). Generating a run of bare `completed`
 * entries instead made every row render as "Log-in ... Archive" with no log-out at all.
 *
 * Every row it writes carries `seed_tag`, so the whole batch can be removed again with
 * --fresh (or a one-line DELETE) without touching a single real registry movement.
 *
 *   php artisan file-tracker:seed-fake-logs --land=500 --kangis=500 --dry-run
 *   php artisan file-tracker:seed-fake-logs --land=500 --kangis=500 --fresh --force
 */
class SeedFakeFileTrackerLogs extends Command
{
    protected $signature = 'file-tracker:seed-fake-logs
        {--land=0 : How many land file numbers to draw from file_indexings instead of the JSON}
        {--kangis=0 : How many KANGIS file numbers to draw from file_indexings instead of the JSON}
        {--file= : JSON source, used when --land/--kangis are not given (default docs/data/json/create_fake_logs.json)}
        {--tag=FAKE-LOGS : Written to file_tracker.seed_tag so the batch stays identifiable}
        {--start= : Earliest date in the window (default 1 January of the --end year)}
        {--end= : Latest date in the window (default now)}
        {--min-logs=11 : Fewest movement entries per file}
        {--max-logs=20 : Most movement entries per file}
        {--returned-pct=30 : Percentage of files that have come back to the registry (tracker status Completed)}
        {--fresh : Delete rows already carrying this tag before inserting}
        {--dry-run : Show what would be written without touching the database}
        {--force : Skip the confirmation prompt (for non-interactive deploys)}';

    protected $description = 'Create demo file_tracker rows with movement logs from a JSON file list or file_indexings';

    /**
     * Offices a file is never routed to here. Codes cover the management block; the
     * name rule below then drops every director-level office (but keeps their deputies
     * and assistants, which are the offices that actually handle files).
     */
    private const EXCLUDED_OFFICE_CODES = ['PS', 'SPS', 'HC', 'SC1', 'SC2', 'PAC', 'DG', 'DGIS', 'GIS', 'SUR-G', 'ICT'];

    private const SENIOR_OFFICE_PATTERN = '/(director|permanent secretary|commissioner|surveyor general|\bdg\b|\bps\b)/i';

    private const DEPUTY_PREFIX_PATTERN = '/^(deputy|assistant)\b/i';

    private const CONNECTION = 'sqlsrv';

    /** @var array<int, string> Offices dropped by the name rule, for the run summary. */
    private array $droppedByRule = [];

    public function handle(): int
    {
        $tag = trim((string) $this->option('tag'));
        if ($tag === '') {
            $this->error('--tag cannot be empty; it is the only handle on these rows.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $landCount = max(0, (int) $this->option('land'));
        $kangisCount = max(0, (int) $this->option('kangis'));

        if ($landCount || $kangisCount) {
            $source = 'file_indexings';
            $files = array_merge(
                $landCount ? $this->fileNumbersFromIndexings('land', $landCount) : [],
                $kangisCount ? $this->fileNumbersFromIndexings('kangis', $kangisCount) : []
            );

            foreach ([['land', $landCount], ['kangis', $kangisCount]] as [$module, $wanted]) {
                $got = count(array_filter($files, fn ($f) => $f['module'] === $module));
                if ($wanted && $got < $wanted) {
                    $this->warn("Only {$got} untracked {$module} file numbers available, {$wanted} requested.");
                }
            }
        } else {
            $source = $this->option('file') ?: base_path('docs/data/json/create_fake_logs.json');
            if (! file_exists($source)) {
                $this->error("JSON source not found at {$source}");
                return self::FAILURE;
            }
            $files = $this->readFiles($source);
        }

        if (empty($files)) {
            $this->error('No file numbers to work with.');
            return self::FAILURE;
        }

        $end = $this->option('end') ? Carbon::parse($this->option('end')) : Carbon::now();
        $start = $this->option('start')
            ? Carbon::parse($this->option('start'))
            : $end->copy()->startOfYear();

        if ($start->greaterThanOrEqualTo($end)) {
            $this->error('--start must fall before --end.');
            return self::FAILURE;
        }

        $minLogs = max(2, (int) $this->option('min-logs'));
        $maxLogs = max($minLogs, (int) $this->option('max-logs'));
        $returnedPct = min(100, max(0, (int) $this->option('returned-pct')));

        $offices = $this->officePool();
        if (count($offices) < 2) {
            $this->error('Fewer than two eligible offices after exclusions — nothing to route between.');
            return self::FAILURE;
        }

        $names = $this->namePool();
        if (empty($names)) {
            $this->error('Could not generate an officer name pool.');
            return self::FAILURE;
        }

        $purposes = $this->purposePool();
        if (empty($purposes)) {
            $this->error('No active request_purposes — run: php artisan db:seed --class=RequestPurposeSeeder');
            return self::FAILURE;
        }

        $existing = DB::connection(self::CONNECTION)->table('file_tracker')->where('seed_tag', $tag)->count();

        $this->info('Source        : ' . $source);
        $this->info('Files         : ' . count($files));
        $this->info('Window        : ' . $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d'));
        $this->info('Logs per file : ' . $minLogs . '-' . $maxLogs . ' (' . $returnedPct . '% returned to registry)');
        $this->info('Office pool   : ' . count($offices) . ' — excluded ' . count(self::EXCLUDED_OFFICE_CODES) . ' management codes + ' . count($this->droppedByRule) . ' director-level offices');
        $this->line('                ' . implode(', ', $this->droppedByRule));
        $this->info('Officer pool  : ' . count($names) . ' generated names (no real users)');
        $this->info('Purpose pool  : ' . count($purposes));
        $this->info('seed_tag      : ' . $tag . ($existing ? " ({$existing} row(s) already tagged)" : ''));
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        } elseif (! $this->option('force')) {
            $action = $this->option('fresh') && $existing
                ? "Delete {$existing} tagged row(s) and insert " . count($files) . ' fresh one(s)?'
                : 'Insert ' . count($files) . ' tagged file_tracker row(s)?';

            if (! $this->confirm($action, false)) {
                $this->info('Aborted — nothing was written.');
                return self::SUCCESS;
            }
        }

        if ($this->option('fresh') && $existing) {
            if (! $dryRun) {
                DB::connection(self::CONNECTION)->table('file_tracker')->where('seed_tag', $tag)->delete();
            }
            $this->warn(($dryRun ? 'Would delete ' : 'Deleted ') . $existing . " row(s) tagged {$tag}.");
            $existing = 0;
        }

        // Without --fresh, a second run would otherwise stack a duplicate history onto
        // every file. Tagged rows are ours to compare against; untagged ones are real
        // and must be left alone. Fetched once — at 500 files per module, one existence
        // check per file was 1,000 round trips.
        $alreadyTagged = $existing > 0
            ? DB::connection(self::CONNECTION)->table('file_tracker')
                ->where('seed_tag', $tag)->pluck('file_number')->flip()
            : collect();

        $preview = [];
        $inserted = 0;
        $skipped = 0;
        $logTotal = 0;
        $returned = 0;
        $batch = [];

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $file) {
            if ($alreadyTagged->has($file['file_number'])) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $record = $this->buildRecord($file, $offices, $names, $purposes, $start, $end, $minLogs, $maxLogs, $returnedPct, $tag);
            $batch[] = $record['row'];
            $inserted++;
            $logTotal += $record['logs'];
            $returned += $record['returned'] ? 1 : 0;

            if (count($preview) < 15) {
                $preview[] = [
                    $file['file_number'],
                    $file['module'],
                    $record['logs'],
                    $record['row']['status'],
                    $record['row']['request_purpose_name'],
                    $record['row']['timeline_days'],
                    Carbon::parse($record['row']['created_at'])->format('Y-m-d'),
                    $record['row']['current_office_name'],
                ];
            }

            // SQL Server caps a statement at 2,100 parameters; ~40 columns a row.
            if (! $dryRun && count($batch) >= 40) {
                DB::connection(self::CONNECTION)->table('file_tracker')->insert($batch);
                $batch = [];
            }

            $bar->advance();
        }

        if (! $dryRun && ! empty($batch)) {
            DB::connection(self::CONNECTION)->table('file_tracker')->insert($batch);
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['File number', 'Module', 'Logs', 'Status', 'Purpose', 'Timeline', 'Logged out', 'Currently at'],
            $preview
        );

        if ($inserted > count($preview)) {
            $this->line('… and ' . ($inserted - count($preview)) . ' more.');
        }

        $verb = $dryRun ? 'Would insert' : 'Inserted';
        $this->info("{$verb} {$inserted} file_tracker row(s) tagged {$tag}: {$logTotal} movement entries, {$returned} returned to registry, " . ($inserted - $returned) . ' still out.'
            . ($skipped ? " Skipped {$skipped} already tagged (use --fresh to replace)." : ''));

        if (! $dryRun && $inserted) {
            $this->newLine();
            $this->line("Remove this batch with:  php artisan file-tracker:seed-fake-logs --tag={$tag} --fresh");
            $this->line("or in SQL:               DELETE FROM file_tracker WHERE seed_tag = '{$tag}';");
        }

        return self::SUCCESS;
    }

    /**
     * Reads the JSON list. Each top-level key is a group of files whose name doubles
     * as the module ("Land files" -> land, "kangis files" -> kangis).
     *
     * @return array<int, array{file_number: string, file_title: string, module: string}>
     */
    private function readFiles(string $path): array
    {
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            return [];
        }

        $files = [];

        foreach ($json as $group => $entries) {
            if (! is_array($entries)) {
                continue;
            }

            $module = str_replace(' ', '_', trim(preg_replace('/\bfiles?\b/i', '', strtolower((string) $group))));

            foreach ($entries as $entry) {
                $number = trim((string) ($entry['file_number'] ?? ''));
                if ($number === '') {
                    continue;
                }

                $files[] = [
                    'file_number' => $number,
                    'file_title' => trim((string) ($entry['file_title'] ?? '')),
                    'module' => $module !== '' ? $module : 'land',
                ];
            }
        }

        return $files;
    }

    /**
     * Draws real file numbers off file_indexings so the seeded trackers resolve to a
     * genuine title and open properly from the File Log Table. Land lives in the three
     * numbered land registries; KANGIS in its own. Files that already have a tracker
     * are left out, so a demo history is never grafted onto a file a registry officer
     * is actually moving.
     *
     * @return array<int, array{file_number: string, file_title: string, module: string}>
     */
    private function fileNumbersFromIndexings(string $module, int $limit): array
    {
        $query = DB::connection(self::CONNECTION)->table('file_indexings')
            ->whereNotNull('file_number')
            ->where('file_number', '<>', '')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('file_tracker')
                    ->whereColumn('file_tracker.file_number', 'file_indexings.file_number');
            });

        $module === 'kangis'
            ? $query->where('registry', 'KANGIS')
            : $query->whereIn('registry', ['1', '2', '3']);

        // The same file number can be indexed more than once (registry variants), so
        // over-fetch and de-duplicate rather than trusting the row count.
        $rows = $query->inRandomOrder()->limit((int) ($limit * 1.3) + 10)
            ->get(['file_number', 'file_title']);

        $files = [];

        foreach ($rows as $row) {
            $number = trim((string) $row->file_number);

            if ($number === '' || isset($files[$number])) {
                continue;
            }

            $files[$number] = [
                'file_number' => $number,
                'file_title' => trim((string) $row->file_title),
                'module' => $module,
            ];

            if (count($files) >= $limit) {
                break;
            }
        }

        return array_values($files);
    }

    /**
     * Active offices minus the management block and every director-level office. The
     * name rule catches director offices whose code gives nothing away ("DCIV Director",
     * "Director, Admin and General Services") and survives a production offices table
     * that uses different codes, while "Deputy Director …" / "Assistant Director …" —
     * the offices files actually pass through — stay in.
     *
     * @return array<int, array{code: string, name: string, dept: string}>
     */
    private function officePool(): array
    {
        $this->droppedByRule = [];
        $pool = [];

        $offices = DB::connection(self::CONNECTION)->table('offices')
            ->where('is_active', 1)
            ->whereNotIn('office_code', self::EXCLUDED_OFFICE_CODES)
            ->orderBy('office_code')
            ->get();

        foreach ($offices as $office) {
            $name = trim((string) $office->office_name);

            if (! preg_match(self::DEPUTY_PREFIX_PATTERN, $name) && preg_match(self::SENIOR_OFFICE_PATTERN, $name)) {
                $this->droppedByRule[] = $office->office_code;
                continue;
            }

            $pool[] = [
                'code' => $office->office_code,
                'name' => $name,
                'dept' => trim((string) $office->department),
            ];
        }

        return $pool;
    }

    /**
     * Officer names are invented, never drawn from `users`. Seeding demo movements with
     * real staff names puts named colleagues — and service accounts like "PRA Deeds" —
     * on file journeys that never happened, and reads as a genuine audit trail. The
     * names below are ordinary Kano ones so the data still looks like the real register;
     * the matching *_id columns are left null, so nothing points at an actual account.
     *
     * @return array<int, string>
     */
    private function namePool(int $size = 400): array
    {
        // Kept apart so a three-part name never reads as a man with a woman's middle
        // name ("Bashir Ramlatu Nasir"), which no real register would contain.
        $male = [
            'Abubakar', 'Aliyu', 'Sani', 'Musa', 'Ibrahim', 'Usman', 'Yusuf', 'Bashir', 'Kabiru', 'Nasiru',
            'Salisu', 'Mustapha', 'Sulaiman', 'Auwalu', 'Lawan', 'Garba', 'Sagir', 'Shehu', 'Tijjani', 'Umar',
            'Haruna', 'Idris', 'Bello', 'Anas', 'Murtala', 'Rabiu', 'Isah', 'Nuhu', 'Adamu', 'Zakari',
            'Yahaya', 'Hamza', 'Mukhtar', 'Habibu', 'Jamilu', 'Kamalu', 'Munir', 'Sadiq', 'Abdulmalik', 'Abdullahi',
            'Abdulrahman', 'Ahmad', 'Zaharaddeen', 'Aminu', 'Farouk', 'Ismail', 'Nafiu', 'Naziru', 'Danladi', 'Sunusi',
        ];

        $female = [
            'Aisha', 'Amina', 'Fatima', 'Hauwa', 'Halima', 'Khadija', 'Maryam', 'Zainab', 'Safiya', 'Rukayya',
            'Hadiza', 'Bilkisu', 'Firdausi', 'Jamila', 'Nafisa', 'Rahma', 'Saudat', 'Zuwaira', 'Hafsat', 'Salamatu',
            'Balaraba', 'Habiba', 'Sadiya', 'Rabi', 'Yalwa', 'Asmau', 'Zahrau', 'Ramlatu', 'Hussaina', 'Ummulkhairi',
        ];

        $last = [
            'Abubakar', 'Adamu', 'Aliyu', 'Bala', 'Bello', 'Danladi', 'Garba', 'Gwadabe', 'Hassan', 'Ibrahim',
            'Idris', 'Inuwa', 'Isa', 'Jibrin', 'Kabiru', 'Lawal', 'Maikano', 'Mohammed', 'Musa', 'Nasir',
            'Rabiu', 'Sani', 'Shehu', 'Sulaiman', 'Tanko', 'Tijjani', 'Umar', 'Usman', 'Yakubu', 'Yusuf',
            'Dahiru', 'Gambo', 'Hamisu', 'Kurfi', 'Wali', 'Gwarzo', 'Rano', 'Bichi', 'Karaye', 'Tofa',
            'Ungogo', 'Kumbotso', 'Madobi', 'Gezawa', 'Minjibir', 'Dala', 'Fagge', 'Dawakin', 'Takai', 'Kiru',
        ];

        // Common Hausa names collide by chance — a run of 400 threw up two that matched
        // live accounts. Anything that lands on a real staff name is discarded, so no
        // colleague is shown moving files they never touched.
        $taken = DB::connection(self::CONNECTION)->table('users')
            ->whereNotNull('first_name')
            ->get(['first_name', 'last_name'])
            ->map(fn ($u) => mb_strtolower(trim($u->first_name . ' ' . $u->last_name)))
            ->filter()
            ->flip();

        $names = [];
        $guard = 0;

        // Kano names commonly run to three parts ("Aliyu Sani Gwarzo"); mixing two- and
        // three-part names keeps the register from looking mechanically generated. The
        // middle name is drawn from the same list as the first so the name stays coherent.
        while (count($names) < $size && $guard++ < $size * 20) {
            $given = rand(1, 10) <= 6 ? $male : $female;

            $name = rand(1, 10) <= 4
                ? $this->pick($given) . ' ' . $this->pick($given) . ' ' . $this->pick($last)
                : $this->pick($given) . ' ' . $this->pick($last);

            if ($taken->has(mb_strtolower($name))) {
                continue;
            }

            $names[$name] = true;
        }

        return array_keys($names);
    }

    /**
     * Purposes with a zero turnaround (Honorable Commissioners, Permanent Secretary)
     * are the management routes excluded here, and would yield a same-day deadline.
     *
     * @return array<int, array{id: int, name: string, days: int}>
     */
    private function purposePool(): array
    {
        return DB::connection(self::CONNECTION)->table('request_purposes')
            ->where('is_active', 1)
            ->where('turnaround_days', '>', 0)
            ->get()
            ->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name, 'days' => (int) $p->turnaround_days])
            ->values()->all();
    }

    /**
     * The registry a file belongs to and returns to. Real trackers store the registry's
     * name in origin_office_code as well — it is free text, not an offices row.
     */
    private function registryFor(string $module): array
    {
        $name = $module === 'kangis'
            ? 'KANGIS Registry'
            : $this->pick(['Registry 1 - Land', 'Registry 2 - Land', 'Registry 3 - Land']);

        return ['code' => $name, 'name' => $name, 'dept' => 'Registry'];
    }

    /**
     * One tracker and its movement history.
     *
     * Each office visit produces a pair of entries, which is what the UI reads back as
     * "Log-out" then "Completed":
     *   1. the log-out — status `active`, at the destination office
     *   2. the return  — status `logged_out` + new_status `Completed`, back at the registry
     * The final visit has no return entry when the file is still out, so it stays badged
     * "Log-out" and the tracker stays ACTIVE.
     *
     * @return array{row: array<string, mixed>, logs: int, returned: bool}
     */
    private function buildRecord(
        array $file,
        array $offices,
        array $names,
        array $purposes,
        Carbon $windowStart,
        Carbon $windowEnd,
        int $minLogs,
        int $maxLogs,
        int $returnedPct,
        string $tag
    ): array {
        // Entries come in pairs, so the visit count is derived from the wanted entry count.
        $minVisits = max(1, (int) ceil(($minLogs + 1) / 2));
        $maxVisits = max($minVisits, (int) floor(($maxLogs + 1) / 2));
        $visits = rand($minVisits, $maxVisits);

        $isReturned = rand(1, 100) <= $returnedPct;

        $registry = $this->registryFor($file['module']);
        $creator = $this->pick($names);
        $purpose = $this->pick($purposes);

        // request_purposes ships with turnaround_days = 5 on 43 of its 45 rows, so taking
        // the purpose's own figure most of the time put 5 days on 70% of the batch. The
        // purpose still leads, but only when it carries a considered (non-default) value.
        $timelineDays = ($purpose['days'] !== 5 && rand(1, 10) <= 7)
            ? $purpose['days']
            : $this->pick([3, 5, 5, 7, 7, 10, 14, 14, 21, 30, 45, 60, 90]);

        // The log-out date is derived from the timeline state we want, not picked freely.
        // Spreading log-outs evenly across a seven-month window left 979 of 1,000 files
        // past a 3-60 day deadline, so every card on the dashboard read "175 days
        // overdue". A real register is mostly inside its window: the further back a file
        // was logged out, the more likely it is late, which is what this reproduces.
        $windowDays = max(1, $windowStart->diffInDays($windowEnd));
        $latestStart = max(0, $windowDays - $visits - 1);
        $roll = rand(1, 100);

        if ($roll <= 45) {
            // Green — comfortably inside the window: logged out recently, deadline ahead.
            $elapsed = (int) floor($timelineDays * (rand(5, 60) / 100));
        } elseif ($roll <= 60) {
            // Amber — under 20% of the window left, which is the model's warning band.
            $elapsed = (int) floor($timelineDays * (rand(81, 99) / 100));
        } else {
            // Red — genuinely overdue, by anything from a day to most of the window.
            $elapsed = $timelineDays + rand(1, max(2, $windowDays - $timelineDays));
        }

        $daysBack = min($latestStart, max(0, $elapsed));
        $start = $windowEnd->copy()->subDays($daysBack)->setTime(rand(8, 11), rand(0, 59));

        if ($start->lessThan($windowStart)) {
            $start = $windowStart->copy()->setTime(rand(8, 11), rand(0, 59));
        }

        $avgVisitDays = max(1, intdiv((int) $start->diffInDays($windowEnd), $visits));

        $log = [];
        $cursor = $start->copy();
        $previous = null;
        $firstOffice = null;
        $lastOffice = null;
        $lastOfficer = null;
        $closedVisits = 0;
        $pages = rand(8, 60);

        for ($i = 0; $i < $visits; $i++) {
            do {
                $office = $this->pick($offices);
            } while ($previous && $office['code'] === $previous['code']);
            $previous = $office;
            $firstOffice ??= $office;

            $officer = $this->pick($names);
            $handler = $this->pick($names);
            $isLastVisit = ($i === $visits - 1);
            $stillOut = $isLastVisit && ! $isReturned;

            $outAt = $cursor->copy()->setTime(rand(8, 11), rand(0, 59));
            if ($outAt->greaterThan($windowEnd)) {
                $outAt = $windowEnd->copy();
            }

            $backAt = null;
            if (! $stillOut) {
                $backAt = $outAt->copy()->addDays(rand(1, max(2, $avgVisitDays)))->setTime(rand(13, 16), rand(0, 59));
                if ($backAt->greaterThan($windowEnd)) {
                    $backAt = $windowEnd->copy();
                }
            }

            // ── the log-out ──────────────────────────────────────────────────────
            $entry = [
                'log_id' => 'LOG-' . $outAt->format('YmdHis') . '-' . str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT),
                'office_code' => $office['code'],
                'office_name' => $office['name'],
                'log_in_time' => $outAt->format('H:i'),
                'log_in_date' => $outAt->format('Y-m-d'),
                'notes' => $i === 0 ? 'File logged out of registry' : 'File moved for processing',
                'user_id' => null,
                'user_name' => $officer,
                'timestamp' => $outAt->format('Y-m-d\TH:i:s\Z'),
                'status' => 'active',
                'receiving_office_code' => $office['code'],
                'receiving_office_name' => $office['name'],
                'receiving_officer_id' => null,
                'receiving_officer_name' => $handler,
                'origin_office_code' => $registry['code'],
                'origin_office_name' => $registry['name'],
                'origin_office_department' => $registry['dept'],
                'num_pages' => $pages,
                'in_digital_archive' => (bool) rand(0, 1),
            ];

            if ($backAt) {
                $entry['log_out_time'] = $backAt->format('H:i');
                $entry['log_out_date'] = $backAt->format('Y-m-d');
            }

            $log[] = $entry;
            $lastOffice = $office;
            $lastOfficer = $handler;

            // ── the return to the registry ───────────────────────────────────────
            if ($backAt) {
                $registryOfficer = $this->pick($names);
                $returnedPages = rand(1, 20) === 1 ? max(1, $pages - rand(1, 3)) : $pages;

                $log[] = [
                    'log_id' => 'LOG-' . $backAt->format('YmdHis') . '-' . str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT),
                    'action' => 'status_updated',
                    'office_code' => $registry['code'],
                    'office_name' => $registry['name'],
                    'log_in_time' => $backAt->format('H:i'),
                    'log_in_date' => $backAt->format('Y-m-d'),
                    'log_out_time' => null,
                    'log_out_date' => null,
                    'notes' => 'File logged back to Registry (Origin)'
                        . ($returnedPages !== $pages ? ' | Page discrepancy: ' . ($pages - $returnedPages) . ' page(s) MISSING (logged out with ' . $pages . ', returned ' . $returnedPages . ').' : ''),
                    'delay_reason' => null,
                    'status' => 'logged_out',
                    'old_status' => 'ACTIVE',
                    'new_status' => 'Completed',
                    'pages_original' => $pages,
                    'pages_returned' => $returnedPages,
                    'page_discrepancy' => $returnedPages - $pages,
                    'has_page_discrepancy' => $returnedPages !== $pages,
                    'timestamp' => $backAt->format('Y-m-d\TH:i:s\Z'),
                    'user_id' => null,
                    'user_name' => $registryOfficer,
                    'destination' => $registry['name'],
                    'receiving_office_code' => $registry['code'],
                    'receiving_office_name' => $registry['name'],
                    'receiving_officer_id' => null,
                    'receiving_officer_name' => $registryOfficer,
                    'origin_office_code' => $registry['code'],
                    'origin_office_name' => $registry['name'],
                    'origin_office_department' => $registry['dept'],
                ];

                $closedVisits++;
                $cursor = $backAt->copy()->addDays(rand(0, 3));
                if ($cursor->greaterThan($windowEnd)) {
                    $cursor = $windowEnd->copy();
                }
            }
        }

        $last = $log[count($log) - 1];
        $registryCode = str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // A returned file sits back in the registry; one still out sits at the last office.
        $currentCode = $isReturned ? $registry['code'] : $lastOffice['code'];
        $currentName = $isReturned ? $registry['name'] : $lastOffice['name'];
        $currentOfficer = $isReturned ? $last['receiving_officer_name'] : $lastOfficer;

        return [
            'logs' => count($log),
            'returned' => $isReturned,
            'row' => [
                'tracking_id' => 'TRK-' . $start->format('ymdHis') . '-' . strtoupper(substr(md5($file['file_number'] . microtime()), 0, 4)) . '-' . $registryCode,
                'file_number' => $file['file_number'],
                'file_title' => $file['file_title'],
                'file_type' => 'File',
                'priority' => $this->pick(['LOW', 'MEDIUM', 'HIGH']),
                'created_by' => null,
                'created_by_name' => $creator,
                'department' => DepartmentNormalizer::normalize($firstOffice['dept']),
                'description' => 'File tracked in ' . $currentName,
                // Matches FileTrackerController::updateStatus, which writes the literal
                // 'Completed' on log-back; ACTIVE is the out-of-registry state.
                'status' => $isReturned ? 'Completed' : 'ACTIVE',
                'date_created' => $start->format('Y-m-d H:i:s'),
                'date_requested' => $start->format('Y-m-d H:i:s'),
                // deadline is rebuilt from the window rather than stored independently,
                // matching trackers:backfill-timeline-days — 23:59:59, not endOfDay(),
                // whose microseconds round up to the next midnight in SQL Server.
                'deadline' => $start->copy()->addDays($timelineDays)->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
                'timeline_days' => $timelineDays,
                'movement_log' => json_encode($log, JSON_UNESCAPED_SLASHES),
                'current_office_code' => $currentCode,
                'current_office_name' => $currentName,
                'total_offices' => $visits,
                'completed_offices' => $closedVisits,
                'notes' => null,
                'created_at' => $start->format('Y-m-d H:i:s'),
                'updated_at' => $last['log_in_date'] . ' ' . $last['log_in_time'] . ':00',
                'receiving_office_code' => $currentCode,
                'receiving_office_name' => $currentName,
                'receiving_officer_id' => null,
                'receiving_officer_name' => $currentOfficer,
                'assignment_status' => 'ACCEPTED',
                'assignment_accepted_at' => $start->format('Y-m-d H:i:s'),
                'origin_office_code' => $registry['code'],
                'origin_office_name' => $registry['name'],
                'origin_office_department' => $registry['dept'],
                'module' => $file['module'],
                'printed' => 0,
                'registry_code' => $registryCode,
                'num_pages' => $pages,
                'returned_num_pages' => $isReturned ? ($last['pages_returned'] ?? $pages) : null,
                'request_purpose_id' => $purpose['id'],
                'request_purpose_name' => $purpose['name'],
                'file_request_type' => $this->pick(['MANUAL', 'SUBMITTED']),
                'seed_tag' => $tag,
            ],
        ];
    }

    /** @param array<int, mixed> $items */
    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }
}
