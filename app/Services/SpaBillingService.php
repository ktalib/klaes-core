<?php

namespace App\Services;

use App\Models\SpaApplication;
use App\Models\SpaBill;
use App\Models\SpaBillItem;
use App\Models\SpaBillLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Raises the contravention bill automatically.
 *
 * WHY THIS EXISTS
 * Bills used to be typed by hand: an officer opened "Add Bill", chose a bill
 * type and entered an amount. Three things followed from that. The tariff lived
 * in people's heads, so two officers billed the same contravention differently.
 * Nothing connected a bill to the finding that justified it. And a contravention
 * nobody happened to look at was simply never billed.
 *
 * Now the tariff is data (`spa_bill_items`) and the bill follows the finding: a
 * record whose approved land use differs from the use prevailing on the ground
 * is in contravention, and is billed the moment it is saved.
 *
 * DELIBERATELY IDEMPOTENT
 * A record is saved and re-saved — corrected in the office, edited from a
 * handset, pulled and pushed by sync. Each of those re-checks the
 * contravention, so without a guard the owner would collect another bill every
 * time anyone touched the record. One auto-raised bill per application, backed
 * by a filtered unique index so two concurrent saves cannot both create one.
 *
 * NEVER THROWS INTO THE CALLER
 * Billing hangs off saving a land record. A tariff misconfiguration must not
 * cost a surveyor their field data, so a failure here is logged and swallowed —
 * the record still saves, and the bill can be raised later.
 *
 * @see database/sql/2026_08_17_spas_bill_items_and_lines.sql
 */
class SpaBillingService
{
    /** A record contravenes when its approved use differs from what is on the ground. */
    public function contravenes(SpaApplication $app): bool
    {
        $approved   = strtoupper(trim((string) $app->proposed_use));
        $prevailing = strtoupper(trim((string) $app->existing_use));

        return $approved !== '' && $prevailing !== '' && $approved !== $prevailing;
    }

    /**
     * Raise the contravention bill for an application, if one is due.
     *
     * @return SpaBill|null the bill raised, or null when nothing was due
     */
    public function billForContravention(SpaApplication $app, ?string $actor = null): ?SpaBill
    {
        try {
            if (! $this->contravenes($app)) {
                return null;
            }

            // The schema ships as a direct SQL script (the MySQL ledger cannot
            // be trusted for these tables), so it may legitimately not be
            // applied yet on a given environment. Degrade quietly rather than
            // 500-ing every land-record save.
            if (! Schema::connection('sqlsrv')->hasTable('spa_bill_items')) {
                return null;
            }

            $existing = SpaBill::where('spa_application_id', $app->id)
                ->where('source', 'contravention')
                ->first();

            if ($existing) {
                return $existing;
            }

            $items = SpaBillItem::billable();

            // No tariff set yet. Not an error — the table seeds at zero on
            // purpose so nothing is charged before an officer configures it.
            if ($items->isEmpty()) {
                return null;
            }

            return DB::connection('sqlsrv')->transaction(function () use ($app, $items, $actor) {
                $bill = SpaBill::create([
                    'spa_application_id' => $app->id,
                    'reference_id'       => $this->nextReference(),
                    'bill_type'          => 'Contravention',
                    'description'        => $this->describe($app, $items),
                    'amount'             => $items->sum(fn ($i) => (float) $i->amount),
                    'due_date'           => now()->addDays(30)->toDateString(),
                    'status'             => 'unpaid',
                    'source'             => 'contravention',
                    'created_by'         => $actor ?? 'system',
                ]);

                foreach ($items as $item) {
                    SpaBillLine::create([
                        'spa_bill_id'      => $bill->id,
                        'spa_bill_item_id' => $item->id,
                        // Copied, not referenced — see SpaBillLine.
                        'name'             => $item->name,
                        'amount'           => $item->amount,
                    ]);
                }

                return $bill;
            });
        } catch (\Throwable $e) {
            // A unique-index violation here is the concurrency guard doing its
            // job: another request raised the bill first. Anything else is a
            // real fault worth reading in the log.
            Log::warning('SPAS auto-billing skipped', [
                'application' => $app->id,
                'error'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** SPAS-BILL-YYYY-### — the same numbering hand-entered bills use. */
    private function nextReference(): string
    {
        return SpaBill::nextReference();
    }

    /** Human-readable reason, so the bill explains itself without a join. */
    private function describe(SpaApplication $app, $items): string
    {
        return sprintf(
            'Contravention: approved use "%s" differs from prevailing use "%s". Composed of: %s.',
            $app->proposed_use,
            $app->existing_use,
            $items->map(fn ($i) => $i->name.' ('.number_format((float) $i->amount, 2).')')->implode(', ')
        );
    }
}
