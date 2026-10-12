<?php

namespace App\Services;

use App\Models\ChangeOfPurposeApplication;
use App\Models\SpaApplication;
use App\Models\SpaBill;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS → a new file number, once the contravention bill is settled.
 *
 *   Change of Purpose   an existing title (Direct Allocation, or an existing
 *                       Conversion): a Change of Purpose record is created,
 *                       already Approved — its KAMMA / application /
 *                       recommendation / decision steps are bypassed — so it is
 *                       ready to pick on the MLS commissioning screen, where the
 *                       new number comes from the old one. Source: SPAS.
 *   New Conversion      a new entry with no record before: waits for a new file
 *                       number under Conversion. Manual for now — the officer
 *                       issues it on the MLS screen and records it here.
 *
 * The route comes from the land title type: Statutory with a real file number
 * is an existing title; Customary (the SPAS ID stands in) is a new entry.
 *
 * Never throws into the payment that triggered it: a fault here must not undo
 * money that was received. Every step is safe to run again.
 */
class SpaNewFileService
{
    public const COP            = 'change_of_purpose';
    public const NEW_CONVERSION = 'new_conversion';
    public const SOURCE         = 'spas';

    private const PURPOSE_CODES = [
        'RESIDENTIAL' => 'RES', 'COMMERCIAL' => 'COM', 'INDUSTRIAL' => 'IND',
        'AGRICULTURAL' => 'AG', 'AGRICULTURE' => 'AG', 'AGRIC' => 'AG',
        'MIXED' => 'MIX', 'MIXED USE' => 'MIX',
    ];

    private static ?bool $supported = null;

    /** The columns arrive with migration 2026_10_12_090000; until then nothing happens. */
    public static function supported(): bool
    {
        return self::$supported ??= Schema::connection('sqlsrv')->hasColumn('spa_applications', 'new_file_route');
    }

    /** Which route this record takes to its new file number. */
    public function routeFor(SpaApplication $app): string
    {
        $file = trim((string) $app->file_number);

        return $app->land_title_type !== 'customary' && $file !== '' && $file !== trim((string) $app->spa_id)
            ? self::COP
            : self::NEW_CONVERSION;
    }

    /** Settled = it has a bill and every bill on it is fully paid. */
    public function settled(SpaApplication $app): bool
    {
        $bills = SpaBill::where('spa_application_id', $app->id)->get(['status']);

        return $bills->isNotEmpty() && $bills->every(fn ($b) => $b->status === 'paid');
    }

    /** Called whenever a bill may have become paid. */
    public function onBillChanged(?SpaBill $bill, ?int $actorId = null): void
    {
        if (! $bill || $bill->status !== 'paid' || ! $bill->spa_application_id) {
            return;
        }
        $app = SpaApplication::find($bill->spa_application_id);
        if ($app) {
            $this->handOff($app, $actorId);
        }
    }

    /**
     * Hand a settled record on. Once only: a record already routed is left as it is.
     *
     * @return string|null the route taken now, or null when nothing was done
     */
    public function handOff(SpaApplication $app, ?int $actorId = null): ?string
    {
        try {
            if (! self::supported() || $app->new_file_route || ! $this->settled($app)) {
                return null;
            }

            $route = $this->routeFor($app);
            $changes = ['new_file_route' => $route];

            if ($route === self::COP) {
                $changes['cop_application_id'] = $this->changeOfPurposeFor($app, $actorId)->id;
            }

            $app->forceFill($changes)->save();
            Log::info('SPAS record handed on for a new file number', ['application' => $app->id, 'route' => $route] + $changes);

            return $route;
        } catch (\Throwable $e) {
            Log::warning('SPAS new-file hand-off failed', ['application' => $app->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The Change of Purpose for this record: one already in progress for the
     * file is reused (a file may carry only one live application), otherwise a
     * new one is created, approved.
     */
    private function changeOfPurposeFor(SpaApplication $app, ?int $actorId): ChangeOfPurposeApplication
    {
        $file = trim((string) $app->file_number);

        $live = ChangeOfPurposeApplication::query()
            ->where('file_no', $file)
            ->whereIn('status', [
                ChangeOfPurposeApplication::STATUS_PENDING,
                ChangeOfPurposeApplication::STATUS_PROCESSING,
                ChangeOfPurposeApplication::STATUS_APPROVED,
            ])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        if ($live) {
            return $live;
        }

        $newUse = strtoupper(trim((string) $app->existing_use));
        $bill   = SpaBill::where('spa_application_id', $app->id)->orderBy('id')->value('reference_id');

        return ChangeOfPurposeApplication::create([
            'applicant_name'      => $app->owner_name ?: '—',
            'file_no'             => $file,
            // What it is approved for, and what it is used for now — the new purpose.
            'land_use'            => ucwords(strtolower(trim((string) $app->proposed_use))) ?: null,
            'purpose'             => self::PURPOSE_CODES[$newUse] ?? mb_substr($newUse, 0, 50),
            'new_purpose'         => $newUse ?: null,
            'location'            => $app->location,
            'district'            => $app->district,
            'lga'                 => $app->lga,
            'phone'               => $app->phone,
            'status'              => ChangeOfPurposeApplication::STATUS_APPROVED,
            'knupda_status'       => 'Approved',
            'source_module'       => self::SOURCE,
            'plot_count'          => 1,
            'captured_by'         => $actorId,
            'remarks'             => 'Raised by SPAS ' . ($app->spa_id ?: '#' . $app->id)
                . ($bill ? " after bill {$bill} was settled" : '')
                . ' — contravention: approved ' . $app->proposed_use . ', in use as ' . $app->existing_use
                . '. Approvals bypassed (SPAS).',
        ]);
    }

    /**
     * Pick up the new file number once the Change of Purpose was commissioned on
     * the MLS screen. That screen keeps the number only in the remarks
     * ("Commissioned: OLD -> NEW" / "Commissioned to File No: NEW").
     */
    public function sync(SpaApplication $app): ?string
    {
        if (! self::supported() || $app->new_file_number || $app->new_file_route !== self::COP || ! $app->cop_application_id) {
            return $app->new_file_number ?? null;
        }

        $cop = ChangeOfPurposeApplication::find($app->cop_application_id);
        if (! $cop || $cop->status !== ChangeOfPurposeApplication::STATUS_COMMISSIONED) {
            return null;
        }

        if (preg_match_all('/(?:->|File No:)\s*([^\s,;]+)/i', (string) $cop->remarks, $m) && ($number = end($m[1]))) {
            $app->forceFill(['new_file_number' => $number, 'new_file_at' => $cop->updated_at ?? now()])->save();

            return $number;
        }

        return null;
    }

    /** Sync every record still waiting on a commissioned Change of Purpose. Cheap: only routed, unnumbered rows. */
    public function syncAll(): int
    {
        if (! self::supported()) {
            return 0;
        }
        $n = 0;
        SpaApplication::where('new_file_route', self::COP)->whereNull('new_file_number')->whereNotNull('cop_application_id')
            ->get()->each(function ($app) use (&$n) { if ($this->sync($app)) { $n++; } });

        return $n;
    }

    /** Record a number issued by hand (New Conversion, for now). */
    public function record(SpaApplication $app, string $number): void
    {
        $app->forceFill(['new_file_number' => trim($number), 'new_file_at' => now()])->save();
    }
}
