<?php

namespace App\Services\Cadastral;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one read API for every configurable Cadastral value.
 *
 * Rebuild plan D4: fee-sheet rates, the area-fee schedule, transport bands,
 * number formats, file prefixes and source registries are edited in System
 * Admin -> Configurable Entries -> Cadastral and stored in three tables:
 *
 *   cadastral_settings            one row per scalar/list setting (key -> value)
 *   cadastral_area_fee_schedule   the S.L.N. No. 3 of 1983 hectare schedule
 *   cadastral_transport_bands     the transport distance bands
 *
 * FALLBACK TO CONFIG. Those tables arrive with a migration that has to be run by
 * hand (2026_10_01_100000, --path only), so until then -- and for any single key
 * nobody has saved -- the value comes from config/cadastral_module.php. A setting
 * key IS its config path under cadastral_module ('fee_sheet.rates.beacon'), which
 * is what makes the per-key fallback one line. The schedule and the bands fall
 * back as a whole when their table is missing or holds no rows at all; a table
 * with rows that are all switched off is NOT empty, it is an admin saying "none".
 *
 * CACHED FOR THE REQUEST. hasTable() and the three reads happen at most once per
 * request and are held in static properties; the Configurable Entries saves call
 * forget() so the page they re-render shows what was just written. Static rather
 * than a container singleton because registering one would mean editing a
 * service provider for the sake of three arrays. A long-running worker (queue)
 * would hold stale values until forget(); nothing cadastral runs in one today.
 */
class CadastralSettings
{
    private const CONN = 'sqlsrv';

    public const TABLE_SETTINGS = 'cadastral_settings';
    public const TABLE_SCHEDULE = 'cadastral_area_fee_schedule';
    public const TABLE_BANDS = 'cadastral_transport_bands';

    /** Which fee column of the schedule a bill uses. */
    public const SCHEDULE_COLUMNS = [
        'proposed' => 'Proposed fees',
        'current' => 'Current fees',
    ];

    /** Rebuild plan Q1. */
    public const BETWEEN_ROWS_RULES = [
        'next_row_up' => 'Next row up (0.45 Ha is charged as 0.50 Ha)',
        'interpolate' => 'Interpolate between the two rows',
    ];

    /** Rebuild plan Q2. */
    public const ABOVE_MAX_RULES = [
        'refuse' => 'Refuse to bill (the officer is told why)',
        'charge_max_row' => 'Charge the largest row of the schedule',
    ];

    /**
     * Every setting the Cadastral tab edits.
     *
     * key = config path under cadastral_module, so config() is its fallback.
     * type: money | decimal | int | bool | string | choice | list
     * group: the tab section that saves it (ConfigurableEntriesController::saveCadastralSettings).
     *
     * @var array<string, array{type: string, label: string, group: string, options?: array, hint?: string}>
     */
    public const DEFINITIONS = [
        'fee_sheet.rates.investigation' => ['type' => 'money', 'group' => 'fees', 'label' => 'Investigation and Search (flat)'],
        'fee_sheet.rates.beacon' => ['type' => 'money', 'group' => 'fees', 'label' => 'Beacons (each)'],
        'fee_sheet.rates.delay_per_day' => ['type' => 'money', 'group' => 'fees', 'label' => 'Delay (per day)'],
        'fee_sheet.rates.field_work_per_day' => ['type' => 'money', 'group' => 'fees', 'label' => 'Additional field work (per day)'],
        'fee_sheet.rates.office_work_per_day' => ['type' => 'money', 'group' => 'fees', 'label' => 'Office work (per day)'],
        'fee_sheet.rates.plan_print' => ['type' => 'money', 'group' => 'fees', 'label' => 'Plan prints (per file)'],

        'fee_sheet.area_schedule_column' => ['type' => 'choice', 'group' => 'area', 'label' => 'Schedule column bills use', 'options' => self::SCHEDULE_COLUMNS],
        'fee_sheet.between_rows_rule' => ['type' => 'choice', 'group' => 'area', 'label' => 'Area between two rows', 'options' => self::BETWEEN_ROWS_RULES],
        'fee_sheet.between_rows_confirmed' => ['type' => 'bool', 'group' => 'area', 'label' => 'Between-rows rule confirmed by the department'],
        'fee_sheet.above_max_area' => ['type' => 'choice', 'group' => 'area', 'label' => 'Area above the largest row', 'options' => self::ABOVE_MAX_RULES],
        'area.plot_size_sqm' => ['type' => 'decimal', 'group' => 'area', 'label' => 'Standard plot size (m²)'],

        'fee_sheet.transport_bands_confirmed' => ['type' => 'bool', 'group' => 'transport', 'label' => 'Transport bands confirmed by the department'],

        'job_number.format' => ['type' => 'string', 'group' => 'numbering', 'label' => 'Survey job number format'],
        'job_number.serial_pad' => ['type' => 'int', 'group' => 'numbering', 'label' => 'Survey job serial digits'],
        'job_number.is_placeholder' => ['type' => 'bool', 'group' => 'numbering', 'label' => 'Job number format is still a placeholder (not SURCON-confirmed)'],
        'its_number.format' => ['type' => 'string', 'group' => 'numbering', 'label' => 'Instruction to Surveyor (ITS) number format'],
        'its_number.serial_pad' => ['type' => 'int', 'group' => 'numbering', 'label' => 'ITS serial digits'],

        'file_numbers.direct_prefixes' => ['type' => 'list', 'group' => 'file_numbers', 'label' => 'Direct file-number prefixes (charted)'],
        'file_numbers.conversion_prefixes' => ['type' => 'list', 'group' => 'file_numbers', 'label' => 'Conversion file-number prefixes (not charted)'],
        'file_numbers.source_registries' => ['type' => 'list', 'group' => 'file_numbers', 'label' => 'Source registries offered at intake'],
    ];

    /** Short keys feeRates() returns, in fee-sheet order. */
    public const FEE_RATE_KEYS = ['investigation', 'beacon', 'delay_per_day', 'field_work_per_day', 'office_work_per_day', 'plan_print'];

    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, object>|null cadastral_settings rows by key */
    private static ?array $rows = null;

    private static ?array $schedule = null;

    private static ?array $bands = null;

    /** Drop the request cache, after a save. */
    public static function forget(): void
    {
        self::$tables = [];
        self::$rows = null;
        self::$schedule = null;
        self::$bands = null;
    }

    public function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::connection(self::CONN)->hasTable($table);
    }

    /** True once the migration has run, i.e. edits can be saved. */
    public function isInstalled(): bool
    {
        return $this->hasTable(self::TABLE_SETTINGS)
            && $this->hasTable(self::TABLE_SCHEDULE)
            && $this->hasTable(self::TABLE_BANDS);
    }

    // ---- Scalar settings ------------------------------------------------------

    /**
     * One setting, typed: the saved value when there is a row, else config.
     * Works for any cadastral_module config path, defined above or not.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->rows()[$key] ?? null;

        if ($row !== null && $row->value !== null) {
            return $this->cast($row->value, $row->value_type ?: (self::DEFINITIONS[$key]['type'] ?? 'string'));
        }

        return config('cadastral_module.' . $key, $default);
    }

    /** 'database' when an admin has saved the key, 'config' when it is the shipped default. */
    public function source(string $key): string
    {
        $row = $this->rows()[$key] ?? null;

        return $row !== null && $row->value !== null ? 'database' : 'config';
    }

    /** The saved row's metadata (updated_by, updated_at), or null. */
    public function row(string $key): ?object
    {
        return $this->rows()[$key] ?? null;
    }

    /**
     * The fee-sheet unit rates.
     *
     * @return array<string, float> investigation, beacon, delay_per_day, field_work_per_day, office_work_per_day, plan_print
     */
    public function feeRates(): array
    {
        $rates = [];
        foreach (self::FEE_RATE_KEYS as $short) {
            $rates[$short] = (float) $this->get('fee_sheet.rates.' . $short, 0);
        }

        return $rates;
    }

    public function areaScheduleColumn(): string
    {
        $column = (string) $this->get('fee_sheet.area_schedule_column', 'proposed');

        return array_key_exists($column, self::SCHEDULE_COLUMNS) ? $column : 'proposed';
    }

    public function betweenRowsRule(): string
    {
        $rule = (string) $this->get('fee_sheet.between_rows_rule', 'next_row_up');

        return array_key_exists($rule, self::BETWEEN_ROWS_RULES) ? $rule : 'next_row_up';
    }

    public function aboveMaxArea(): string
    {
        $rule = (string) $this->get('fee_sheet.above_max_area', 'refuse');

        return array_key_exists($rule, self::ABOVE_MAX_RULES) ? $rule : 'refuse';
    }

    public function plotSizeSqm(): float
    {
        return (float) $this->get('area.plot_size_sqm', 450.0);
    }

    /** @return array{format: string, serial_pad: int, is_placeholder: bool} */
    public function jobNumberFormat(): array
    {
        return [
            'format' => (string) $this->get('job_number.format', 'KN/CAD/{year}/{serial}'),
            'serial_pad' => (int) $this->get('job_number.serial_pad', 4),
            'is_placeholder' => (bool) $this->get('job_number.is_placeholder', true),
        ];
    }

    /** @return array{format: string, serial_pad: int} */
    public function itsNumberFormat(): array
    {
        return [
            'format' => (string) $this->get('its_number.format', 'ITS/{year}/{serial}'),
            'serial_pad' => (int) $this->get('its_number.serial_pad', 4),
        ];
    }

    /** @return array{direct: string[], conversion: string[]} upper-cased */
    public function filePrefixes(): array
    {
        return [
            'direct' => $this->upperList($this->get('file_numbers.direct_prefixes', [])),
            'conversion' => $this->upperList($this->get('file_numbers.conversion_prefixes', ['CON'])),
        ];
    }

    /** @return string[] */
    public function sourceRegistries(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->get('file_numbers.source_registries', [])), 'strlen'));
    }

    // ---- Area-fee schedule ----------------------------------------------------

    /**
     * The hectare schedule, smallest first.
     *
     * Each row: id (null from config), hectares, current_fee, proposed_fee,
     * additional_note, proposed_additional_note, sort_order, is_active.
     *
     * @return array<int, array>
     */
    public function areaSchedule(bool $activeOnly = true): array
    {
        $rows = self::$schedule ??= $this->loadTable(self::TABLE_SCHEDULE, 'area_fee_schedule', fn (array $r, int $i) => [
            'id' => $r['id'] ?? null,
            'hectares' => round((float) $r['hectares'], 2),
            'current_fee' => $r['current_fee'] === null ? null : (float) $r['current_fee'],
            'proposed_fee' => $r['proposed_fee'] === null ? null : (float) $r['proposed_fee'],
            'additional_note' => $r['additional_note'] ?? null,
            'proposed_additional_note' => $r['proposed_additional_note'] ?? null,
            'sort_order' => (int) ($r['sort_order'] ?? $i + 1),
            'is_active' => (bool) ($r['is_active'] ?? true),
        ], 'hectares');

        return $activeOnly ? array_values(array_filter($rows, fn ($r) => $r['is_active'])) : $rows;
    }

    /** 'database' or 'config', for the schedule as a whole. */
    public function areaScheduleSource(): string
    {
        return $this->tableSource(self::TABLE_SCHEDULE);
    }

    /** The largest hectares in the active schedule, or null when it is empty. */
    public function maxScheduleHectares(): ?float
    {
        $rows = $this->areaSchedule();

        return $rows ? (float) end($rows)['hectares'] : null;
    }

    /**
     * The area fee for an area in hectares, from the column in use.
     *
     * Exact row: that row's fee. Below the smallest row: the smallest row's fee
     * (there is no lower row to interpolate from). Between two rows: the
     * configured rule -- next row up (0.45 -> the 0.50 row, 4,200) or a straight
     * line between the two (0.45 -> 3,800). Above the largest row: null when the
     * rule is 'refuse' (Q2 is open), else the largest row's fee.
     *
     * Null also when the area is not positive or the schedule is empty, so a
     * caller can never mistake "cannot be charged" for "free". Rounded to the kobo.
     */
    public function areaFee(float $hectares): ?float
    {
        $rows = array_values(array_filter(
            $this->areaSchedule(),
            fn ($r) => $this->feeOf($r) !== null
        ));

        if ($hectares <= 0 || $rows === []) {
            return null;
        }

        // Compare at schedule precision, so 0.4999999 from an m² conversion
        // does not land between 0.40 and 0.50 when it means 0.50.
        $ha = round($hectares, 4);
        $epsilon = 0.00005;

        $first = $rows[0];
        if ($ha <= $first['hectares'] + $epsilon) {
            return round($this->feeOf($first), 2);
        }

        $previous = $first;
        foreach ($rows as $row) {
            if (abs($ha - $row['hectares']) < $epsilon) {
                return round($this->feeOf($row), 2);
            }

            if ($ha < $row['hectares']) {
                if ($this->betweenRowsRule() === 'interpolate') {
                    $span = $row['hectares'] - $previous['hectares'];
                    $fraction = $span > 0 ? ($ha - $previous['hectares']) / $span : 1.0;
                    $fee = $this->feeOf($previous) + $fraction * ($this->feeOf($row) - $this->feeOf($previous));

                    return round($fee, 2);
                }

                return round($this->feeOf($row), 2);
            }

            $previous = $row;
        }

        // Above the largest row.
        return $this->aboveMaxArea() === 'refuse' ? null : round($this->feeOf($previous), 2);
    }

    private function feeOf(array $row): ?float
    {
        return $row[$this->areaScheduleColumn() . '_fee'];
    }

    // ---- Transport bands ------------------------------------------------------

    /**
     * Transport bands, nearest first. Each: id, label, min_km, max_km (null =
     * and over), fee, sort_order, is_active.
     *
     * @return array<int, array>
     */
    public function transportBands(bool $activeOnly = true): array
    {
        $rows = self::$bands ??= $this->loadTable(self::TABLE_BANDS, 'transport_bands', fn (array $r, int $i) => [
            'id' => $r['id'] ?? null,
            'label' => (string) $r['label'],
            'min_km' => (float) $r['min_km'],
            'max_km' => $r['max_km'] === null ? null : (float) $r['max_km'],
            'fee' => (float) $r['fee'],
            'sort_order' => (int) ($r['sort_order'] ?? $i + 1),
            'is_active' => (bool) ($r['is_active'] ?? true),
        ], 'min_km');

        return $activeOnly ? array_values(array_filter($rows, fn ($r) => $r['is_active'])) : $rows;
    }

    public function transportBandsSource(): string
    {
        return $this->tableSource(self::TABLE_BANDS);
    }

    /**
     * The band a distance falls in. Bands are whole kilometres (1-10, 11-50),
     * so the distance is rounded up first: 10.4 km is in the 11-50 band.
     */
    public function transportBandFor(float $km): ?array
    {
        $whole = (float) ceil(max(0, $km));

        foreach ($this->transportBands() as $band) {
            if ($whole >= $band['min_km'] && ($band['max_km'] === null || $whole <= $band['max_km'])) {
                return $band;
            }
        }

        return null;
    }

    // ---- Defaults, shared with the seeder and the tab's "copy defaults" -------

    /** @return array<int, array> the config schedule, shaped as table rows */
    public static function defaultScheduleRows(): array
    {
        return array_values(array_map(fn (array $r, int $i) => [
            'hectares' => $r['hectares'],
            'current_fee' => $r['current_fee'],
            'proposed_fee' => $r['proposed_fee'],
            'additional_note' => $r['additional_note'] ?? null,
            'proposed_additional_note' => $r['proposed_additional_note'] ?? null,
            'sort_order' => $i + 1,
            'is_active' => true,
        ], (array) config('cadastral_module.area_fee_schedule', []), array_keys((array) config('cadastral_module.area_fee_schedule', []))));
    }

    /** @return array<int, array> */
    public static function defaultBandRows(): array
    {
        return array_values(array_map(fn (array $r, int $i) => [
            'label' => $r['label'],
            'min_km' => $r['min_km'],
            'max_km' => $r['max_km'] ?? null,
            'fee' => $r['fee'],
            'sort_order' => $i + 1,
            'is_active' => true,
        ], (array) config('cadastral_module.transport_bands', []), array_keys((array) config('cadastral_module.transport_bands', []))));
    }

    /** A config value encoded the way cadastral_settings.value stores it. */
    public static function encode(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'list' => json_encode(array_values((array) $value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    // ---- Internals ------------------------------------------------------------

    /** @return array<string, object> */
    private function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }

        if (! $this->hasTable(self::TABLE_SETTINGS)) {
            return self::$rows = [];
        }

        return self::$rows = DB::connection(self::CONN)->table(self::TABLE_SETTINGS)
            ->get(['key', 'value', 'value_type', 'label', 'group', 'updated_by', 'updated_at'])
            ->keyBy('key')
            ->all();
    }

    /**
     * Table rows when the table exists and holds any row; the config list otherwise.
     */
    private function loadTable(string $table, string $configKey, callable $shape, string $sortBy): array
    {
        $raw = null;

        if ($this->hasTable($table)) {
            $raw = DB::connection(self::CONN)->table($table)->orderBy($sortBy)->orderBy('sort_order')->get()
                ->map(fn ($r) => (array) $r)->all();
        }

        if (! $raw) {
            $raw = (array) config('cadastral_module.' . $configKey, []);
        }

        $rows = array_map($shape, array_values($raw), array_keys(array_values($raw)));
        usort($rows, fn ($a, $b) => [$a[$sortBy], $a['sort_order']] <=> [$b[$sortBy], $b['sort_order']]);

        return $rows;
    }

    private function tableSource(string $table): string
    {
        return $this->hasTable($table) && DB::connection(self::CONN)->table($table)->exists() ? 'database' : 'config';
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'money', 'decimal' => (float) $value,
            'int' => (int) $value,
            'bool' => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true),
            'list', 'json' => (array) (json_decode($value, true) ?? []),
            default => $value,
        };
    }

    private function upperList(mixed $value): array
    {
        return array_values(array_filter(array_map(fn ($v) => strtoupper(trim((string) $v)), (array) $value), 'strlen'));
    }
}
