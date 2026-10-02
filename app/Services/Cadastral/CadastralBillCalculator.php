<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralBill;
use App\Models\Cadastral\CadastralPlanDescription;
use App\Models\Cadastral\CadastralFileReceipt;
use Illuminate\Validation\ValidationException;

/**
 * The cadastral bill, line for line from the official sheet "Right of Occupancy
 * - Cadastral Fees and Area" (rebuild plan D1, §3b):
 *
 *   Investigation and Search   flat rate x 1
 *   Beacons                    rate x live pillar rows
 *   Area fee                   CadastralSettings::areaFee(hectares)
 *   Delay                      rate x days (officer)
 *   Transport                  band fee (officer picks the band, or the km)
 *   Additional field work      rate x days (officer)
 *   Office work                rate x days (officer)
 *   Plan prints                rate x count (officer)
 *   Total                      sum of the lines
 *
 * Every rate, the area-fee schedule and the transport bands come from
 * CadastralSettings, i.e. what admins save in Configurable Entries -> Cadastral.
 * The old "m² x land use x zone + service charge + description fee" formula is
 * gone (D1).
 *
 * THREE RULES THAT ARE NOT NEGOTIABLE.
 *
 *  1. Everything that comes from the record is read from the record: the area
 *     from area_sqm, the beacon count from the live cadastral_pillars rows. A
 *     count or total posted from the browser is never read. Only the four
 *     officer-entered quantities (days, prints) and the transport choice are.
 *
 *  2. Each line is rounded to the kobo and THEN summed, so the figures printed
 *     on the bill add up to the printed total.
 *
 *  3. An area the schedule cannot price is REFUSED, never billed at zero.
 *     areaFee() returns null above the largest row while Q2 is open (and for an
 *     empty schedule); the bill is refused with the reason, so "cannot be
 *     charged" is never mistaken for "free".
 *
 * issue() copies every rate, quantity and amount onto the bill row, so a reprint
 * shows what the applicant was charged after the rates change.
 *
 * NOTHING IS WRITTEN TO THE CENTRAL `billing` TABLE. That is Phase 8 (Q5);
 * cadastral_bills.billing_id is the hook.
 */
class CadastralBillCalculator
{
    /** Upper bound on any officer-entered quantity: a typo guard, not a policy. */
    public const MAX_QTY = 999;

    public function __construct(private CadastralSettings $settings) {}

    /**
     * The officer-entered inputs, cleaned. Missing or blank means 0 / none.
     *
     * @return array{delay_days: int, field_work_days: int, office_work_days: int, plan_prints: int, transport_band: string, transport_km: ?float}
     */
    public function inputs(array $in): array
    {
        $int = fn ($v) => max(0, min(self::MAX_QTY, (int) (is_numeric($v) ? $v : 0)));

        $km = $in['transport_km'] ?? null;

        return [
            'delay_days'       => $int($in['delay_days'] ?? 0),
            'field_work_days'  => $int($in['field_work_days'] ?? 0),
            'office_work_days' => $int($in['office_work_days'] ?? 0),
            'plan_prints'      => $int($in['plan_prints'] ?? 0),
            'transport_band'   => trim((string) ($in['transport_band'] ?? '')),
            'transport_km'     => is_numeric($km) && (float) $km > 0 ? round((float) $km, 2) : null,
        ];
    }

    /**
     * The transport bands keyed the way the page's select posts them: the
     * table id, or "i{n}" for a config fallback row (which has no id).
     *
     * @return array<string, array>
     */
    public function bandOptions(): array
    {
        $out = [];
        foreach ($this->settings->transportBands() as $i => $band) {
            $out[$band['id'] !== null ? (string) $band['id'] : 'i' . $i] = $band;
        }

        return $out;
    }

    /**
     * Every line for a record and the officer's inputs, without saving.
     *
     * Always returns the lines it could work out. 'refusal' is the first reason
     * the bill cannot be issued (null when it can) and 'total' is null while it
     * is refused, so a partial sum is never shown as a total.
     */
    public function compute(CadastralPlanDescription $pd, array $input = []): array
    {
        $in    = $this->inputs($input);
        $rates = $this->settings->feeRates();

        $refusals = [];
        $flags    = [];

        // ---- Beacons: counted from the live rows (soft-deleted ones excluded). ----
        $pillars = $pd->pillars()->get(['id', 'ownership']);
        $govt    = $pillars->where('ownership', 'government')->count();
        $private = $pillars->count() - $govt;
        $beacons = $pillars->count();

        if ($beacons === 0) {
            $refusals[] = 'No pillars are recorded on this file. Beacons are charged per pillar, so record them on Area & Pillars first.';
        }

        // ---- Area fee: the configured column, between-rows rule and above-max rule. ----
        $sqm = $pd->area_sqm;
        $ha  = $sqm === null ? null : round((float) $sqm / (float) config('cadastral_module.area.sqm_per_hectare', 10000), 4);

        $column     = $this->settings->areaScheduleColumn();
        $rule       = $this->settings->betweenRowsRule();
        $aboveRule  = $this->settings->aboveMaxArea();
        $maxHa      = $this->settings->maxScheduleHectares();
        $areaFee    = null;
        $row        = null;

        if ($ha === null || $ha <= 0) {
            $refusals[] = 'No area is recorded on this file. Record it on Area & Pillars first.';
        } else {
            $areaFee = $this->settings->areaFee($ha);
            $row     = $this->scheduleRow($ha, $column);

            if ($areaFee === null) {
                $refusals[] = $maxHa !== null && $ha > $maxHa
                    ? sprintf(
                        'The area is %s ha, above the largest row of the area-fee schedule (%s ha). What is charged above it is unconfirmed (Q2), and the rule in Configurable Entries -> Cadastral is to refuse. Nothing can be billed until that is settled.',
                        $this->ha($ha), $this->ha($maxHa)
                    )
                    : 'The area-fee schedule has no usable row in the "' . CadastralSettings::SCHEDULE_COLUMNS[$column] . '" column, so the area cannot be priced. Check Configurable Entries -> Cadastral.';
            } else {
                if ($row && $row['between'] && ! $this->settings->get('fee_sheet.between_rows_confirmed', false)) {
                    $flags['Q1'] = sprintf(
                        '%s ha lies between two schedule rows and is charged by the rule "%s", which the department has not confirmed (Q1).',
                        $this->ha($ha), CadastralSettings::BETWEEN_ROWS_RULES[$rule]
                    );
                }
                if ($row && $row['above']) {
                    $flags['Q2'] = sprintf(
                        '%s ha is above the largest schedule row and is charged that row\'s fee, which the department has not confirmed (Q2).',
                        $this->ha($ha)
                    );
                }
            }
        }

        // ---- Transport: the officer's band, or the band the km falls in. ----
        $band  = null;
        $bands = $this->bandOptions();

        if ($in['transport_km'] !== null) {
            $band = $this->settings->transportBandFor($in['transport_km']);
            if (! $band) {
                $refusals[] = sprintf('No transport band covers %s km. Pick the band instead, or check the bands in Configurable Entries -> Cadastral.', $in['transport_km']);
            }
        } elseif ($in['transport_band'] !== '' && $in['transport_band'] !== 'none') {
            $band = $bands[$in['transport_band']] ?? null;
            if (! $band) {
                $refusals[] = 'That transport band no longer exists. Pick it again.';
            }
        }

        if ($band && ! $this->settings->get('fee_sheet.transport_bands_confirmed', false)) {
            $flags['Q3'] = 'The transport bands are read from a garbled line on the sheet ("1.1 km / 11-5 km / over 50 km") and the department has not confirmed them (Q3).';
        }

        // ---- The lines. Rounded one by one, then summed. ----
        $line = function (string $key, float $rate, int $qty, string $qtyLabel, string $basis) {
            return [
                'key'       => $key,
                'label'     => CadastralBill::LINES[$key],
                'rate'      => round($rate, 2),
                'qty'       => $qty,
                'qty_label' => $qtyLabel,
                'basis'     => $basis,
                'amount'    => round(round($rate, 2) * $qty, 2),
            ];
        };

        $lines = [
            'investigation' => $line('investigation', $rates['investigation'], 1, '1', 'Flat'),
            'beacon'        => $line('beacon', $rates['beacon'], $beacons, $beacons . ' beacon' . ($beacons === 1 ? '' : 's'),
                                     "{$govt} government + {$private} private, from the pillar rows"),
            'area'          => $areaFee === null
                ? ['key' => 'area', 'label' => CadastralBill::LINES['area'], 'rate' => null, 'qty' => 1,
                   'qty_label' => $ha === null ? '—' : $this->ha($ha) . ' ha', 'basis' => 'Cannot be priced', 'amount' => null]
                : $line('area', $areaFee, 1, $this->ha($ha) . ' ha', $this->areaBasis($row, $column, $rule)),
            'delay'         => $line('delay', $rates['delay_per_day'], $in['delay_days'], $this->days($in['delay_days']), 'Per day'),
            'transport'     => $band
                ? $line('transport', $band['fee'], 1, $band['label'], $in['transport_km'] !== null ? "{$in['transport_km']} km" : 'Band chosen')
                : $line('transport', 0, 0, 'None', 'No transport'),
            'field_work'    => $line('field_work', $rates['field_work_per_day'], $in['field_work_days'], $this->days($in['field_work_days']), 'Per day'),
            'office_work'   => $line('office_work', $rates['office_work_per_day'], $in['office_work_days'], $this->days($in['office_work_days']), 'Per day'),
            'plan_print'    => $line('plan_print', $rates['plan_print'], $in['plan_prints'],
                                     $in['plan_prints'] . ' file' . ($in['plan_prints'] === 1 ? '' : 's'), 'Per file'),
        ];

        $total = $refusals === []
            ? round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2)
            : null;

        return [
            'ok'        => $refusals === [],
            'refusal'   => $refusals[0] ?? null,
            'refusals'  => $refusals,
            'flags'     => $flags,
            'lines'     => $lines,
            'total'     => $total,
            'inputs'    => $in,
            'area'      => [
                'sqm'             => $sqm === null ? null : (float) $sqm,
                'ha'              => $ha,
                'column'          => $column,
                'column_label'    => CadastralSettings::SCHEDULE_COLUMNS[$column],
                'row_ha'          => $row['row_ha'] ?? null,
                'between_rule'    => $rule,
                'above_rule'      => $aboveRule,
                'max_ha'          => $maxHa,
            ],
            'transport' => $band ? ['label' => $band['label'], 'fee' => (float) $band['fee'], 'km' => $in['transport_km']] : null,
            'pillars'   => ['government' => $govt, 'private' => $private, 'total' => $beacons],
            'installed' => CadastralBill::feeSheetInstalled(),
        ];
    }

    /**
     * Issue a bill from the officer's inputs.
     *
     * Refused (ValidationException carrying the reason) when the fee-sheet
     * columns are not installed yet, or when compute() refuses.
     *
     * CANCELLED, NEVER DELETED. Earlier Draft bills on the record are cancelled
     * (the pre-Phase 7 behaviour). An earlier Issued bill that is neither paid
     * nor pushed to Revenue is cancelled too and points at its replacement
     * (superseded_by_bill_id): two live Issued bills for one file would both be
     * counted in Fees YTD. An Issued bill already pushed to Revenue blocks a new
     * one; it has to be undone there first.
     *
     * The caller wraps this in a transaction.
     */
    public function issue(CadastralPlanDescription $pd, array $input = []): CadastralBill
    {
        if (! CadastralBill::feeSheetInstalled()) {
            throw ValidationException::withMessages([
                'bill' => 'Bills cannot be saved yet: the fee-sheet columns are pending installation (migration 2026_10_02_120000).',
            ]);
        }

        $c = $this->compute($pd, $input);

        if (! $c['ok']) {
            throw ValidationException::withMessages(['bill' => $c['refusal']]);
        }

        $pushed = $pd->bills()->where('status', 'Issued')->whereNotNull('billing_id')->first();
        if ($pushed) {
            throw ValidationException::withMessages([
                'bill' => "{$pushed->bill_ref} has already been pushed to Revenue. It has to be withdrawn there before a new bill is issued.",
            ]);
        }

        $l = $c['lines'];

        $bill = new CadastralBill();
        $bill->forceFill([
            'bill_ref'                      => CadastralBill::nextRef('bill_ref', 'CBL', 4),
            'cadastral_plan_description_id' => $pd->id,
            'file_number'                   => $pd->file_number,
            'status'                        => 'Issued',
            'issued_at'                     => now(),
            'bill_date'                     => now()->toDateString(),

            // The sheet's header.
            're_cad_ref'                    => $this->reCadRef($pd),
            'rofo_no'                       => $pd->file_number,

            // How the area was priced.
            'area_sqm'                      => $c['area']['sqm'],
            'area_ha'                       => $c['area']['ha'],
            'schedule_column'               => $c['area']['column'],
            'schedule_row_ha'               => $c['area']['row_ha'],
            'between_rows_rule'             => $c['area']['between_rule'],
            'above_max_rule'                => $c['area']['above_rule'],

            'transport_band_label'          => $c['transport']['label'] ?? null,
            'transport_km'                  => $c['transport']['km'] ?? null,
            'unconfirmed_rules'             => $c['flags'] === [] ? null : implode(',', array_keys($c['flags'])),

            // Old columns that keep their meaning (DashboardMetrics reads them).
            'area_fee'                      => $l['area']['amount'],
            'pillar_fee'                    => $l['beacon']['amount'],
            'pillar_unit_price'             => $l['beacon']['rate'],
            'pillar_count_government'       => $c['pillars']['government'],
            'pillar_count_private'          => $c['pillars']['private'],
            'grand_total'                   => $c['total'],
        ]);

        foreach ($l as $key => $line) {
            $bill->forceFill([
                "{$key}_rate"   => $line['rate'],
                "{$key}_qty"    => $line['qty'],
                "{$key}_amount" => $line['amount'],
            ]);
        }

        $bill->save();

        // Earlier drafts, and an earlier unpaid un-pushed issue, are cancelled.
        $pd->bills()
            ->whereKeyNot($bill->id)
            ->where(fn ($w) => $w->where('status', 'Draft')
                ->orWhere(fn ($i) => $i->where('status', 'Issued')->whereNull('billing_id')))
            ->get()
            ->each(function (CadastralBill $old) use ($bill) {
                $old->forceFill([
                    'status'                => 'Cancelled',
                    'superseded_by_bill_id' => $bill->id,
                    'cancelled_at'          => now(),
                    'cancelled_by'          => auth()->id(),
                    'cancel_reason'         => "Superseded by {$bill->bill_ref}.",
                ])->save();
            });

        // Stamp the beacon rate onto the pillar rows, as before.
        $pd->pillars()->update(['unit_price' => $l['beacon']['rate']]);

        return $bill;
    }

    /**
     * The bill's lines as the print renders them, from the bill's own snapshot.
     * Nothing here reads a current rate.
     *
     * @return array<string, array{label: string, rate: ?float, qty: ?int, qty_label: string, amount: ?float}>
     */
    public function lines(CadastralBill $bill): array
    {
        $out = [];

        foreach (CadastralBill::LINES as $key => $label) {
            $qty = $bill->{"{$key}_qty"};

            $out[$key] = [
                'label'     => $label,
                'rate'      => $bill->{"{$key}_rate"},
                'qty'       => $qty,
                'amount'    => $bill->{"{$key}_amount"},
                'qty_label' => match ($key) {
                    'area'          => $bill->area_ha !== null ? $this->ha((float) $bill->area_ha) . ' ha' : '',
                    'transport'     => $qty ? (string) ($bill->transport_km !== null ? $this->num($bill->transport_km) . ' km' : $bill->transport_band_label) : '',
                    'delay', 'field_work', 'office_work' => $qty === null ? '' : $this->days((int) $qty),
                    default         => $qty === null ? '' : (string) $qty,
                },
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    /**
     * The schedule row an area is charged at, worked out the same way as
     * CadastralSettings::areaFee() (same epsilon, same column filter), so the
     * print can highlight it. row_ha is the row charged: the exact row, the
     * next row up, the smallest row (below it) or the largest (above it); when
     * interpolating, the upper of the two rows.
     *
     * @return array{row_ha: float, between: bool, above: bool}|null
     */
    private function scheduleRow(float $ha, string $column): ?array
    {
        $rows = array_values(array_filter(
            $this->settings->areaSchedule(),
            fn ($r) => $r[$column . '_fee'] !== null
        ));

        if ($rows === []) {
            return null;
        }

        $ha = round($ha, 4);
        $epsilon = 0.00005;

        if ($ha <= $rows[0]['hectares'] + $epsilon) {
            return ['row_ha' => $rows[0]['hectares'], 'between' => false, 'above' => false];
        }

        foreach ($rows as $row) {
            if (abs($ha - $row['hectares']) < $epsilon) {
                return ['row_ha' => $row['hectares'], 'between' => false, 'above' => false];
            }
            if ($ha < $row['hectares']) {
                return ['row_ha' => $row['hectares'], 'between' => true, 'above' => false];
            }
        }

        return ['row_ha' => end($rows)['hectares'], 'between' => false, 'above' => true];
    }

    private function areaBasis(?array $row, string $column, string $rule): string
    {
        $col = CadastralSettings::SCHEDULE_COLUMNS[$column];

        if (! $row) {
            return $col;
        }
        if ($row['above']) {
            return "{$col}, largest row (" . $this->ha($row['row_ha']) . ' ha)';
        }
        if ($row['between']) {
            return $rule === 'interpolate'
                ? "{$col}, interpolated below the " . $this->ha($row['row_ha']) . ' ha row'
                : "{$col}, next row up (" . $this->ha($row['row_ha']) . ' ha)';
        }

        return "{$col}, " . $this->ha($row['row_ha']) . ' ha row';
    }

    /**
     * The sheet's "Re CAD/" reference: the file's cadastral correspondence
     * (shadow) file number when intake commissioned one, else the record's own
     * reference. A leading "CAD/" is dropped, because the sheet prints it.
     */
    private function reCadRef(CadastralPlanDescription $pd): string
    {
        $receipt = CadastralFileReceipt::query()
            ->where('file_number', $pd->file_number)
            ->whereNotNull('cadastral_shadow_file_id')
            ->orderByDesc('id')
            ->with('shadowFile')
            ->first();

        $ref = trim((string) ($receipt?->shadowFile?->full_number ?? '')) ?: (string) $pd->pd_ref;

        return preg_replace('#^CAD\s*/\s*#i', '', $ref);
    }

    /** Hectares to at least two places, more only when they say something. */
    private function ha(float $ha): string
    {
        $s = rtrim(rtrim(number_format($ha, 4, '.', ','), '0'), '.');

        return str_contains($s, '.') && strlen(substr(strrchr($s, '.'), 1)) >= 2 ? $s : number_format($ha, 2);
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
    }

    private function days(int $n): string
    {
        return $n . ' day' . ($n === 1 ? '' : 's');
    }
}
