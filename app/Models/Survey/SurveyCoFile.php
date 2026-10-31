<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A CO (Compensation) file, numbered CO-<serial>-<year>, e.g. CO-9-2018: the
 * serial has no leading zeros and restarts each year (user, 2026-10-30). One CO
 * file holds many projects (user transcript, 2026-10-25). Two ways in:
 * commissioned (numbered by the system) and captured (an existing paper CO file
 * keyed in under its own number, in the same format).
 */
class SurveyCoFile extends SurveyModel
{
    use Concerns\HasImportOrigin;

    protected $table = 'survey_co_files';

    protected $casts = ['commission_date' => 'date'];

    /** Every status, for filters. A form offers only its mode's (statusesFor). */
    public const STATUSES = ['Draft', 'Commissioned', 'Captured', 'Active', 'Closed'];

    public const CAPTURED_STATUS = 'Captured';

    public const SOURCE_COMMISSIONED = 'commissioned';
    public const SOURCE_CAPTURED     = 'captured';

    /** The earliest year a captured CO file may carry. */
    public const FIRST_YEAR = 1960;

    /** A commissioned file is Draft / Commissioned / Active / Closed; a captured one starts Captured (user, 2026-10-30). */
    public static function statusesFor(?string $source): array
    {
        return $source === self::SOURCE_CAPTURED
            ? [self::CAPTURED_STATUS, 'Active', 'Closed']
            : ['Draft', 'Commissioned', 'Active', 'Closed'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(SurveyProject::class, 'survey_co_file_id');
    }

    /** A "(TP/KNUPDA/338A)"-style layout plan number in brackets. */
    private const PLAN_IN_BRACKETS = '/\s*\(\s*((?:TP|BLM|KNUPDA|MLPP|KASSEPPA|KAS|UC|KN|LPKN)\b[^()]*\/[^()]*)\)/i';

    /**
     * The title for listings, without the TP numbers (they have their own column),
     * and with a name repeated for each plan said once: "K/Inna (TP/KNUPDA/338) /
     * K/Inna (TP/KNUPDA/338A)" reads "K/Inna". The stored title is not changed.
     */
    public function getDisplayTitleAttribute(): string
    {
        $t = trim((string) preg_replace(self::PLAN_IN_BRACKETS, '', (string) $this->title));
        $parts = [];
        foreach (preg_split('/\s+\/\s+/', $t) as $p) {
            $p = trim($p);
            if ($p !== '' && ! in_array(mb_strtolower($p), array_map('mb_strtolower', $parts), true)) $parts[] = $p;
        }

        return $parts ? implode(' / ', $parts) : (string) $this->title;
    }

    /** The TP numbers of this CO file: its projects' plan numbers, else those written in the title. */
    public function tpNumbers(): array
    {
        $nos = $this->relationLoaded('projects') ? $this->projects->pluck('plan_no')->all() : [];
        preg_match_all(self::PLAN_IN_BRACKETS, (string) $this->title, $m);
        $nos = array_merge($nos, $m[1] ?? []);

        $out = [];
        foreach ($nos as $n) {
            $n = trim((string) $n);
            if ($n !== '' && ! in_array(strtoupper($n), array_map('strtoupper', $out), true)) $out[] = $n;
        }

        return $out;
    }

    /**
     * A CO file number as typed, in the standard form: "co 09/2018", "CO-09-2018" and
     * "CO 9 2018" all become "CO-9-2018". Null when it is not a CO number (no serial,
     * a zero serial, or no four-digit year).
     */
    public static function normaliseNumber(?string $raw): ?string
    {
        $s = strtoupper(trim((string) $raw));
        if (! preg_match('/^CO[\s\-\/.]*0*(\d{1,7})[\s\-\/.]+(\d{4})$/', $s, $m) || (int) $m[1] === 0) {
            return null;
        }

        return 'CO-' . (int) $m[1] . '-' . $m[2];
    }

    /**
     * The next number for the year: one above the highest serial already used that
     * year, commissioned or captured, deleted rows included (their numbers stay taken).
     * Compared as numbers, so CO-10 follows CO-9.
     */
    public static function nextNumber(?int $year = null): string
    {
        $year = $year ?? (int) date('Y');
        $max = 0;
        foreach (static::withTrashed()->where('co_file_no', 'like', "CO-%-{$year}")->pluck('co_file_no') as $no) {
            if (preg_match('/^CO-(\d+)-' . $year . '$/', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'CO-' . ($max + 1) . '-' . $year;
    }
}
