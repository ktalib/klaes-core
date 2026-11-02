<?php

namespace App\Services\IndexingPay;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The indexing rate card, effective-dated (Configurable Entries > Indexing Pay).
 *
 * Each saved version applies from its effective_from date until the next version, so
 * changing a rate never alters a day already earned. With no version saved, the approved
 * card in config/indexing_pay.php applies.
 */
class IndexingPaySettings
{
    public const TABLE = 'indexing_pay_settings';

    /** File types, in the order screens list them. ITF is dormant (user, 2026-11-01). */
    public const TYPES = [
        'regular'     => 'Regular Land File',
        'sltr'        => 'SLTR File',
        'kangis_land' => 'KANGIS Land File',
        'kangis'      => 'KANGIS File',
    ];

    public const RECORDS = [
        'trans' => 'Transaction',
        'gr'    => 'Ground Rent',
        'bb'    => 'Bill Balance',
    ];

    /** @var array<int, object>|null versions ordered by effective_from */
    private static ?array $versions = null;

    public static function defaults(): array
    {
        return config('indexing_pay.defaults', []);
    }

    public function tableExists(): bool
    {
        static $exists = null;

        return $exists ??= Schema::connection('sqlsrv')->hasTable(self::TABLE);
    }

    /** @return array<int, object> */
    public function versions(): array
    {
        if (self::$versions !== null) {
            return self::$versions;
        }

        if (! $this->tableExists()) {
            return self::$versions = [];
        }

        return self::$versions = DB::connection('sqlsrv')->table(self::TABLE)
            ->orderBy('effective_from')
            ->get()
            ->map(function ($row) {
                $row->effective_from = substr((string) $row->effective_from, 0, 10);
                $row->values = self::normalise(json_decode((string) $row->settings, true) ?: []);

                return $row;
            })
            ->all();
    }

    public function forget(): void
    {
        self::$versions = null;
    }

    /** The card in force on a date (Y-m-d or Carbon). */
    public function forDate($date): array
    {
        $day = $date instanceof Carbon ? $date->toDateString() : substr((string) $date, 0, 10);
        $current = null;

        foreach ($this->versions() as $version) {
            if ($version->effective_from <= $day) {
                $current = $version->values;
            }
        }

        return $current ?? self::normalise([]);
    }

    public function current(): array
    {
        return $this->forDate(now()->toDateString());
    }

    /**
     * Fill gaps from the defaults and coerce types, so a hand-edited or older row can never
     * yield a missing key or a string amount.
     */
    public static function normalise(array $values): array
    {
        $d = self::defaults();
        $out = [];

        foreach (array_keys($d['base']) as $type) {
            $out['base'][$type] = self::money($values['base'][$type] ?? $d['base'][$type]);
        }
        foreach (array_keys($d['rates']) as $rec) {
            $out['rates'][$rec] = self::money($values['rates'][$rec] ?? $d['rates'][$rec]);
        }

        $out['sltr_pays_gr_bb'] = (bool) ($values['sltr_pays_gr_bb'] ?? $d['sltr_pays_gr_bb']);
        $out['start_date'] = self::date($values['start_date'] ?? null) ?? $d['start_date'];
        $out['end_date'] = self::date($values['end_date'] ?? null) ?? $d['end_date'];
        $out['daily_file_target'] = max(0, (int) ($values['daily_file_target'] ?? $d['daily_file_target']));
        $out['monthly_target'] = self::money($values['monthly_target'] ?? $d['monthly_target']);

        $modules = $values['eligible_modules'] ?? $d['eligible_modules'];
        $modules = is_array($modules) ? $modules : explode(',', (string) $modules);
        $out['eligible_modules'] = array_values(array_unique(array_filter(array_map(fn ($m) => trim((string) $m), $modules), 'strlen')));

        $types = $values['eligible_staff_types'] ?? $d['eligible_staff_types'] ?? [];
        $types = is_array($types) ? $types : explode(',', (string) $types);
        $out['eligible_staff_types'] = array_values(array_unique(array_filter(array_map(fn ($t) => strtoupper(trim((string) $t)), $types), 'strlen')));

        $stations = $values['eligible_work_stations'] ?? $d['eligible_work_stations'] ?? [];
        $stations = is_array($stations) ? $stations : explode(',', (string) $stations);
        $out['eligible_work_stations'] = array_values(array_unique(array_filter(array_map(fn ($w) => trim((string) $w), $stations), 'strlen')));

        foreach (array_keys($d['show']) as $place) {
            $out['show'][$place] = (bool) ($values['show'][$place] ?? $d['show'][$place]);
        }

        return $out;
    }

    private static function money($value): float
    {
        return round(max(0, (float) $value), 2);
    }

    private static function date($value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    /** Save a version. A second save on the same effective date replaces that version. */
    public function save(array $values, string $effectiveFrom, ?int $userId, ?string $note = null): void
    {
        $db = DB::connection('sqlsrv');
        $payload = json_encode(self::normalise($values), JSON_UNESCAPED_SLASHES);
        $existing = $db->table(self::TABLE)->where('effective_from', $effectiveFrom)->first();

        if ($existing) {
            $db->table(self::TABLE)->where('id', $existing->id)->update([
                'settings' => $payload, 'note' => $note, 'updated_by' => $userId, 'updated_at' => now(),
            ]);
        } else {
            $db->table(self::TABLE)->insert([
                'effective_from' => $effectiveFrom, 'settings' => $payload, 'note' => $note,
                'created_by' => $userId, 'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->forget();
    }

    /** Data for the Configurable Entries tab. */
    public static function tabData(): array
    {
        $self = app(self::class);
        $userNames = [];
        $ids = array_filter(array_map(fn ($v) => $v->updated_by ?? $v->created_by, $self->versions()));
        if ($ids) {
            $userNames = \App\Models\User::whereIn('id', array_unique($ids))->get()
                ->mapWithKeys(fn ($u) => [$u->id => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''))])->all();
        }

        return [
            'indexingPay' => [
                'ready'     => $self->tableExists(),
                'current'   => $self->current(),
                'versions'  => array_reverse($self->versions()),
                'userNames' => $userNames,
                'types'     => self::TYPES,
                'records'   => self::RECORDS,
                'eligibleCount' => $self->tableExists() ? count(app(EarningsService::class)->eligibleUserIds()) : 0,
            ],
        ];
    }
}
