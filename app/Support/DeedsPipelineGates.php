<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How strictly each hand-off of Valuation -> Consent -> Print -> Registration is
 * enforced, and where that answer is kept.
 *
 * config/deeds_pipeline.php carries the shipped default for each gate. This class
 * lets System Admin -> Configurable Entries override it at runtime, because how
 * hard to enforce a hand-off is an operational decision that moves as the back
 * catalogue catches up -- 'warn' while Valuation is still routinely done after the
 * fact, 'block' once it is done first -- and the department must not need a
 * deployment to change it.
 *
 * Stored in `settings`, the same table and row shape SltrApproval uses: three
 * switches do not earn a table of their own, and this is where the project
 * already keeps switches like these.
 *
 * Every read is defensive. gateMode() is called once per row on the Valuation,
 * Consent and Instrument list screens, so a settings table that is missing or
 * briefly unreachable must fall back to the configured default rather than take
 * those screens down with it.
 */
class DeedsPipelineGates
{
    /** The gates, in the order the pipeline reaches them. */
    public const GATES = [
        'valuation_before_consent',
        'consent_before_registration',
        'consent_print_before_registration',
    ];

    /**
     * block  the action is refused until the previous stage exists.
     * warn   the action is allowed, but answers with a warning and the workflow
     *        strip shows the stage as skipped.
     * off    no check at all.
     */
    public const MODES = ['block', 'warn', 'off'];

    /** The `settings.type` every row written here carries. */
    public const TYPE = 'deeds_pipeline';

    /** Read once per request: see the class note on why this is on a hot path. */
    private static ?array $cache = null;

    /** The `settings.name` that holds one gate. */
    public static function rowName(string $gate): string
    {
        return 'deeds_gate_' . $gate;
    }

    /**
     * The stored overrides, gate => mode. A gate nobody has set is absent, which
     * is what tells the screen it is still showing the shipped default.
     *
     * @return array<string, string>
     */
    public static function overrides(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $out = [];

        try {
            $stored = Setting::whereIn('name', array_map([self::class, 'rowName'], self::GATES))
                ->pluck('value', 'name');

            foreach (self::GATES as $gate) {
                $mode = strtolower(trim((string) $stored->get(self::rowName($gate))));

                if (in_array($mode, self::MODES, true)) {
                    $out[$gate] = $mode;
                }
            }
        } catch (\Throwable $e) {
            // Nothing stored that can be read: every gate falls back to config.
            $out = [];
        }

        return self::$cache = $out;
    }

    /** The shipped default for a gate, ignoring any override. */
    public static function default(string $gate): string
    {
        $mode = strtolower((string) config("deeds_pipeline.gates.{$gate}", 'off'));

        return in_array($mode, self::MODES, true) ? $mode : 'off';
    }

    /** What the gate is actually set to: the override if there is one, else the default. */
    public static function mode(string $gate): string
    {
        return self::overrides()[$gate] ?? self::default($gate);
    }

    /**
     * Save one gate.
     *
     * Choosing the shipped default still writes a row. The alternative -- deleting
     * the row so the gate "returns to config" -- would make an administrator's
     * deliberate choice indistinguishable from never having chosen, and would let
     * a later change to config/deeds_pipeline.php silently move a gate someone had
     * set by hand.
     */
    public static function set(string $gate, string $mode): void
    {
        if (!in_array($gate, self::GATES, true) || !in_array($mode, self::MODES, true)) {
            return;
        }

        Setting::updateOrCreate(
            ['name' => self::rowName($gate)],
            // parent_id has no default on this table and every existing row uses 1.
            ['value' => $mode, 'type' => self::TYPE, 'parent_id' => 1]
        );

        self::$cache = null;
    }
}
