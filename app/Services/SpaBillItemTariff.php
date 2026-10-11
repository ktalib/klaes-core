<?php

namespace App\Services;

use App\Models\SpaBillItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The SPAS contravention tariff (spa_bill_items), as edited on
 * System Admin -> Configurable Entries -> SPAS Bill Items.
 *
 * Bills are raised from it automatically (SpaBillingService); this only says
 * what a contravention costs from now on.
 */
class SpaBillItemTariff
{
    /**
     * The items on the tariff, switched on or off, in the order the editor shows
     * them. Removed items are left out — removing is not the same as switching off.
     */
    public function all(): Collection
    {
        return SpaBillItem::notRemoved()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** What one contravention is billed under the current tariff. */
    public function total(): float
    {
        return (float) SpaBillItem::billable()->sum(fn ($i) => (float) $i->amount);
    }

    /**
     * Replace the tariff with what the editor submitted.
     *
     * Rows the officer removed (the x in the editor) are flagged REMOVED and
     * switched off, never deleted: bills already raised reference them. A
     * removed item leaves the settings list and the Bills table's columns and
     * is never charged again; re-adding the same name brings it back. Before the
     * removal migration has run, removing only switches the item off.
     *
     * @param  array<int, array{id?: int|string|null, name: string, amount: mixed, is_active: mixed, is_on_demand?: mixed, sort_order?: int|null}>  $items
     */
    public function save(array $items, string $actor): void
    {
        $keptIds = [];

        DB::connection('sqlsrv')->transaction(function () use ($items, $actor, &$keptIds) {
            foreach (array_values($items) as $i => $row) {
                $values = [
                    'name'       => trim($row['name']),
                    'amount'     => $row['amount'],
                    'is_active'  => (bool) $row['is_active'],
                    'sort_order' => $row['sort_order'] ?? ($i + 1),
                    'updated_by' => $actor,
                ];

                // A row the editor sent is on the tariff — including a name that
                // was removed earlier and has just been added back.
                if (SpaBillItem::supportsRemoval()) {
                    $values += ['is_removed' => 0, 'removed_at' => null, 'removed_by' => null];
                }

                // "Add when needed": never charged automatically; an officer adds
                // it to a particular bill from the Generate Bill form.
                if (SpaBillItem::supportsOnDemand()) {
                    $values['is_on_demand'] = ! empty($row['is_on_demand']);
                }

                $item = ! empty($row['id']) ? SpaBillItem::find($row['id']) : null;

                if ($item) {
                    $oldName = $item->name;
                    $item->update($values);

                    if ($oldName !== $values['name']) {
                        $this->renameOnExistingBills($item->id, $oldName, $values['name']);
                    }
                } else {
                    // updateOrCreate on the name, because the unique index makes
                    // a re-added item collide with the row that was deactivated
                    // earlier rather than creating a duplicate.
                    $item = SpaBillItem::updateOrCreate(
                        ['name' => $values['name']],
                        $values + ['created_by' => $actor]
                    );
                }

                $keptIds[] = $item->id;
            }

            // Everything the editor no longer lists has been removed.
            $gone = SpaBillItem::when($keptIds, fn ($q) => $q->whereNotIn('id', $keptIds));

            if (SpaBillItem::supportsRemoval()) {
                $gone->notRemoved()->update([
                    'is_active'  => 0,
                    'is_removed' => 1,
                    'removed_at' => now(),
                    'removed_by' => $actor,
                    'updated_by' => $actor,
                ]);
            } else {
                $gone->update(['is_active' => 0, 'updated_by' => $actor]);
            }
        });
    }

    /**
     * Carry a renamed item's new name onto bills already raised from it.
     *
     * Bill and payment lines keep their own copy of the name (so a bill reads
     * the same however the tariff changes), which meant a rename never reached
     * the bills table, the payment form, receipts or the Payments page. Only the
     * NAME follows the tariff; every amount billed or paid stays as it was.
     * Matched on the item id, never on the text.
     */
    private function renameOnExistingBills(int $itemId, string $oldName, string $newName): void
    {
        $db = DB::connection('sqlsrv');

        $lineIds = $db->table('spa_bill_lines')->where('spa_bill_item_id', $itemId)->pluck('id');
        if ($lineIds->isEmpty()) {
            return;
        }

        $db->table('spa_bill_lines')->whereIn('id', $lineIds)->update(['name' => $newName]);
        $db->table('spa_payment_lines')->whereIn('spa_bill_line_id', $lineIds)->update(['name' => $newName]);

        // The bill's description sentence lists its items as "Name (amount)".
        $billIds = $db->table('spa_bill_lines')->whereIn('id', $lineIds)->distinct()->pluck('spa_bill_id');
        foreach ($db->table('spa_bills')->whereIn('id', $billIds)->get(['id', 'description']) as $bill) {
            $text = (string) $bill->description;
            $renamed = str_replace($oldName . ' (', $newName . ' (', $text);
            if ($renamed !== $text) {
                $db->table('spa_bills')->where('id', $bill->id)->update(['description' => $renamed]);
            }
        }
    }
}
