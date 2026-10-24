<?php

namespace App\Models\Survey;

use App\Models\User;

/**
 * One line of the Beacon Register book: a range of beacon numbers issued to a
 * surveyor on a date, with the payment that covered it.
 *
 * The range is expanded into one survey_beacon_numbers row per number
 * (user, 2026-10-23); every one of them belongs to this line's surveyor, date,
 * location and payment.
 */
class SurveyBeaconRegister extends SurveyModel
{
    protected $table = 'survey_beacon_register';

    protected $casts = [
        'issue_date'    => 'date',
        'date_received' => 'date',
        'reviewed_at'   => 'datetime',
        'audited_at'    => 'datetime',
        'amount'        => 'decimal:2',
    ];

    public const PAYMENT_MODES = ['E-Payment', 'Cash', 'Other'];

    /** Stored value => label. The first choice on the form. */
    public const LAYOUT_TYPES = ['Government' => 'Government', 'Private' => 'Private'];

    public function getLayoutTypeLabelAttribute(): ?string
    {
        return self::LAYOUT_TYPES[$this->layout_type] ?? null;
    }

    /** Largest range one line may expand to; guards against a mistyped end number. */
    public const MAX_RANGE = 1000;

    /** Beacon numbers are letters, digits, letters: C9308, C9327A, KN/0098B. */
    public static function normalise(?string $code): ?string
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * Split a beacon number: "C9302B" -> series "C|B", number 9302, prefix "C",
     * suffix "B", width 4 (so leading zeros survive expansion). Null when there
     * is no single run of digits to count on.
     *
     * @return array{series:string, n:int, prefix:string, suffix:string, width:int}|null
     */
    public static function parseBeacon(?string $code): ?array
    {
        $code = (string) self::normalise($code);
        if (! preg_match('/^([A-Z\/\-]*)(\d+)([A-Z]*)$/', $code, $m)) {
            return null;
        }

        return [
            'series' => $m[1] . '|' . $m[3],
            'n'      => (int) $m[2],
            'prefix' => $m[1],
            'suffix' => $m[3],
            'width'  => strlen($m[2]),
        ];
    }

    /**
     * The parsed range, or null when the two ends are not the same series or
     * the end is lower than the start. A blank end means a single beacon.
     *
     * @return array{series:string, start:int, end:int, count:int, prefix:string, suffix:string, width:int}|null
     */
    public static function parseRange(?string $from, ?string $to): ?array
    {
        $a = self::parseBeacon($from);
        $b = ($to === null || trim($to) === '') ? $a : self::parseBeacon($to);
        if (! $a || ! $b || $a['series'] !== $b['series'] || $b['n'] < $a['n']) {
            return null;
        }

        return [
            'series' => $a['series'],
            'start'  => $a['n'],
            'end'    => $b['n'],
            'count'  => $b['n'] - $a['n'] + 1,
            'prefix' => $a['prefix'],
            'suffix' => $a['suffix'],
            'width'  => $a['width'],
        ];
    }

    /**
     * Every beacon number in a parsed range, start and end included:
     * C9302B – C9306B -> [9302 => 'C9302B', ..., 9306 => 'C9306B'].
     *
     * @return array<int, string>
     */
    public static function expand(array $range): array
    {
        $out = [];
        for ($n = $range['start']; $n <= $range['end']; $n++) {
            $out[$n] = $range['prefix'] . str_pad((string) $n, $range['width'], '0', STR_PAD_LEFT) . $range['suffix'];
        }

        return $out;
    }

    /** The line's file numbers, in the order picked (user, 2026-10-23). */
    public function files()
    {
        return $this->hasMany(SurveyBeaconRegisterFile::class, 'survey_beacon_register_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return array<int, string> */
    public function fileNumberList(): array
    {
        $list = $this->exists ? $this->files->pluck('file_number')->all() : [];

        // Lines saved before the list existed carry only the single column.
        return $list ?: array_values(array_filter([$this->file_number]));
    }

    public function numbers()
    {
        return $this->hasMany(SurveyBeaconNumber::class, 'survey_beacon_register_id')->orderBy('beacon_seq');
    }

    public function getIssuedRangeAttribute(): string
    {
        return $this->beacon_to && $this->beacon_to !== $this->beacon_from
            ? "{$this->beacon_from} → {$this->beacon_to}"
            : (string) $this->beacon_from;
    }

    public function getPaymentLabelAttribute(): ?string
    {
        return $this->payment_mode === 'Other' ? ($this->payment_mode_other ?: 'Other') : $this->payment_mode;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function auditor()
    {
        return $this->belongsTo(User::class, 'audited_by');
    }
}
